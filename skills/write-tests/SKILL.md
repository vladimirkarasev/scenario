---
name: write-tests
description: Write Feature and Unit tests following project conventions — RefreshDatabase, Model::query()->create() instead of factories, actingAs, assertJsonPath, permission setup. Use when asked to add tests, cover a controller, or verify API behaviour.
---

# Write Tests

## Overview

```
tests/
  Feature/
    <Module>/
      Http/
        <Resource>ControllerTest.php  ← HTTP-тесты (auth, статусы, данные)
  Unit/
    ExampleTest.php
```

Тесты Feature используют SQLite in-memory (`DB_DATABASE=:memory:`).
Фабрик нет — модели создаются через `Model::query()->create([...])`.

---

## Запуск

```bash
# Все тесты
task test

# Только backend Unit
task test:unit

# Только backend Feature
task test:feature

# Конкретный класс
task test:unit -- --filter=UserGroupsControllerTest

# Конкретный метод
task test:unit -- --filter=UserGroupsControllerTest::test_index_returns_groups_for_current_project

# Параллельно
task test -- --parallel

# Frontend Unit
task test:frontend

# Конкретный frontend test-файл
task test:frontend -- resources/js/modules/example/__tests__/example.test.ts
```

Всегда запускай проверки через цели `Taskfile.yml`. Дополнительные аргументы передавай после `--`.

---

## Скелет Feature-теста

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Groups\Http;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Module\Groups\Models\UserGroup;
use Module\Projects\Models\Project;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

final class UserGroupsControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Обязательно при работе с Spatie Permission
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    // ── GET /api/groups ─────────────────────────────────────────────────

    public function test_index_returns_groups_for_current_project(): void
    {
        [$user, $project] = $this->makeUserWithProject('group_view');
        $this->makeGroup($project);
        $this->makeGroup($project);

        $this->actingAs($user)
            ->getJson('/api/groups')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_index_returns_403_without_permission(): void
    {
        [$user] = $this->makeUserWithProject(); // без прав

        $this->actingAs($user)
            ->getJson('/api/groups')
            ->assertForbidden();
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/groups')
            ->assertUnauthorized();
    }

    // ── POST /api/groups ────────────────────────────────────────────────

    public function test_store_creates_group_and_returns_201(): void
    {
        [$user, $project] = $this->makeUserWithProject('group_create');

        $this->actingAs($user)
            ->postJson('/api/groups', [
                'name'      => 'Новая группа',
                'slug'      => 'new-group',
                'is_active' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.attributes.name', 'Новая группа');

        $this->assertDatabaseHas('user_groups', [
            'name'    => 'Новая группа',
            'slug'    => 'new-group',
            'site_id' => $project->id,
        ]);
    }

    public function test_store_returns_422_when_required_fields_missing(): void
    {
        [$user] = $this->makeUserWithProject('group_create');

        $this->actingAs($user)
            ->postJson('/api/groups', [])
            ->assertUnprocessable();
    }

    // ── DELETE /api/groups/{group} ───────────────────────────────────────

    public function test_destroy_deletes_group_and_returns_204(): void
    {
        [$user, $project] = $this->makeUserWithProject('group_delete');
        $group = $this->makeGroup($project);

        $this->actingAs($user)
            ->deleteJson("/api/groups/{$group->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('user_groups', ['id' => $group->id]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    /** @return array{User, Project} */
    private function makeUserWithProject(string ...$permissions): array
    {
        $project = $this->makeProject();
        $user    = User::factory()->create([
            'sitekey' => $project->sitekey,
            'host'    => $project->host,
        ]);

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
            $user->givePermissionTo($permission);
        }

        return [$user, $project];
    }

    private function makeProject(): Project
    {
        return Project::query()->create([
            'name'          => 'Project ' . Str::random(4),
            'sitekey'       => 'sk-' . Str::random(6),
            'host'          => Str::random(4) . '.local',
            'shared_secret' => Str::random(32),
            'is_active'     => true,
        ]);
    }

    private function makeGroup(Project $project, string $name = 'Test Group', string $slug = ''): UserGroup
    {
        return UserGroup::query()->create([
            'name'      => $name,
            'slug'      => $slug ?: 'group-' . Str::random(6),
            'site_id'   => $project->id,
            'is_active' => true,
        ]);
    }
}
```

---

## Ключевые правила

### Нет фабрик — только `Model::query()->create()`

```php
// Bad — фабрики не определены
$user = User::factory()->create();

// OK — User::factory() работает (User из app/), но остальные модели — только create()
$group = UserGroup::query()->create([...]);
$project = Project::query()->create([...]);
```

### Permissions — через `Permission::firstOrCreate`

```php
// В setUp() или в helper-методе makeUserWithProject()
app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

Permission::firstOrCreate(['name' => 'group_view', 'guard_name' => 'web']);
$user->givePermissionTo('group_view');
```

Никогда не запускай seeders в тестах — создавай только нужные permissions вручную.

### `assertJsonPath` для JSON:API

```php
// Проверка конкретного поля в атрибутах
->assertJsonPath('data.attributes.name', 'Группа продаж')
->assertJsonPath('data.id', $group->id)
->assertJsonPath('data.type', 'groups')

// Проверка количества элементов в коллекции
->assertJsonCount(3, 'data')

// Проверка наличия ключа в meta
->assertJsonStructure(['data', 'meta' => ['current_page', 'last_page', 'per_page', 'total']])
```

### Что тестировать для каждого метода

| Метод | Обязательные кейсы |
|---|---|
| GET (list) | 200 со своими данными; изоляция от чужих данных; 403 без права; 401 без авторизации |
| GET (show) | 200 с атрибутами; 404 при несуществующем UUID; 403 без права |
| POST (store) | 201 + запись в БД; 422 при пустом теле; 422 при дубликате unique-поля; 403 без права |
| PUT/PATCH | 200 + изменение в БД; 422 при невалидных данных; 403 без права |
| DELETE | 204 + отсутствие записи в БД; 403 без права |

### Именование методов

```
test_<subject>_<expected_result>_<condition>

test_index_returns_groups_for_current_project
test_index_excludes_groups_from_other_project
test_store_returns_422_when_slug_not_unique
test_destroy_returns_403_without_permission
```

---

## Организация файла

```php
final class UserGroupsControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void { ... }

    // ── GET /api/groups ───────────────────── (комментарий-разделитель)
    public function test_index_...(): void { ... }

    // ── POST /api/groups ──────────────────────
    public function test_store_...(): void { ... }

    // ── Helpers ───────────────────────────────
    private function makeUserWithProject(...): array { ... }
    private function makeGroup(...): UserGroup { ... }
}
```

- Разделяй блоки методов комментариями с HTTP-методом и путём
- Helpers — в конце, всегда `private`
- Один класс = один контроллер

---

## Decision Table

| Ситуация | Что делать |
|---|---|
| Нужен пользователь с ролью | `$user->syncRoles([Role::firstOrCreate(...)])` |
| Нужен проект для изоляции | `makeProject()` helper, привязывай `sitekey` к User |
| Тест падает из-за Permission cache | `forgetCachedPermissions()` в `setUp()` |
| Проверить вложенные данные relationships | `->assertJsonPath('data.relationships.created_by.data.id', ...)` |
| Нужно загрузить файл | `$this->actingAs($user)->postJson(..., ['file' => UploadedFile::fake()->create('data.csv', 100)])` |
| Тест для публичного эндпоинта (без auth) | Не вызывай `actingAs()` — тест пойдёт как гость |
| Нужно проверить событие/job | `Event::fake()` / `Queue::fake()` перед вызовом |
| Ресурс принадлежит другому проекту | Создай второй проект с другим `sitekey`, убедись что 404 или пустая коллекция |

## References

See [references/write-tests-checklist.md](references/write-tests-checklist.md) for the quick checklist.
