# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

> Детальные правила и паттерны — в `skills/` (auto-trigger по frontmatter). Здесь —
> карта проекта, команды и инварианты; за глубиной идём в указанный skill.

## Commands

```bash
# Development (local)
composer run dev        # Starts Laravel server + queue + pail + Vite concurrently
composer run test       # Clears config, then runs PHPUnit
composer run setup      # First-time setup: deps, .env, key, migrate, npm install + build

# Tests
php artisan test
php artisan test --filter=ClassName

# Static analysis
./vendor/bin/phpstan analyse --memory-limit=512M --error-format=table
./vendor/bin/phpstan analyse module/Scenario --memory-limit=512M

# Refactoring (Rector — заменил Pint)
./vendor/bin/rector process            # применить
./vendor/bin/rector process --dry-run  # предпросмотр

# Frontend
npm run dev             # Vite dev server
npm run build           # Production build
```

```bash
# Taskfile (wraps Docker Compose)
task                        # alias for task up
task up                     # docker compose up -d
task up:dev                 # + Traefik & Buggregator (--profile dev)
task down                   # stop & remove containers
task down:dev               # stop including dev-profile services
task build                  # rebuild images (needs RT_TOKEN env — GitHub token, public-repo read-only — for the Velox rr build, see docker/app/velox.toml)
task restart                # restart containers

task shell                  # bash into the laravel container
task rr                     # show RoadRunner worker pools status
task jobs                   # show RoadRunner Jobs pipelines status
task logs                   # tail laravel container logs
task logs -- centrifugo     # tail a specific service's logs

task artisan -- migrate
task artisan -- tinker
task composer -- require vendor/pkg
task npm:dev                # Vite dev server inside the node service (--profile tools)
```

## Architecture

The app is a Laravel 13 SPA (Inertia.js + Vue 3) — a visual workflow engine where users build and run node-graph scenarios. Served via **RoadRunner** directly (`roadrunner-php/laravel-bridge`, config in `.rr.yaml`; Laravel Octane is used only as an internal worker library, not as a separate server/CLI). The queue (`imports`/`default`) runs on the RoadRunner **Jobs** plugin — driver picked via `RR_JOBS_DRIVER` env (`boltdb` locally, broker of choice in prod). The container runs a single process (`rr serve`, no supervisord, no cron daemon) — `rr` is a custom Velox build (`docker/app/velox.toml`) that bundles the community **cron** plugin, which runs `php artisan schedule:run` every minute natively inside the RoadRunner process (`.rr.yaml`'s `cron:` section). Locally `RR_DEBUG=true` (`compose.yaml`) sets `pool.debug` on the http/jobs pools — a fresh worker per request/job, so PHP changes are picked up immediately, no `task reload` needed (never enable in prod — kills worker reuse). Real-time via **Centrifugo** WebSockets.

### Module system

Domain logic lives in `module/` (namespace `Module\`), not in `app/`. `app/` is a thin layer for cross-cutting concerns (User model, auth, roles/permissions). Each module registers its own routes via a ServiceProvider.

```
module/
  Scenario/     # Core: scenario engine (graph, execution, history)
  Actions/      # Reusable HTTP action steps + credential management
  Categories/   # Shared categorisation: abstract controller + model_has_categories
  Directories/  # Data directories (CSV/Excel imports, versioning)
  Projects/     # Project / tenant management; web route: /projects
  Groups/       # User group management; web routes: /users/groups
  Users/        # User management; web routes: /users, /users/roles, /users/groups
  Proxy/        # Webhook proxy: receive, log, retry webhook requests
```

Each module follows the same internal layout:
```
Models/ DTO/ Enums/ Http/{Controllers,Requests}/ Services/ Providers/ routes/api.php Exceptions/
```

### Scenario module (core domain)

- `Scenario` → `ScenarioVersion` (graph snapshots: `nodes_json`, `edges_json`, `schema_json`)
- `ScenarioRun` → live execution state (current node, runtime context JSON, status)
- `ScenarioRunStep` → append-only audit log per run
- `ScenarioPlayerService` drives graph traversal
- `ScenarioGraphResolver` resolves start nodes and transitions
- `App\Services\Expression\ExpressionService` evaluates expressions via Symfony Expression Language; Scenario uses it through `VariableResolver`
- Node handlers in `module/Scenario/Services/Nodes/`: one class per node type (`BlockNodeHandler`, `ConditionNodeHandler`, `EndNodeHandler`, `ScenarioLinkNodeHandler`, `DefaultNodeHandler`). Adding new node behavior = add a handler, do not extend `PlayerService` with conditionals.

### Categories module (shared categorisation)

Полиморфная система категорий, используемая несколькими модулями через наследование.

- `categories` — сущности (дерево через `parent_id`, UUID PK)
- `model_has_categories` — полиморфная pivot: `category_id`, `model_id` (uuid), `model_type` (класс модели)
- `CategoryController` (abstract) — базовый CRUD; метод `modelClass(): string` определяет фильтр по `model_type`
- Каждый модуль создаёт свой контроллер-наследник и регистрирует роут у себя
- `model_has_categories` обновляется через `$model->categories()->sync($ids)` при сохранении модели
- Модели-потребители обязаны использовать `HasUuids` (поле `model_id` — uuid)

**Текущие потребители:** Directories → `DirectoryCategoryController` → `api/directories/categories`

См. `docs/categories.md`.

### Auth

Sanctum-based auth. Production flow for embedding in external systems: launch-token → exchange → access/refresh (`App\Services\EmbedAuth`, routes `embed/auth/*`). `DEV_AUTH_ENABLED` включает dev-хелперы: страницу `/auth` (симуляция входа, `DevAuthWebController`/`DevAuth.vue`) и `dev/auth/*` API (`DevAuthApiController`). Вход по логину/паролю удалён; остаётся `POST /auth/logout` (`TokenAuthController`) для отзыва токена. См. `docs/embed-integration.md`.

### Proxy module (webhook gateway)

Входящий вебхук → нормализация полей через handler (`Module\Proxy\WebhookHandler`, поля объявляются `fields(): iterable`) → валидация, лог, retry. Доступы к внешним сервисам — в БД (`proxy_integrations`, `credentials` шифруются), не в конфиге; `ProxyEndpoint` привязан к интеграции, из неё строится gateway. Реестры сидятся командами `php artisan webhooks:sync` и `proxies:sync` (после `migrate`). Безопасность: handler-класс — в namespace `Module\Proxy\Proxies\` (или в `proxy.allowed_handlers`).

Детали (builder полей, gateway, секреты, fields API) — `docs/proxy-webhook-gateway.md`; добавление — skills `add-new-proxy` / `add-new-proxy-gateway`.

### Actions module (orchestration)

Actions — переиспользуемые шаги (email, HTTP-запрос, CRM, XML, custom). В сценарии Action-нода настраивает оркестровку: `sync` / `chain` / `batch`, retry, webhooks. Данные из опроса подставляются через `ActionDataResolver` (`{{ input.field }}`). Async-выполнение через `ExecuteActionJob`, результат прилетает по WebSocket в канал `scenario-run:{id}`. Один endpoint: `POST /api/actions/{uuid}` — вся конфигурация хранится в `action.config`, не в URL. См. `docs/actions-orchestration.md`.

### Real-time channels (Centrifugo)

Centrifugo runs as a separate container and holds client WebSocket connections directly (not proxied through RR — RR is not a transport layer here). `roadrunner-php/centrifugo` (RR_MODE=centrifuge, `.rr.yaml`'s `centrifuge:` section) handles only two things server-to-server:
- **Subscribe authorization** — Centrifugo calls RR's gRPC subscribe-proxy (`app/Workers/CentrifugoWorker.php`) when a client subscribes to a channel.
- **Outbound publish** — Laravel dispatches `App\Events\CentrifugoMessagePublished($channel, $payload)` (never inject `CentrifugoApiInterface` directly in jobs/services), handled by `PublishCentrifugoMessage` (RPC → RR → Centrifugo gRPC API) and `LogCentrifugoMessage`. Both listeners are registered explicitly in `AppServiceProvider::register()` — event auto-discovery is disabled (`bootstrap/app.php`'s `withEvents(discover: false)`), so any new listener needs manual `Event::listen()`.

Connect auth is native: Centrifugo validates a short-lived JWT itself (`client.token.hmac_secret_key` in `docker/centrifugo/config.json`), minted by `CentrifugoTokenController::connectionToken()` (`GET /api/centrifugo/connection-token`, `sub` = user id). RR is not involved in connect at all.

Channels:
- `scenario-run:{id}` — live run progress + action started/completed/failed, chain/batch completed/failed
- `directory-import:{id}` — import progress
- `#user:{id}` — personal channel (built-in Centrifugo mechanism), used for remote scenario dispatch

Full message catalog and connection flow — `docs/openapi/openapi.yaml`'s «Real-time» tag (rendered at `/swagger`, includes payload shapes for every message type via OpenAPI 3.1 `webhooks`).

### Frontend

Pages live in `resources/js/Pages/` (Inertia page components): `Users/`, `Projects/`, `Scenario/`, `Dashboard/`, `Auth/`. The visual graph editor is `resources/js/components/scenario-flow/` (built on `@vue-flow/core`). Shared state uses Pinia (`resources/js/stores/`); UI-local state stays in composables (`resources/js/composables/`). Repositories in `resources/js/repositories/` handle all API calls. See `docs/api-conventions.md` for the API request format.

## Conventions

Инварианты ниже; детали — в указанном skill (единый источник правды).

**Backend**
- Контроллеры только валидируют и делегируют; сервисы чистые (без `request()`), принимают DTO; DTO — `readonly`; Enum — backed с `label()`/`color()`. → `add-crud-jsonapi`, `laravel-refactor`
- Конфиг моделей — через PHP-атрибуты (`#[Table]`/`#[Fillable]`/`#[UseEloquentBuilder]`/`#[\Override]`), не свойства/override-методы. → `prefer-php-attributes`
- Комментарии — только докблоки у методов, без инлайна в теле. → `code-comments`
- PHPStan level 10 на `app/` и `module/`. → `static-analysis`

**API**
- JSON:API query: `filter[...]`, `page[number]`/`page[size]`, `sort`, `include`; без плоских `search=`/`per_page=`. → `jsonapi-conventions`
- Конверт ответа `{data, meta}` и ошибки `{errors:[...], meta}` через доменные исключения + enum-коды. → `api-response-contract`

**Frontend**
- `<script setup lang="ts">`, без `any`, shadcn-vue вместо нативных элементов, Pinia только для cross-page state.
- Логика страницы — в composables: `useXxxList` / `useXxxModal` / `useXxxFilters`. Репозитории принимают `URLSearchParams` напрямую.
- Формы — zod + `useZodForm`; toast на каждое CRUD-действие; типы в `types/`; чинить все tsc-ошибки. → `form-validation`, `crud-toast`, `types-organization`, `typescript-fix-policy`

**Tests**
- Сервисы через `app(Service::class)`; модели `Model::query()->create([...])` (фабрик нет); HTTP-тесты только для HTTP-специфики. → `write-tests`

**PHPStan** — level 10 across `app/` and `module/`.
