---
name: add-new-module
description: Scaffold a new Laravel domain module — directory structure, ServiceProvider, routes registration, namespace, PHPStan path. Use when asked to add a new module, domain area, or major feature that doesn't fit an existing module.
---

# Add New Module

## When to create a new module

Create a new module when the domain is independent enough to have its own models, routes, and permissions. If the feature fits into an existing module — add it there instead.

Existing modules for reference: `Scenario`, `Actions`, `Categories`, `Directories`, `Projects`, `Groups`, `Users`, `Gateways`, `Proxy`.

---

## Directory layout

```
module/<Module>/
  DTO/
  Enums/
    <Module>Permission.php   ← если нужны права
  Exceptions/
  Http/
    Controllers/
    Requests/
    Resources/
      JsonApi/               ← JSON:API ресурсы
  Models/
  Providers/
    <Module>ServiceProvider.php
  Repositories/
  Services/
  routes/
    api.php                  ← API-роуты (JSON:API)
    web.php                  ← web-роуты (Inertia) — если нужна страница
```

Не создавай папки, которые не нужны прямо сейчас — добавляй по мере роста модуля.

---

## Step 1 — ServiceProvider

```php
// module/Widgets/Providers/WidgetsServiceProvider.php
<?php

declare(strict_types=1);

namespace Module\Widgets\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class WidgetsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware(['api', 'auth:sanctum'])
            ->prefix('api')
            ->group(dirname(__DIR__).'/routes/api.php');

        // Если есть web-страницы:
        // Route::middleware('web')
        //     ->group(dirname(__DIR__).'/routes/web.php');
    }
}
```

Если нужны дополнительные биндинги, артизан-команды или event-листенеры — добавляй в `register()` / `boot()` по аналогии с `ProxyServiceProvider`.

---

## Step 2 — Регистрация в bootstrap/providers.php

```php
// bootstrap/providers.php
return [
    AppServiceProvider::class,
    // ... остальные ...
    Module\Widgets\Providers\WidgetsServiceProvider::class,  // ← добавить
];
```

Порядок не важен — все провайдеры загружаются при старте.

---

## Step 3 — Routes

```php
// module/Widgets/routes/api.php
<?php

use Illuminate\Support\Facades\Route;
use Module\Widgets\Http\Controllers\WidgetController;

Route::middleware('can:widget_view')->group(static function (): void {
    Route::get('widgets',            [WidgetController::class, 'index']);
    Route::get('widgets/{widget}',   [WidgetController::class, 'show']);
});

Route::middleware('can:widget_create')->group(static function (): void {
    Route::post('widgets',           [WidgetController::class, 'store']);
    Route::put('widgets/{widget}',   [WidgetController::class, 'update']);
    Route::patch('widgets/{widget}', [WidgetController::class, 'update']);
});

Route::middleware('can:widget_delete')->group(static function (): void {
    Route::delete('widgets/{widget}', [WidgetController::class, 'destroy']);
});
```

---

## Step 4 — PHPStan path

PHPStan уже анализирует всю папку `module/` — путь добавлять не нужно. Конфиг `phpstan.neon`:

```yaml
parameters:
    paths:
        - app
        - module   # ← покрывает module/<Module> автоматически
```

---

## Step 5 — Permissions (если нужны)

```php
// module/Widgets/Enums/WidgetPermission.php
enum WidgetPermission: string implements \App\Contracts\PermissionEnum
{
    case View   = 'widget_view';
    case Create = 'widget_create';
    case Delete = 'widget_delete';

    public function label(): string
    {
        return match ($this) {
            self::View   => 'Просмотр виджетов',
            self::Create => 'Создание виджетов',
            self::Delete => 'Удаление виджетов',
        };
    }

    public function group(): string
    {
        return 'Виджеты';
    }
}
```

Зарегистрируй в `module/Users/PermissionRegistry.php`:

```php
public static function list(): array
{
    return [
        // ...
        \Module\Widgets\Enums\WidgetPermission::class,
    ];
}
```

Затем запусти seeders (см. `add-new-permission` skill).

---

## Step 6 — Первая модель и миграция

```bash
# Создать миграцию
php artisan make:migration create_widgets_table

# Создать модель вручную — не через artisan (он кладёт в app/)
# module/Widgets/Models/Widget.php
```

```php
// module/Widgets/Models/Widget.php
final class Widget extends Model
{
    use HasUuids;

    protected $fillable = ['name', 'slug', 'description', 'is_active', 'site_id'];
}
```

---

## Verification

```bash
# Проверить что роуты зарегистрированы
php artisan route:list | grep widget

# Статический анализ
./vendor/bin/phpstan analyse module/Widgets --memory-limit=512M --error-format=table

# Code style
./vendor/bin/pint module/Widgets
```

---

## Decision Table

| Ситуация | Что делать |
|---|---|
| Модуль только API, без страниц | Только `routes/api.php`, не создавай `routes/web.php` |
| Модуль разделяет категории с другим | Используй `Categories` модуль — наследуй `CategoryController` |
| Нужен artisan-command | Добавь в `ServiceProvider::register()`: `$this->commands([MyCommand::class])` |
| Нужна обработка события из другого модуля | `Event::listen(SomeEvent::class, MyListener::class)` в `boot()` |
| Модуль зависит от другого | Инжекти сервисы через конструктор — не импортируй провайдеры напрямую |
| Нужен singleton в контейнере | `$this->app->singleton(MyGateway::class)` в `register()` |

## References

See [references/add-new-module-checklist.md](references/add-new-module-checklist.md) for the quick checklist.
