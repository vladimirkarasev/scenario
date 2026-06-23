# Add New Page — Quick Checklist

## TypeScript

- [ ] `modules/<resource>/types/<resource>.ts` — интерфейсы `Resource`, `ResourcesPage`, `ResourcePayload`
- [ ] Использует `PaginationMeta` из `@/types/pagination`

## Репозиторий

- [ ] `modules/<resource>/repositories/<resource>Repository.ts`
- [ ] `RawXxx` интерфейс для ответа API + `normalize()` функция
- [ ] Методы: `list(qs)`, `find(id)`, `create(payload)`, `update(id, payload)`, `remove(id)`
- [ ] Использует `getJson`, `sendJson`, `destroyJson` из `@/lib/http`
- [ ] `list()` принимает `URLSearchParams`, не объект

## Composables

- [ ] `useXxxList.ts` — `search`, `page`, `loading`, `items`, `meta`, `load()`
- [ ] `useXxxModal.ts` — `showModal`, `editing`, `form`, `openCreate`, `openEdit`, `save`, `doDelete`
- [ ] URL-параметры через `filter[search]`, `page[number]`, `page[size]`

## Page component

- [ ] `Pages/<Module>/Index.vue`
- [ ] Только `<script setup lang="ts">`, никаких Options API
- [ ] Вся логика — в composables, не inline
- [ ] `AppShell` + `PageHeader` + `ListPagination` + `EmptyState`
- [ ] Shadcn-vue компоненты вместо нативных

## Backend

- [ ] Контроллер возвращает `Inertia::render('Module/Index')`
- [ ] Роут в `module/<Module>/routes/web.php`
- [ ] ServiceProvider регистрирует web.php через `Route::middleware('web')`
