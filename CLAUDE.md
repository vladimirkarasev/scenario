# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

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
task build                  # rebuild images
task restart                # restart containers

task shell                  # bash into the laravel container
task rr                     # show RoadRunner status via supervisorctl
task queue                  # show queue worker status via supervisorctl
task logs                   # tail laravel container logs
task logs -- centrifugo     # tail a specific service's logs

task artisan -- migrate
task artisan -- tinker
task composer -- require vendor/pkg
task npm:dev                # Vite dev server inside the node service (--profile tools)
```

## Architecture

The app is a Laravel 13 SPA (Inertia.js + Vue 3) — a visual workflow engine where users build and run node-graph scenarios. Served via **Laravel Octane + RoadRunner** (not PHP-FPM). Real-time via **Centrifugo** WebSockets.

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

Принимает входящие вебхуки, нормализует поля через handler, валидирует, ведёт лог, управляет retry. Handler-ы наследуют `Module\Proxy\WebhookHandler` и объявляют поля через `fields(): iterable`.

- Управление эндпоинтами: `WebhookRegistry` → `php artisan webhooks:sync` (вызывать после `migrate`)
- Поля handler-а: `WebhookFieldString`, `WebhookFieldInteger`, `WebhookFieldBoolean`, `WebhookFieldList`, `WebhookFieldArray` и др. — builder API: `->label()->source()->required()->example()->filterable()`
- Fields API: `GET /api/proxy/webhooks/{id}/fields` (auth) и `GET /api/webhooks/{uuid}/fields` (публичный, только активные)
- Доступы к внешним сервисам — в БД (`proxy_integrations`), не в конфиге. `ProxyHandler::authFields()` объявляет поля доступов (тот же builder + `->secret()`), админ заполняет их в UI (`/proxy/integrations`), значения шифруются (`credentials` → `encrypted:array`). `ProxyEndpoint` привязан к интеграции (`integration_id`); handler строит gateway из неё через `AutoCrmGatewayFactory::forIntegration()` / `ApiGatewayConfig::fromIntegration()`. Интеграции/эндпоинты сидятся `IntegrationRegistry` + `ProxyRegistry` → `php artisan proxies:sync`
- Исходящие gateway-вызовы: `BaseApiGateway` / `AutoCrmGateway` (конфиг приходит из интеграции, не из статического `config/proxy.php`)
- Секреты маскируются в API (`secret_filled` флаг); пустое значение при update = «не менять»
- Безопасность: handler-класс должен быть в namespace `Module\Proxy\Proxies\` или явно в `proxy.allowed_handlers`
- См. `docs/proxy-webhook-gateway.md`

### Actions module (orchestration)

Actions — переиспользуемые шаги (email, HTTP-запрос, CRM, XML, custom). В сценарии Action-нода настраивает оркестровку: `sync` / `chain` / `batch`, retry, webhooks. Данные из опроса подставляются через `ActionDataResolver` (`{{ input.field }}`). Async-выполнение через `ExecuteActionJob`, результат прилетает по WebSocket в канал `scenario-run:{id}`. Один endpoint: `POST /api/actions/{uuid}` — вся конфигурация хранится в `action.config`, не в URL. См. `docs/actions-orchestration.md`.

### Real-time channels (Centrifugo)

- `scenario-run:{id}` — live run progress + action completed/failed events
- `directory-import:{id}` — import progress

### Frontend

Pages live in `resources/js/Pages/` (Inertia page components): `Users/`, `Projects/`, `Scenario/`, `Dashboard/`, `Auth/`. The visual graph editor is `resources/js/components/scenario-flow/` (built on `@vue-flow/core`). Shared state uses Pinia (`resources/js/stores/`); UI-local state stays in composables (`resources/js/composables/`). Repositories in `resources/js/repositories/` handle all API calls. See `docs/api-conventions.md` for the API request format.

## Conventions

**Backend**
- Controllers validate only; delegate everything to Services
- Services are pure: no `request()` access, accept DTOs
- DTOs are `readonly` PHP classes
- Enums are backed (`string`/`int`) with `label()`/`color()` methods
- Tests instantiate services via `app(ServiceClass::class)` directly; HTTP tests only for HTTP-specific behavior
- Models created in tests via `Model::query()->create([...])` — no Factories defined

**Frontend**
- `<script setup lang="ts">` only — no Options API
- No `any` TypeScript types
- shadcn-vue components over native HTML elements
- Pinia only for cross-page shared state
- Page logic goes into composables, not inline in the component. Pattern per page: `useXxxList` (data fetching + pagination), `useXxxModal` (create/edit/delete form state), `useXxxFilters` (filter state + dropdowns) — see `resources/js/composables/` for examples (Users, Groups, Roles, Projects pages)

**API conventions (JSON:API)**
- Filter params are namespaced: `filter[search]=foo`, `filter[group_ids][]=1`, `filter[role_ids][]=1`
- Pagination params: `page[number]=2`, `page[size]=20`
- Backend DTOs read filters via `$request->array('filter')`, pagination via `$request->input('page.number')` / `$request->input('page.size')`
- No flat query params (`search=`, `group_ids[]=`, `per_page=`) on the API level
- Frontend repositories accept `URLSearchParams` directly — no intermediate `XxxQuery` objects with renamed fields. The caller (composable) builds `URLSearchParams` from `window.location.search` and adds hardcoded defaults (e.g. `per_page`).

**PHPStan** runs at level 10 across `app/` and `module/`.
