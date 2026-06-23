# Iframe Embed

Интеграция сервиса во внешний сайт через `<iframe>`. Авторизация полностью токен-based — сессий нет, токены хранятся в `sessionStorage` iframe и сбрасываются при закрытии вкладки.

## Схема работы

```
Внешний сервер  →  POST /api/project/{uuid}/user-register  →  launch_token (TTL 5 мин)
Внешний фронт   →  <iframe src="/embed?token={launch_token}&redirect={path}">
Scenario (iframe) →  POST /api/embed/auth/exchange  →  access_token + refresh_token
                  →  сохраняет в sessionStorage
                  →  router.visit(redirect)
```

## Шаг 1. Настройка проекта

В настройках проекта (раздел Projects) есть два поля:

| Поле            | Описание                                      |
|-----------------|-----------------------------------------------|
| `uuid`          | Идентификатор проекта                         |
| `shared_secret` | Секрет для подписи server-to-server запросов  |

## Шаг 2. Регистрация пользователя (server-to-server)

Вызывается с **вашего бэкенда** (не с браузера — секрет не должен быть публичным).

```http
POST /api/project/{projectUuid}/user-register
Authorization: Bearer {shared_secret}
Content-Type: application/json

{
  "login": "user123",
  "name": "Иван Иванов",
  "email": "user@company.com",
  "roles": ["student"]
}
```

Поля `email` и `roles` — опциональны. `login` используется как уникальный идентификатор пользователя внутри проекта.

**Ответ:**

```json
{
  "iframe_launch_token": "a1b2c3d4...",
  "expires_in": 300
}
```

> Launch token одноразовый и живёт **5 минут**. Запрашивайте его непосредственно перед вставкой iframe — не кешируйте.

## Шаг 3. Вставка iframe

```html
<iframe
  src="https://scenario.app/embed?token={launch_token}"
  allow="fullscreen"
  style="width: 100%; height: 600px; border: none;"
/>
```

### Переход на конкретную страницу

По умолчанию после авторизации открывается `/scenarios`. Чтобы открыть конкретную страницу, добавьте параметр `redirect`:

```html
<!-- Список сценариев (по умолчанию) -->
<iframe src="/embed?token=...&redirect=/scenarios" />

<!-- Конкретный сценарий -->
<iframe src="/embed?token=...&redirect=/scenarios/5/play" />

<!-- Конкретная версия сценария -->
<iframe src="/embed?token=...&redirect=/scenario-versions/12/play" />
```

## Токены

| Токен           | TTL      | Где хранится      |
|-----------------|----------|-------------------|
| `launch_token`  | 5 минут  | не хранится       |
| `access_token`  | 30 минут | `sessionStorage`  |
| `refresh_token` | 7 дней   | `sessionStorage`  |

`sessionStorage` сбрасывается при закрытии вкладки или iframe — пользователь автоматически выходит из системы.

Обновление `access_token` происходит через:

```http
POST /api/embed/auth/refresh
Content-Type: application/json

{ "refresh_token": "..." }
```

## Dev-симуляция

В режиме `IFRAME_AUTH_DEV_ENABLED=true` доступна страница `/auth` для симуляции embed-авторизации без внешнего сервера. Вводится `project_uuid`, `shared_secret` и данные пользователя — выдаётся токен и происходит редирект в приложение.

## Структура файлов

- `app/Http/Controllers/EmbedAuth/` — контроллеры авторизации
- `app/Services/EmbedAuth/EmbedAuthTokenService.php` — логика токенов
- `app/Services/EmbedAuth/EmbedAuthUserService.php` — синхронизация пользователей
- `routes/api.php` — маршруты `/api/project/*/user-register`, `/api/embed/auth/*`
- `routes/web.php` — маршрут `/embed`
- `resources/js/Pages/Embed.vue` — entry page внутри iframe
- `resources/js/stores/auth.ts` — Pinia-стор с текущим пользователем и permissions
- `resources/js/lib/http-client.ts` — axios с автоматической подстановкой Bearer токена
