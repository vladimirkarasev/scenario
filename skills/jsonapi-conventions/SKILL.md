---
name: jsonapi-conventions
description: All API endpoints must follow JSON:API conventions. Use filter[...] for filtering, sort for sorting, include for relationships, fields[...] for sparse fieldsets, and page[number]/page[size] for pagination. Frontend repositories accept URLSearchParams directly without intermediate query objects. Apply when creating or modifying API endpoints, repositories, filters, tables, or list pages.
------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

# JSON:API Conventions

## Общие правила

Все REST API в проекте должны соответствовать спецификации JSON:API.

Используем следующие соглашения:

* Фильтрация через `filter[...]`
* Сортировка через `sort`
* Включение связей через `include`
* Ограничение полей через `fields[...]`
* Пагинация через `page[number]` и `page[size]`
* Ответы содержат `data`, `meta`, `links`
* Ошибки возвращаются через `errors`
* Frontend работает напрямую с `URLSearchParams`

Не допускается создание собственных форматов запросов для отдельных модулей.

---

# Query Parameters

## Поиск

```http
GET /api/users?filter[search]=ivan
```

```php
$search = $request->input('filter.search');
```

---

## Простые фильтры

```http
GET /api/users?filter[is_active]=1
```

```php
$isActive = $request->input('filter.is_active');
```

---

## Множественные фильтры

```http
GET /api/users?filter[group_ids][]=1&filter[group_ids][]=2
```

```php
$groupIds = $request->array('filter.group_ids');
```

---

## Вложенные фильтры

```http
GET /api/users?filter[company][id]=5
```

```php
$companyId = $request->input('filter.company.id');
```

---

## Сортировка

По возрастанию:

```http
GET /api/users?sort=name
```

По убыванию:

```http
GET /api/users?sort=-created_at
```

Несколько полей:

```http
GET /api/users?sort=name,-created_at
```

Backend обязан поддерживать множественную сортировку.

---

## Include

Загрузка связанных сущностей.

```http
GET /api/users?include=roles
```

Несколько связей:

```http
GET /api/users?include=roles,groups
```

Вложенные связи:

```http
GET /api/users?include=roles.permissions
```

---

## Sparse Fieldsets

Запрос только нужных полей.

```http
GET /api/users?fields[users]=id,name,email
```

Для нескольких типов:

```http
GET /api/users?fields[users]=id,name&fields[roles]=id,title
```

---

## Pagination

Первая страница:

```http
GET /api/users?page[number]=1&page[size]=15
```

Вторая страница:

```http
GET /api/users?page[number]=2&page[size]=15
```

Backend обязан задавать безопасные ограничения на максимальный размер страницы.

Пример:

```php
$pageNumber = max(1, (int) $request->input('page.number', 1));
$pageSize = min(
    100,
    max(1, (int) $request->input('page.size', 15))
);
```

---

# Запрещённые параметры

Нельзя использовать:

```http
?search=ivan
?page=2
?per_page=20
?group_ids[]=1
?order=name
?direction=asc
```

Допустимо только:

```http
?filter[search]=ivan
?page[number]=2
&page[size]=20
&filter[group_ids][]=1
&sort=name
```

---

# Backend (Laravel)

Получение параметров:

```php
$filters = $request->array('filter');

$search = $filters['search'] ?? null;
$isActive = $filters['is_active'] ?? null;
$groupIds = $filters['group_ids'] ?? [];

$pageNumber = (int) $request->input('page.number', 1);
$pageSize = (int) $request->input('page.size', 15);

$sort = $request->input('sort');
$include = explode(',', $request->input('include', ''));
```

---

# Формат ответа

Минимальный JSON:API ответ:

```json
{
  "data": []
}
```

Коллекция:

```json
{
  "data": [],
  "meta": {
    "total": 42,
    "page": {
      "number": 1,
      "size": 15,
      "last": 3
    }
  },
  "links": {
    "self": "...",
    "first": "...",
    "prev": null,
    "next": "...",
    "last": "..."
  }
}
```

Ресурс:

```json
{
  "data": {
    "type": "users",
    "id": "1",
    "attributes": {
      "name": "Ivan"
    }
  }
}
```

---

# Relationships

```json
{
  "data": {
    "type": "users",
    "id": "1",
    "attributes": {
      "name": "Ivan"
    },
    "relationships": {
      "roles": {
        "data": [
          {
            "type": "roles",
            "id": "5"
          }
        ]
      }
    }
  }
}
```

---

# Included

```json
{
  "data": [
    ...
  ],
  "included": [
    {
      "type": "roles",
      "id": "5",
      "attributes": {
        "name": "Admin"
      }
    }
  ]
}
```

Используется только при наличии параметра `include`.

---

# Формат ошибок

```json
{
  "errors": [
    {
      "status": "422",
      "title": "Validation Error",
      "detail": "The name field is required."
    }
  ]
}
```

Для ошибок валидации:

```json
{
  "errors": [
    {
      "status": "422",
      "source": {
        "pointer": "/data/attributes/name"
      },
      "detail": "The name field is required."
    }
  ]
}
```

---

# Frontend Repository

Репозитории работают напрямую с URLSearchParams.

Правильно:

```ts
async
list(qs
:
URLSearchParams
):
Promise < UsersPage > {
    return getJson(`/api/users?${qs}`)
}
```

Неправильно:

```ts
interface UserQuery {
    search?: string
    page?: number
    perPage?: number
}
```

и затем:

```ts
buildQuery(query)
```

URL уже является контрактом API.

Не создавай промежуточные DTO для query-параметров.

---

# Frontend Composable

Composable отвечает за формирование URLSearchParams.

```ts
const qs = new URLSearchParams({
    'page[number]': String(page.value),
    'page[size]': '15',
})

if (search.value) {
    qs.set('filter[search]', search.value)
}

for (const id of selectedGroups.value) {
    qs.append('filter[group_ids][]', id)
}
```

---

# Synchronization With URL

Если экран поддерживает deep-linking:

```ts
const qs = new URLSearchParams(window.location.search)
```

Состояние фильтров должно восстанавливаться из URL.

URL считается источником истины для списка.

---

# Resource Naming

Используем множественное число:

```json
{
  "type": "users"
}
```

Не использовать:

```json
{
  "type": "user"
}
```

---

# API Design Rules

Каждая коллекция должна поддерживать:

* filter
* sort
* page
* include

Если сущность имеет связи — поддерживать include.

Если таблица отображает сортировку — поддерживать sort.

Если таблица отображает фильтры — поддерживать filter.

---

# Existing Exceptions

## Scenario Runner

Текущий ответ:

```json
{
  "runs": [],
  "pagination": {},
  "stats": {}
}
```

Нужно перевести на JSON:API при следующем изменении:

```php
ScenarioRunController::index
```

---

# Related Rules

* [[types-organization]]
* [[form-validation]]
* [[repository-pattern]]
* [[api-resources]]
* resources/js/lib/http.ts
