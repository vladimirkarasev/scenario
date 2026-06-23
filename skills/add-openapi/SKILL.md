---
name: add-openapi
description: Document a new API endpoint in the OpenAPI spec — paths file, schema file, registration in openapi.yaml, bundle command. Use when asked to add API docs, document a new resource, or update the OpenAPI spec.
---

# Add OpenAPI Documentation

## File layout

```
docs/openapi/
  openapi.yaml                      ← главный файл (только paths $ref и tags)
  paths/
    <resource>.yaml                 ← описание операций (get/post/put/delete)
  components/
    schemas/
      <resource>.yaml               ← схемы (ресурс, атрибуты, request body)
    parameters.yaml                 ← переиспользуемые параметры (PerPage, и др.)
    responses.yaml                  ← стандартные ответы (401, 404, 422)
    security-schemes.yaml

public/openapi.yaml                 ← сгенерированный бандл (не редактировать руками)
```

Редактируй только файлы в `docs/openapi/`. Бандл генерируется командой:

```bash
npm run openapi:bundle
```

---

## Step 1 — Схема ресурса

Создай `docs/openapi/components/schemas/<resource>.yaml`:

```yaml
# docs/openapi/components/schemas/widgets.yaml

Widget:
  type: object
  required: [id, type, attributes]
  properties:
    id:
      type: string
      format: uuid
    type:
      type: string
      example: widgets
    attributes:
      $ref: '#/WidgetAttributes'

WidgetAttributes:
  type: object
  properties:
    name:
      type: string
      example: Мой виджет
    slug:
      type: string
      example: my-widget
    description:
      type: string
      nullable: true
    is_active:
      type: boolean
    created_at:
      type: string
      format: date-time
      nullable: true
    updated_at:
      type: string
      format: date-time
      nullable: true

WidgetRequest:
  type: object
  required: [name, slug]
  properties:
    name:
      type: string
      maxLength: 255
      example: Мой виджет
    slug:
      type: string
      maxLength: 255
      example: my-widget
    description:
      type: string
      nullable: true
    is_active:
      type: boolean
      example: true
```

---

## Step 2 — Paths файл

Создай `docs/openapi/paths/<resource>.yaml`:

```yaml
# docs/openapi/paths/widgets.yaml

collection:
  get:
    tags: [Виджеты]
    summary: Список виджетов
    security:
      - BearerAuth: []
    parameters:
      - name: filter[search]
        in: query
        description: Поиск по названию
        schema:
          type: string
      - name: filter[is_active]
        in: query
        schema:
          type: boolean
      - $ref: '../components/parameters.yaml#/PageNumber'
      - $ref: '../components/parameters.yaml#/PageSize'
    responses:
      '200':
        description: Список виджетов (JSON:API коллекция)
        content:
          application/json:
            schema:
              allOf:
                - $ref: '../components/schemas/common.yaml#/JsonApiCollection'
                - type: object
                  properties:
                    data:
                      type: array
                      items:
                        $ref: '../components/schemas/widgets.yaml#/Widget'
      '401':
        $ref: '../components/responses.yaml#/Unauthorized'

  post:
    tags: [Виджеты]
    summary: Создать виджет
    security:
      - BearerAuth: []
    requestBody:
      required: true
      content:
        application/json:
          schema:
            $ref: '../components/schemas/widgets.yaml#/WidgetRequest'
    responses:
      '201':
        description: Виджет создан
        content:
          application/json:
            schema:
              $ref: '../components/schemas/widgets.yaml#/Widget'
      '401':
        $ref: '../components/responses.yaml#/Unauthorized'
      '422':
        $ref: '../components/responses.yaml#/ValidationError'

item:
  parameters:
    - name: widget
      in: path
      required: true
      description: UUID виджета
      schema:
        type: string
        format: uuid

  get:
    tags: [Виджеты]
    summary: Получить виджет
    security:
      - BearerAuth: []
    responses:
      '200':
        description: Виджет
        content:
          application/json:
            schema:
              $ref: '../components/schemas/widgets.yaml#/Widget'
      '401':
        $ref: '../components/responses.yaml#/Unauthorized'
      '404':
        $ref: '../components/responses.yaml#/NotFound'

  put:
    tags: [Виджеты]
    summary: Обновить виджет
    security:
      - BearerAuth: []
    requestBody:
      required: true
      content:
        application/json:
          schema:
            $ref: '../components/schemas/widgets.yaml#/WidgetRequest'
    responses:
      '200':
        description: Обновлённый виджет
        content:
          application/json:
            schema:
              $ref: '../components/schemas/widgets.yaml#/Widget'
      '401':
        $ref: '../components/responses.yaml#/Unauthorized'
      '404':
        $ref: '../components/responses.yaml#/NotFound'
      '422':
        $ref: '../components/responses.yaml#/ValidationError'

  delete:
    tags: [Виджеты]
    summary: Удалить виджет
    security:
      - BearerAuth: []
    responses:
      '204':
        description: Виджет удалён
      '401':
        $ref: '../components/responses.yaml#/Unauthorized'
      '404':
        $ref: '../components/responses.yaml#/NotFound'
```

---

## Step 3 — Регистрация в openapi.yaml

Добавь tag и paths:

```yaml
# docs/openapi/openapi.yaml

tags:
  # ... существующие ...
  - name: Виджеты
    description: Управление виджетами.

x-tagGroups:
  - name: Администрирование     # или подходящая группа
    tags:
      # ... существующие ...
      - Виджеты                 # ← добавить

paths:
  # ... существующие ...
  /widgets:
    $ref: './paths/widgets.yaml#/collection'
  /widgets/{widget}:
    $ref: './paths/widgets.yaml#/item'
```

---

## Step 4 — Сборка

```bash
npm run openapi:bundle
```

Собирает `docs/openapi/openapi.yaml` в единый `public/openapi.yaml`. Закоммить оба файла.

---

## Переиспользуемые компоненты

### Стандартные responses (уже определены в components/responses.yaml)

```yaml
$ref: '../components/responses.yaml#/Unauthorized'   # 401
$ref: '../components/responses.yaml#/NotFound'        # 404
$ref: '../components/responses.yaml#/ValidationError' # 422
```

### Стандартные schemas (common.yaml)

```yaml
$ref: '../components/schemas/common.yaml#/JsonApiCollection'      # обёртка коллекции
$ref: '../components/schemas/common.yaml#/JsonApiResourceEnvelope' # обёртка одного ресурса
$ref: '../components/schemas/common.yaml#/Pagination'              # мета-пагинация
$ref: '../components/schemas/common.yaml#/Actor'                   # пользователь (created_by)
```

---

## Decision Table

| Ситуация | Что делать |
|---|---|
| Эндпоинт только GET (read-only) | Создай только `collection.get` и `item.get` в paths |
| Ресурс без UUID (integer id) | `schema: { type: integer }` в path parameter |
| Эндпоинт принимает файл | `requestBody: content: multipart/form-data: ...` |
| Нужно показать пример ответа | Добавь `example:` внутрь `schema` или `content` |
| Эндпоинт публичный (без auth) | Не добавляй `security:` или добавь `security: []` |
| Relationships в ответе | Добавь `relationships:` в схему ресурса по аналогии с `groups.yaml#/UserGroup` |

## References

See [references/add-openapi-checklist.md](references/add-openapi-checklist.md) for the quick checklist.
