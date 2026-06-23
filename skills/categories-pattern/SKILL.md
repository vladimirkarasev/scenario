---
name: categories-pattern
description: Add category support to a module by inheriting from Categories' abstract CategoryController and providing modelClass(). Categories are polymorphic via model_has_categories; each consuming module registers its own controller and route. Apply when asked to add categorization, sections, or folders to a domain entity.
---

# Categories Pattern (inheritance per module)

`module/Categories/` — общий базовый модуль категорий. Работает через наследование: каждый потребитель объявляет свой контроллер с указанием модели.

## Архитектура

| Таблица | Назначение |
|---|---|
| `categories` | сами категории (дерево через `parent_id`, UUID PK) |
| `model_has_categories` | полиморфная pivot: `category_id`, `model_id` (uuid), `model_type` (класс модели) |

| Класс | Что делает |
|---|---|
| `Module\Categories\Http\Controllers\CategoryController` | abstract — базовый CRUD; требует `modelClass(): string` |
| `Module\Categories\Services\CategoryService::forModel(class-string)` | фильтрует категории по `model_type` через `model_has_categories` |
| `Module\Categories\Repositories\CategoryRepository::forModel(class-string)` | SQL: `WHERE EXISTS … model_type = ?` |

## Как подключить категории к новому модулю

### 1. Контроллер-наследник

```php
// module/Widgets/Http/Controllers/WidgetCategoryController.php
namespace Module\Widgets\Http\Controllers;

use Module\Categories\Http\Controllers\CategoryController;
use Module\Widgets\Models\Widget;

final class WidgetCategoryController extends CategoryController
{
    protected function modelClass(): string
    {
        return Widget::class;
    }
}
```

### 2. Роут в своём модуле

```php
// module/Widgets/routes/api.php
Route::prefix('api/widgets/categories')->group(function () {
    Route::get('/',           [WidgetCategoryController::class, 'index']);
    Route::post('/',          [WidgetCategoryController::class, 'store']);
    Route::put('/{category}', [WidgetCategoryController::class, 'update']);
    Route::delete('/{category}', [WidgetCategoryController::class, 'destroy']);
});
```

Роут живёт **в модуле-потребителе**, не в `Categories`.

### 3. Модель потребителя

- Должна использовать `Illuminate\Database\Eloquent\Concerns\HasUuids` (поле `model_id` — uuid).
- При сохранении категорий: `$widget->categories()->sync($categoryIds)` через morphToMany.

### 4. Frontend

Используй `useDirectorySectionTree` или [[scenario-section-tree]] как образец lazy-tree. Контроллер на бэке должен поддерживать `filter[parent_id]` если нужно lazy-loading.

## Существующие потребители

- **Directories** → `DirectoryCategoryController` → `api/directories/categories`. См. `module/Directories/Http/Controllers/DirectoryCategoryController.php`.
- **Scenario** → `ScenarioCategoryController` (наследует + переопределяет `index()` для `relevantCategories()` с ancestor-walking).

## Почему так

Категории общие для всего приложения (одна таблица `categories`), но каждый модуль видит только «свои» через фильтр по `model_has_categories.model_type`. Это позволяет переиспользовать CRUD и схему, но изолировать видимость.

## Связанные

- [[scenario-section-tree]] — реализация lazy-tree на фронте + `relevantCategories()` с ancestor walking.
- См. `docs/categories.md` в репозитории (если есть) — там оригинальная документация.
