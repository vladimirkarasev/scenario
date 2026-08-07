---
name: types-organization
description: TypeScript types must live in each module types directory, never inside repositories. Repositories contain only HTTP logic and normalization. Apply when adding new entities, a new repository, or moving types around.
---

# Types Organization

Публичные TypeScript-типы живут в `resources/js/modules/<module>/types/<entity>.ts`, а не внутри репозиториев.

**Why:** Так удобнее читать — сразу видно, откуда что берётся. Репозитории содержат только HTTP-логику и нормализацию (Raw-DTO для парсинга ответа можно оставить как **приватный** тип внутри `<entity>Repository.ts`, но публичный домен-тип — в `types/`).

**Структура модуля:**
```
resources/js/modules/<module>/
  types/<entity>.ts        ← публичные типы: Entity, EntityPayload, EntitiesPage…
  repositories/<entity>Repository.ts   ← HTTP + normalize(raw): Entity
  composables/use<Entity>*.ts
  schemas/<entity>Schema.ts ← zod-схемы (см. skill form-validation)
```

**How to apply:**

При создании новой сущности:

1. Создай `modules/<module>/types/<entity>.ts` с интерфейсами `Entity`, `EntityPayload`, `EntityPage` и т.п.
2. Импортируй их и в репозиторий, и в страницы/композаблы отдельно — не реэкспортируй типы из репозитория.
3. `Raw<Entity>` (форма ответа API до нормализации) — приватный тип внутри репозитория. Можно оставить там как `interface RawEntity { … }` без экспорта.
4. Zod-формы (см. [[form-validation]]) живут в `schemas/`, тип формы — `z.infer<typeof xxxSchema>`. Не дублируй его в `types/`.
5. Не делай barrel-реэкспорт domain types из repository: потребитель импортирует тип из `types/`, а repository — из `repositories/`.
6. После переноса проверь, что repositories не экспортируют публичные `interface`/`type`:

```bash
rg -n "export (interface|type)" resources/js/modules/*/repositories
```

Приватные wire DTO допустимы только без `export`. Если один wire DTO используется несколькими repositories, вынеси его в `types/<entity>Api.ts`, не смешивая с domain model.

**Анти-паттерн:**

```ts
// ❌ Не делай так — Entity внутри репозитория
// repositories/entityRepository.ts
export interface Entity { id: string; … }
export const entityRepository = { … }
```

```ts
// ✅ Правильно
// types/entity.ts
export interface Entity { id: string; … }
export interface EntityPayload { … }

// repositories/entityRepository.ts
import type { Entity, EntityPayload } from '@/modules/<module>/types/entity'
interface RawEntity { id: string; attributes: { … } }  // приватный
function normalize(raw: RawEntity): Entity { … }
export const entityRepository = { … }
```

**Связанные:** [[jsonapi-conventions]] — `Raw<Entity>` обычно имеет вид JSON:API `{ id, type, attributes, relationships }`.
