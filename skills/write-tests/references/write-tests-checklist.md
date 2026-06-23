# Write Tests — Quick Checklist

## Структура файла

- [ ] `namespace Tests\Feature\<Module>\Http`
- [ ] `final class <Resource>ControllerTest extends TestCase`
- [ ] `use RefreshDatabase`
- [ ] `setUp()` с `forgetCachedPermissions()` (если используются permissions)

## Каждый HTTP-метод

### GET (list)
- [ ] 200 со своими данными (правильное количество)
- [ ] Изоляция — данные другого проекта не попадают
- [ ] 403 без нужного права
- [ ] 401 без авторизации

### GET (show)
- [ ] 200 с атрибутами (`assertJsonPath('data.attributes.name', ...)`)
- [ ] 404 при несуществующем UUID

### POST (store)
- [ ] 201 + `assertDatabaseHas(...)`
- [ ] 422 при пустом теле
- [ ] 422 при нарушении unique-правила
- [ ] 403 без права

### PUT/PATCH (update)
- [ ] 200 + изменение в БД
- [ ] 422 при невалидных данных

### DELETE (destroy)
- [ ] 204 + `assertDatabaseMissing(...)`
- [ ] 403 без права

## Правила

- [ ] Модели через `Model::query()->create([...])`, не фабрики
- [ ] Permissions через `Permission::firstOrCreate(['name' => ..., 'guard_name' => 'web'])`
- [ ] `$user->givePermissionTo(...)` после создания permission
- [ ] Helper-методы: `makeUserWithProject()`, `makeResource()`
- [ ] Именование: `test_<subject>_<result>_<condition>`
