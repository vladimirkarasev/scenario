---
name: add-seed
description: Add a database seeder — demo data, test users, permissions sync, or reference data. Use when asked to seed data, add demo content, create initial users/roles, or run seeders after migrations.
---

# Database Seeding

## Типы сидеров в проекте

| Сидер | Назначение | Запускать |
|---|---|---|
| `PermissionSeeder` | Синхронизирует все permission-ы из `PermissionRegistry` | После добавления нового permission-а |
| `RoleSeeder` | Создаёт роль `administrator` с полными правами | После `PermissionSeeder` |
| `DemoProjectSeeder` | Создаёт demo-проект | При первоначальной настройке |
| `AdminUserSeeder` | Создаёт пользователя `admin@scenario.local` / пароль `password` | При первоначальной настройке |
| `TestUsersSeeder` | Создаёт тестовых пользователей с ограниченными ролями | При первоначальной настройке |
| `DatabaseSeeder` | Запускает все сидеры по порядку | `php artisan db:seed` |

---

## Команды

```bash
# Все сидеры (DatabaseSeeder)
php artisan db:seed

# Конкретный сидер
php artisan db:seed --class=PermissionSeeder
php artisan db:seed --class=RoleSeeder

# Несколько сидеров по очереди
php artisan db:seed --class=PermissionSeeder && php artisan db:seed --class=RoleSeeder

# Migrate + seed (осторожно — сбрасывает данные)
php artisan migrate:fresh --seed

# В Docker
task artisan -- db:seed --class=PermissionSeeder
```

---

## Когда что запускать

### Добавил новый permission

```bash
php artisan db:seed --class=PermissionSeeder
php artisan db:seed --class=RoleSeeder  # чтобы administrator получил новый permission
```

### Добавил новый proxy-эндпоинт

```bash
php artisan proxies:sync
```

### Первоначальная настройка (composer run setup)

```bash
php artisan db:seed  # запускает всё через DatabaseSeeder
```

---

## Как написать сидер

### Справочные данные (idempotent)

Используй `updateOrCreate` / `firstOrCreate` — сидер должен быть безопасен для повторного запуска:

```php
<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Module\Widgets\Models\Widget;

final class WidgetSeeder extends Seeder
{
    public function run(): void
    {
        Widget::query()->updateOrCreate(
            ['slug' => 'default-widget'],
            [
                'name'      => 'Default Widget',
                'is_active' => true,
            ],
        );
    }
}
```

### Demo-данные с константами

```php
final class DemoProjectSeeder extends Seeder
{
    public const string SITEKEY = 'demo-site';
    public const string HOST    = 'parent.localhost';

    public function run(): void
    {
        Project::query()->updateOrCreate(
            ['sitekey' => self::SITEKEY, 'host' => self::HOST],
            [
                'id'            => '019e5d9f-86d8-7311-9f8b-fb1dfef34a71', // фиксированный UUID
                'name'          => 'Demo Site',
                'shared_secret' => 'demo-site-shared-secret-for-local-dev',
                'is_active'     => true,
            ],
        );
    }
}
```

Фиксированный UUID удобен — другие сидеры могут ссылаться на него без запроса.

### Тестовые пользователи с ролями

```php
final class TestUsersSeeder extends Seeder
{
    public function run(): void
    {
        $project = Project::query()->where('sitekey', DemoProjectSeeder::SITEKEY)->first();

        $role = $this->createRole('editor', 'Редактор', [
            WidgetPermission::View->value,
            WidgetPermission::Create->value,
        ]);

        $this->createUser('editor@example.local', 'editor', 'Editor', $role, $project);
    }

    private function createRole(string $name, string $title, array $permissions): Role
    {
        $role = Role::query()->updateOrCreate(
            ['name' => $name, 'guard_name' => 'web'],
            ['title' => $title, 'description' => '', 'is_system' => false],
        );

        $role->syncPermissions(
            Permission::whereIn('name', $permissions)->where('guard_name', 'web')->get(),
        );

        return $role;
    }

    private function createUser(string $email, string $login, string $name, Role $role, ?Project $project): void
    {
        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name'     => $name,
                'login'    => $login,
                'sitekey'  => DemoProjectSeeder::SITEKEY,
                'host'     => DemoProjectSeeder::HOST,
                'password' => Hash::make('password'),
            ],
        );

        $user->syncRoles([$role]);

        if ($project === null) {
            return;
        }

        DB::table('project_users')->insertOrIgnore([
            'user_id'    => $user->id,
            'project_id' => $project->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
```

---

## Регистрация в DatabaseSeeder

```php
// database/seeders/DatabaseSeeder.php
final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            DemoProjectSeeder::class,
            AdminUserSeeder::class,
            TestUsersSeeder::class,
            WidgetSeeder::class,  // ← добавить после зависимостей
        ]);
    }
}
```

Порядок важен: `PermissionSeeder` → `RoleSeeder` → `DemoProjectSeeder` → пользователи → данные.

---

## Правила

- Всегда `updateOrCreate` / `firstOrCreate` — никакого `create()` без проверки на дубликаты
- Никогда `truncate()` в сидерах — они запускаются на продовых данных
- Тестовые пароли — всегда `password`, никаких env-переменных в сидерах
- Фиксированные UUID для demo-данных — удобно для cross-seeder ссылок и тестов
- Перед `syncPermissions` нужно чтобы `PermissionSeeder` уже отработал

---

## Decision Table

| Ситуация | Что делать |
|---|---|
| Добавил permission → роли не видят его | Запусти `RoleSeeder` после `PermissionSeeder` |
| Нужны данные только для локальной разработки | Создавай в `TestUsersSeeder` или новом `DemoXxxSeeder` |
| Нужны данные в тестах | Создавай в `setUp()` через `Model::query()->create()` — не запускай сидеры |
| Сидер упал — нужно перезапустить с нуля | `php artisan migrate:fresh --seed` (только local!) |
| Нужно добавить пользователя в проект | `DB::table('project_users')->insertOrIgnore(...)` |
| Нужно назначить роль пользователю | `$user->syncRoles([$role])` — syncRoles безопаснее чем assignRole |

## References

See [references/add-seed-checklist.md](references/add-seed-checklist.md) for the quick checklist.
