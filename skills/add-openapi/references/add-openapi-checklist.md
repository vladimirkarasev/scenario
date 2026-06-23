# Add OpenAPI — Quick Checklist

- [ ] `docs/openapi/components/schemas/<resource>.yaml` создан
  - [ ] Схема ресурса: `id` (uuid), `type` (string), `attributes` ($ref)
  - [ ] `<Resource>Attributes` — все поля с типами и `nullable`
  - [ ] `<Resource>Request` — только поля для записи, с `required`
- [ ] `docs/openapi/paths/<resource>.yaml` создан
  - [ ] `collection` — GET (list) + POST
  - [ ] `item` — параметр `{resource}` + GET, PUT, DELETE
  - [ ] Все операции имеют `tags`, `summary`, `security`
  - [ ] Стандартные ответы через `$ref: ../components/responses.yaml#/...`
- [ ] В `docs/openapi/openapi.yaml`:
  - [ ] Добавлен tag в `tags:`
  - [ ] Добавлен tag в нужную группу `x-tagGroups:`
  - [ ] Добавлены пути в `paths:`
- [ ] `npm run openapi:bundle` — сборка прошла без ошибок
- [ ] `public/openapi.yaml` закоммичен вместе с исходниками
