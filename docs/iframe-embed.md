# Iframe Embed

Интеграция сервиса во внешний сайт через `<iframe>`. Авторизация полностью токен-based — сессий нет,
токены хранятся в `sessionStorage` iframe и сбрасываются при закрытии вкладки.

## Схема работы

```
Создание проекта       →  автоматически создаётся системный пользователь проекта
Внешний бэкенд         →  POST /api/users (Bearer {system_token})              →  создаёт/обновляет пользователя
Внешний бэкенд         →  POST /api/users/iframe-token (Bearer {system_token}) →  одноразовый _token (TTL 5 мин)
Внешний фронт          →  <iframe src="https://scenario.app/scenarios?_token={_token}">
Scenario (iframe)      →  ловит ?_token= глобально  →  POST /api/embed/auth/exchange  →  access + refresh
                       →  сохраняет в sessionStorage, вырезает _token из URL, продолжает
```

Отдельной страницы `/embed` больше нет: `_token` перехватывается на **любой** странице при загрузке
приложения (`resources/js/app.ts`).

## Модель пользователей

- **Один пользователь = один проект** (`users.project_id`). Один и тот же `login`/`email` может
  существовать в разных проектах как разные пользователи; в рамках проекта они уникальны.
- **Системный пользователь** создаётся автоматически при создании проекта (`is_system = true`,
  роль `project-service`). Через его API-токен внешний бэкенд ходит в обычный API. Системного
  пользователя нельзя редактировать/удалять через API.

## Шаг 1. Токен системного пользователя

Системный пользователь появляется вместе с проектом. Его bearer-токен выпускается штатно
(в разделе пользователей проекта или через API), можно бессрочный:

```http
POST /api/users/{system_user_id}/tokens
Authorization: Bearer {admin_token}

{ "name": "integration" }
```

**Ответ** содержит `token` (plaintext) — показывается один раз. Этим токеном авторизуются
server-to-server запросы ниже.

## Шаг 2. Создание/обновление пользователя (server-to-server)

Обычный API пользователей, авторизация — токеном системного пользователя проекта. Проект
определяется автоматически по системному пользователю.

```http
POST /api/users
Authorization: Bearer {system_token}
Content-Type: application/json

{
  "name": "Иван Иванов",
  "login": "user123",
  "email": "user@company.com",
  "external_id": "crm-42",
  "roles": ["student"]
}
```

`login` и `email` обязательны и уникальны в рамках проекта. `password` необязателен
(iframe-пользователи входят по токену). `external_id` — ваш внешний идентификатор.

## Шаг 3. Получение одноразового `_token`

```http
POST /api/users/iframe-token
Authorization: Bearer {system_token}
Content-Type: application/json

{ "external_id": "crm-42" }
```

**Ответ:**

```json
{ "_token": "a1b2c3d4...", "expires_in": 300 }
```

> `_token` одноразовый и живёт **5 минут**. Запрашивайте его непосредственно перед вставкой iframe.

## Шаг 4. Вставка iframe

`_token` передаётся query-параметром на любую страницу приложения:

```html
<!-- Список сценариев -->
<iframe src="https://scenario.app/scenarios?_token={_token}" allow="fullscreen" />

<!-- Конкретный сценарий -->
<iframe src="https://scenario.app/scenarios/5/play?_token={_token}" />
```

Приложение при загрузке обменивает `_token` на пару access/refresh, вырезает его из URL и
показывает запрошенную страницу. При невалидном/просроченном `_token` показывается 403-экран
с кнопкой перезагрузки (перезагружает всё окно вне iframe).

## Токены

| Токен           | TTL      | Хранилище        | Таблица                   |
|-----------------|----------|------------------|---------------------------|
| `_token`        | 5 минут  | не хранится      | `sso_launch_tokens`       |
| `access_token`  | 30 минут | `sessionStorage` | Sanctum PAT               |
| `refresh_token` | 7 дней   | `sessionStorage` | `personal_refresh_tokens` |

`sessionStorage` сбрасывается при закрытии вкладки/iframe — пользователь автоматически выходит.
Sanctum не имеет встроенного refresh: короткий access + отдельный ротируемый refresh-токен —
это наш слой поверх Sanctum. Обновление access:

```http
POST /api/embed/auth/refresh
Content-Type: application/json

{ "refresh_token": "..." }
```

## Dev-симуляция

При `IFRAME_AUTH_DEV_ENABLED=true` доступна страница `/auth` — полностью повторяет прод-флоу:
форма (проект + данные пользователя) создаёт/обновляет пользователя проекта, выпускает
одноразовый `_token` и делает редирект на `/scenarios?_token=...`, где срабатывает тот же
глобальный перехват. Dev-`_token` выпускается без Origin-ограничения (в локали Origin ≠ `project->host`).

## Структура файлов

- `app/Http/Controllers/EmbedAuth/` — exchange / refresh / logout
- `module/Users/Http/Controllers/IframeTokenController.php` — выпуск `_token`
- `module/Users/Services/SystemUserService.php` — системный пользователь проекта
- `app/Services/EmbedAuth/EmbedAuthTokenService.php` — логика токенов
- `app/Models/SSOLaunchToken.php`, `app/Models/PersonalRefreshToken.php` — модели токенов
- `resources/js/app.ts` — глобальный перехват `_token`
- `resources/js/components/AuthForbidden.vue` — 403-экран
- `resources/js/lib/auth-state.ts` — флаг `authForbidden`
