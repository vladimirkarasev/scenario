# Аудит frontend

Дата: 2026-08-06

## Итог

Frontend переведён к целевой структуре `schema → composable → repository`, добавлена единая политика конкурентных запросов, усилен вывод rich text и унифицирован базовый визуальный каркас. Статическая типизация, ESLint без ошибок, unit-тесты и production build проходят.

## Исправлено

### Архитектура

- HTTP-вызовы из `app.ts`, auth store, Centrifugo и импорта справочников перенесены в repositories.
- Публичные типы action feed перенесены из repository в `modules/actions/types/`.
- Динамическая Zod-схема блок-формы перенесена из composable в `modules/scenario/schemas/`.
- Форма proxy connection переведена с ручной проверки на отдельную Zod-схему и `useZodForm`.
- Повторяющееся поле ввода с action-кнопкой выделено в `FormInputAction`.
- Для раздела Proxy добавлен переиспользуемый `ProxyTabs`.

### Гонки и асинхронность

- Добавлена стратегия `LastStartedWinsPolicy` и facade `useLatestRequest`.
- Защита применена к спискам проектов, справочников, сценариев, actions, proxy и webhook-запросов.
- Автосохранение настроек импорта сериализовано: промежуточные состояния объединяются, PATCH-запросы не могут перезаписать более новое состояние в обратном порядке.
- Активные запросы инвалидируются при уничтожении Vue scope.

### Безопасность

- Rich text проходит allowlist-санитизацию перед `v-html`; опасные протоколы, элементы и атрибуты удаляются.
- Для ссылок с `target="_blank"` добавлен `rel="noopener noreferrer"`.
- Удалена внешняя загрузка Google Fonts.
- `axios` обновлён с `1.14.0` до `1.19.0`, закрывающей найденные npm audit уязвимости пакета и его HTTP-зависимостей.
- Для spreadsheet preview добавлены лимиты 10 МБ, 200 колонок и 20 строк; `xlsx` загружается динамически только при разборе файла.

### Дизайн

- Общие поверхности оформлены через `app-page`, `app-page-container`, `app-panel`, `app-error`.
- `AppShell`, `PageHeader`, `PageContent`, `EmptyState` типизированы и используют общие theme tokens.
- Навигация и системные подписи приведены к русскому языку.
- Projects и Proxy Connections приведены к шаблону Users/Roles: `max-w-6xl`, KPI-карточки, поиск/табы, общая поверхность списка и одинаковая primary-кнопка.

## Оставшиеся риски

| Приоритет | Риск | Рекомендация |
|---|---|---|
| Высокий | `xlsx@0.18.5` имеет известные prototype pollution/ReDoS и не имеет исправления в npm registry | Заменить на поддерживаемый парсер или вынести разбор в изолированный backend/worker. Текущие лимиты уменьшают, но не устраняют риск |
| Высокий | Access/refresh tokens хранятся в `sessionStorage` и доступны при XSS | Перевести сессию на `HttpOnly + Secure + SameSite` cookies; refresh token не отдавать JavaScript |
| Средний | `npm audit --omit=dev` всё ещё показывает 16 транзитивных уязвимостей, преимущественно в tooling/MCP-цепочках | Обновлять владельцев цепочек зависимостей адресно; не применять `npm audit fix --force` без проверки совместимости |
| Средний | В repositories остаются публичные типы нескольких старых модулей | Перенести их в `modules/<module>/types/` при следующем изменении соответствующего модуля |
| Средний | Knip находит 10 неиспользуемых frontend-файлов и 35 exports | Удалить после подтверждения, что нет динамических импортов/внешних entry points |
| Низкий | Production build сообщает о чанках свыше 500 КБ, крупнейший — Swagger | Загружать Swagger и тяжёлые редакторы только по маршруту; проверить manual chunks после замеров |
| Низкий | В старых страницах остаются native controls и hardcoded slate-цвета | Постепенно переводить на `components/ui`, `components/form` и theme tokens |

## Принятые паттерны

- Strategy: политика конкурентности `LastStartedWinsPolicy` и стратегии построения полевых Zod-схем.
- Repository: весь прикладной HTTP находится за типизированными repositories.
- Adapter: repositories нормализуют API envelope/DTO в доменные типы.
- Facade: `useLatestRequest` скрывает sequence tracking, loading/error и cleanup.
- Command queue: сериализованное автосохранение настроек импорта с объединением ожидающих команд.

## Проверки

- `vue-tsc --noEmit` — без ошибок.
- `eslint resources/js --quiet` — без ошибок.
- `vitest run` — 25 файлов, 136 тестов.
- `vite build` — успешно.
- `knip` — результаты учтены в таблице оставшихся рисков.
