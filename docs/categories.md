# Categories module

Модуль `module/Categories/` — общий механизм категоризации для любых моделей приложения. Категории хранятся в таблице `categories`, а привязка к конкретным моделям — в полиморфной таблице `model_has_categories`.

## Таблицы

### `categories`
| Колонка | Тип | Описание |
|---|---|---|
| `id` | uuid | PK |
| `parent_id` | uuid\|null | Родительская категория (дерево) |
| `name` | string | Уникальное название |
| `is_active` | boolean | |
| `created_by` / `updated_by` | int\|null | FK → users |

### `model_has_categories`
| Колонка | Тип | Описание |
|---|---|---|
| `category_id` | uuid | FK → categories (CASCADE DELETE) |
| `model_id` | uuid | PK модели-потребителя |
| `model_type` | string | Полное имя класса модели |

PK составной: `(category_id, model_id, model_type)`.

`model_type` отвечает за изоляцию: каждый модуль видит только свои категории.

## Архитектура: наследование контроллеров

`CategoryController` — абстрактный базовый класс. Каждый модуль-потребитель создаёт свой контроллер, наследует его и указывает `modelClass()`.

```php
// module/Categories/Http/Controllers/CategoryController.php
abstract class CategoryController extends Controller
{
    /** @return class-string */
    abstract protected function modelClass(): string;

    public function index(): AnonymousResourceCollection
    {
        // фильтрует через model_has_categories по modelClass()
        return CategoryResource::collection(
            $this->categories->forModel($this->modelClass()),
        );
    }

    // store / update / destroy — общие, переиспользуются
}
```

```php
// module/Directories/Http/Controllers/DirectoryCategoryController.php
final class DirectoryCategoryController extends CategoryController
{
    protected function modelClass(): string
    {
        return Directory::class;
    }
}
```

Роут регистрируется **в модуле-потребителе**, не в Categories:

```php
// module/Directories/routes/api.php
Route::prefix('api/directories/categories')
    ->name('directories.categories.')
    ->middleware(['api', 'auth:sanctum'])
    ->group(static function (): void {
        Route::get('/', [DirectoryCategoryController::class, 'index'])->name('index');
        Route::post('/', [DirectoryCategoryController::class, 'store'])->name('store');
        Route::get('/{category}', [DirectoryCategoryController::class, 'show'])->name('show');
        Route::put('/{category}', [DirectoryCategoryController::class, 'update'])->name('update');
        Route::delete('/{category}', [DirectoryCategoryController::class, 'destroy'])->name('destroy');
    });
```

## Как подключить к новому модулю

1. **Создать контроллер** в своём модуле:
```php
// module/Scenario/Http/Controllers/ScenarioCategoryController.php
final class ScenarioCategoryController extends CategoryController
{
    protected function modelClass(): string
    {
        return Scenario::class;
    }
}
```

2. **Добавить роут** в `module/Scenario/routes/api.php`:
```php
Route::prefix('api/scenarios/categories')
    ->name('scenarios.categories.')
    ->middleware(['api', 'auth:sanctum'])
    ->group(static function (): void {
        Route::get('/', [ScenarioCategoryController::class, 'index'])->name('index');
        Route::post('/', [ScenarioCategoryController::class, 'store'])->name('store');
        Route::put('/{category}', [ScenarioCategoryController::class, 'update'])->name('update');
        Route::delete('/{category}', [ScenarioCategoryController::class, 'destroy'])->name('destroy');
    });
```

3. **Добавить relation** в модель:
```php
// Модель должна использовать HasUuids (model_id — uuid)
public function categories(): MorphToMany
{
    return $this->morphToMany(Category::class, 'model', 'model_has_categories', 'model_id', 'category_id')
        ->withTimestamps();
}
```

4. **Синкать при сохранении** через `$model->categories()->sync($categoryIds)`.

## Permissions

Каждый модуль определяет свои права. Для Categories:
- `CategoryPermission::Create` → `category_create`
- `CategoryPermission::Delete` → `category_delete`

Права проверяются через `$request->user()?->can('category_create')` — это идёт через `Gate::before`, поэтому администраторы проходят автоматически.

## Текущие потребители

| Модуль | Контроллер | Endpoint |
|---|---|---|
| Directories | `DirectoryCategoryController` | `api/directories/categories` |

## Frontend

Репозиторий: `resources/js/repositories/categoryRepository.ts`  
Тип: `CategoryRef { id, parent_id, name, is_active }`

Каждый модуль использует свой URL при вызове репозитория.
