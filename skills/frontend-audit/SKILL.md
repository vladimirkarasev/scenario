---
name: frontend-audit
description: "Проводить комплексный аудит и рефакторинг Vue 3 frontend: архитектура, repositories, отдельные Zod-схемы, гонки запросов, единый дизайн, безопасность, производительность и unit-тесты. Применять по запросам «аудит frontend», «оптимизировать frontend», «привести страницы к общему стилю», «исправить гонки», «вынести API/Zod», «аудит безопасности» или перед крупным frontend MR."
---

# Frontend Audit

Проводить аудит от измеримого baseline к небольшим проверяемым изменениям. Сохранять пользовательские изменения в dirty worktree и не расширять backend scope без необходимости.

Перед аудитом прочитать [references/checklist.md](references/checklist.md). Для правок конкретных областей дополнительно применять `vue-refactor`, `form-validation`, `types-organization`, `static-analysis`, `crud-toast` и `api-response-contract` по их trigger rules.

## 1. Зафиксировать baseline

Проверить структуру frontend, package scripts, текущий git diff и существующие архитектурные примеры. Найти эталонные страницы того же типа, прежде чем менять дизайн.

Запустить доступные проверки до изменений, чтобы отличать новые ошибки от существующих:

```bash
npm run test:run
npx vue-tsc --noEmit
npx eslint resources/js --quiet
npm run knip
npm audit --omit=dev
```

Не считать отсутствие одного script блокером: использовать эквивалентную команду из `package.json`.

## 2. Инвентаризировать риски

Искать и классифицировать находки:

1. Critical/high: XSS, утечки токенов, небезопасные URL, гонки, потеря данных, обход permission UI.
2. Medium: HTTP вне repositories, Zod внутри компонентов/composables, дублирование страниц, несогласованный дизайн, неограниченный импорт файлов.
3. Low: лишние render/reload, dead code, слабая типизация, повторяющаяся разметка.

Не внедрять паттерны GoF ради количества. Использовать паттерн только при наличии изменяемого поведения или устойчивой границы:

- Repository — HTTP и нормализация ответа.
- Strategy — выбор схемы, renderer или политики конкурентности.
- Adapter — преобразование внешнего API в доменный контракт.
- Facade — единая точка для сложной инфраструктуры.
- Factory — создание вариантов объектов с общей сигнатурой.

## 3. Соблюдать архитектурные границы

Поддерживать поток формы:

```text
schema -> useZodForm -> composable -> repository -> API
```

- Хранить Zod-схемы в `modules/<module>/schemas/`.
- Хранить публичные domain types в `modules/<module>/types/`.
- Оставлять в repository только HTTP, разбор API-конверта и нормализацию.
- Не вызывать HTTP из page, component, store или feature composable.
- Выносить повторяемую разметку и самостоятельное поведение в компоненты, но не создавать компонент-обёртку без повторного использования или смысловой границы.

## 4. Исправлять конкурентность по семантике операции

Для поисков, фильтров, пагинации и повторной загрузки использовать latest-started-wins через проектный `useLatestRequest`. Обновлять state только результатом, признанным актуальным политикой.

Debounce уменьшает число запросов, но не устраняет гонку.

Для create/update/delete и autosave не применять latest-wins: сериализовать mutations либо использовать очередь с явным coalescing. Abort допустим только если repository и transport корректно поддерживают `AbortSignal`.

При размонтировании инвалидировать ожидающие read-операции, если composable может обновить state после unmount.

## 5. Приводить дизайн к системе

Сравнивать изменяемую страницу с Users и Roles и переиспользовать общий визуальный словарь:

- `AppShell` и `PageHeader`;
- `app-page`, `app-page-container`, `app-panel`, `app-error`;
- `SearchInput`, `EmptyState`, `ListPagination`;
- module tabs как отдельный компонент для родственных страниц;
- цвета только через semantic tokens и CSS variables;
- actions показывать по permissions, а не только блокировать после клика.

Проверять light/dark, narrow viewport, loading, empty, error и populated states. Не копировать большие Tailwind-цепочки между соседними страницами, если уже есть системный класс или компонент.

## 6. Проверять безопасность

- Запрещать необработанный `v-html`; пропускать доверенный rich text через централизованный sanitizer.
- Для `target="_blank"` устанавливать `rel="noopener noreferrer"`.
- Не хранить secrets в `VITE_*`; считать такие значения публичными.
- Удалять одноразовые tokens из URL после чтения.
- Оценивать риск хранения access token в `localStorage`/`sessionStorage` и фиксировать его в отчёте, если архитектурная смена требует backend.
- Ограничивать размер, количество строк и форму импортируемых файлов до тяжёлого parsing.
- Загружать тяжёлые parsers динамически, если они нужны только пользовательскому действию.
- Не запускать `npm audit fix --force` автоматически. Обновлять прямую зависимость безопасно и перечислять оставшиеся transitive/no-fix риски.

## 7. Добавлять unit-тесты

Если пользователь просит только unit, не добавлять E2E и Feature tests.

Покрывать чистые и критичные границы:

- Zod schemas и error mapping;
- policies конкурентности;
- repository normalization и API envelope parsing;
- sanitization и URL/token helpers;
- import limits и преобразования данных;
- composable state transitions с mocked repository.

Не тестировать детали Tailwind-классов, если они не выражают контракт состояния или доступности.

## 8. Завершать аудит доказательствами

После изменений запустить unit tests, TypeScript, ESLint, production build и релевантный security audit. При frontend-изменениях исправить все ошибки TypeScript согласно `typescript-fix-policy`.

В итоговом отчёте разделить:

- исправлено;
- проверено командами;
- осталось и почему;
- риски, требующие backend/product решения.

Не объявлять аудит безопасности «полностью безопасным»: формулировать проверенный scope и остаточный риск.
