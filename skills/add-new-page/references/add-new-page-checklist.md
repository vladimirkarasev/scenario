# Add New Page — Quick Checklist

## TypeScript

- [ ] `modules/<resource>/types/<resource>.ts` — интерфейсы `Resource`, `ResourcesPage`, `ResourcePayload`
- [ ] Использует `PaginationMeta` из `@/types/pagination`
- [ ] Zod-схема и inferred form type находятся в `modules/<resource>/schemas/`

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
- [ ] Конкурирующие reads защищены `useLatestRequest`; mutations не используют latest-wins
- [ ] Ошибка загрузки доступна странице, pending read инвалидируется при dispose

## Page component

- [ ] `Pages/<Module>/Index.vue`
- [ ] Только `<script setup lang="ts">`, никаких Options API
- [ ] Вся логика — в composables, не inline
- [ ] `AppShell` + `PageHeader` + `ListPagination` + `EmptyState`
- [ ] `app-page` + `app-page-container` + `app-panel` + `app-error`
- [ ] Родственные страницы используют общий tabs-компонент и semantic color tokens
- [ ] Actions скрываются/показываются по permissions
- [ ] Shadcn-vue компоненты вместо нативных

## Unit tests и безопасность

- [ ] Unit-тесты покрывают schema, repository normalization и race policy/composable state
- [ ] Нет необработанного `v-html`; внешние ссылки используют `noopener noreferrer`
- [ ] Файловый импорт имеет limits до parsing, если он есть
- [ ] Выполнены `vue-tsc`, ESLint, unit tests и production build

## Backend

- [ ] Контроллер возвращает `Inertia::render('Module/Index')`
- [ ] Роут в `module/<Module>/routes/web.php`
- [ ] ServiceProvider регистрирует web.php через `Route::middleware('web')`
