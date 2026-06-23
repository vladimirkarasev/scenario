# API-соглашения

В проекте используются соглашения [JSON:API](https://jsonapi.org/) для HTTP-эндпоинтов.

## Фильтрация

Параметры фильтрации всегда передаются в неймспейсе `filter[...]`:

```
GET /api/users?filter[search]=alice&filter[group_ids][]=3&filter[role_ids][]=1
GET /api/scenarios?filter[search]=test&filter[is_active]=true&filter[tag]=onboarding
```

На бэкенде DTO читают фильтры через:

```php
$filter = $request->array('filter');
$search   = $filter['search']    ?? null;
$groupIds = $filter['group_ids'] ?? [];
```

Плоские query-параметры вида `?search=alice` или `?group_ids[]=3` на API-эндпоинтах не используются.

## Пагинация

Параметры пагинации передаются в неймспейсе `page[...]`:

| Параметр       | Описание               | По умолчанию |
|----------------|------------------------|--------------|
| `page[number]` | Номер текущей страницы | 1            |
| `page[size]`   | Элементов на странице  | зависит от эндпоинта |

```
GET /api/users?page[number]=2&page[size]=20
```

На бэкенде размер страницы читается через:

```php
$perPage = (int) $request->input('page.size', 20);
```

Laravel paginator настраивается с именем страницы `page[number]`:

```php
->paginate($perPage, ['*'], 'page[number]')
```

## Формат ответа

Одиночный ресурс:

```json
{
  "data": {
    "id": "1",
    "type": "users",
    "attributes": { "name": "Alice", "email": "alice@example.com" }
  }
}
```

Коллекция с пагинацией:

```json
{
  "data": [ ... ],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 20,
    "total": 98
  }
}
```

## Репозитории на фронтенде

Репозитории принимают `URLSearchParams` напрямую — без промежуточных query-объектов:

```ts
// репозиторий
async list(qs: URLSearchParams): Promise<UsersPage> {
  const raw = await getJson(`/api/users?${qs}`, '...')
  return { data: raw.data.map(normalize), meta: raw.meta }
}

// composable — строит qs из window.location.search, добавляет дефолты
async function load() {
  const qs = new URLSearchParams(window.location.search)
  qs.set('per_page', '15')
  if (!qs.has('page[number]')) qs.set('page[number]', '1')
  const result = await userRepository.list(qs)
}
```

URL-параметры используют те же JSON:API-ключи, что и API — переименования не нужны.
