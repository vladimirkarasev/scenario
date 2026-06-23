# Actions Orchestration

Идея расширения модуля Actions для поддержки оркестровки (цепочки, батч, retry) с интеграцией в сценарий и уведомлением через WebSocket.

## Концепция

В сценарии пользователь добавляет **Action-ноду**, настраивает её прямо в редакторе:
- Какой action выполнить (выбор по UUID)
- Режим: `sync` / `chain` / `batch`
- Ожидать завершения: `wait_for_completion`
- Retry: количество попыток, backoff
- Webhooks: один или несколько (для chain/batch)

Данные из опроса (ScenarioRun context) подставляются в конфиг action через уже существующий `ActionDataResolver` (`{{ input.name }}`).

## API

Один endpoint — вся конфигурация оркестровки хранится в самом action:

```
POST /api/actions/{uuid}
Body: { "input": { ...данные из опроса..., "scenario_run_id": 123 } }
→ 202 { "run_id": "...", "status": "queued" }   // async
→ 200 { "run_id": "...", "status": "completed", "output": {...} }  // sync
```

Никаких `/chain` или `/batch` в URL — это детали конфига, не контракта.

## Конфиг action (action.config)

```json
{
  "execution_mode": "sync|chain|batch",
  "wait_for_completion": true,
  "retry": { "attempts": 3, "backoff": [60, 300, 900] },
  "webhooks": ["uuid-1", "uuid-2"],

  // специфика handler-а (email, http_request, crm, etc.):
  "url": "https://crm.example.com/leads",
  "body": { "name": "{{ input.name }}", "phone": "{{ input.phone }}" }
}
```

## Поток выполнения

```
ScenarioRun достигает Action-ноды
    → POST /api/actions/{uuid} { input: { опрос + scenario_run_id } }
        → ActionRunController
            → ActionExecutor::execute(action, input)
                → читает execution_mode из action.config
                → sync:  выполнить inline, вернуть результат
                → chain: Bus::chain([ ExecuteActionJob, ... ])->dispatch()
                → batch: Bus::batch([ ExecuteActionJob, ... ])->dispatch()
            → 202 { status: "queued" }

ExecuteActionJob выполняется в фоне (retry из action.config)
    → success / failed
        → broadcast ActionCompletedEvent → Centrifugo
            → канал scenario-run:{scenario_run_id}
            → payload: { type: "action_completed", run_id, status, output }

Frontend слушает WS канал scenario-run:{id}
    → получает action_completed
        → ScenarioPlayerService переходит к следующей ноде
```

## Два кейса использования

### Кейс 1 — Отправка заявки (async)
- `execution_mode: chain`
- `wait_for_completion: true`
- Webhooks: [email_action_uuid, crm_action_uuid]
- Handler диспатчит `Bus::chain([EmailJob, CrmJob])` → 202
- Каждый шаг при успехе шлёт WS-событие (прогресс)
- По завершении цепочки — `chain_completed` → сценарий идёт дальше

### Кейс 2 — Справочники (sync, Brands/Dealers/Models)
- `execution_mode: sync`
- `wait_for_completion: true`
- Без retry (maxAttempts = 1)
- Handler вызывает AutoCRM синхронно, возвращает данные сразу в ответе
- Сценарий получает данные и продолжается без ожидания WS

## WS события (канал scenario-run:{id})

| Событие | Когда |
|---|---|
| `action_started` | Job взят в работу |
| `action_completed` | Job успешно завершён |
| `action_failed` | Job исчерпал retry |
| `chain_completed` | Все шаги цепочки выполнены |
| `batch_completed` | Все параллельные задачи выполнены |

## Что нужно реализовать

- [ ] `ExecuteActionJob` — queued job, оборачивает `ActionExecutor::execute()`, бросает исключение если `ActionRunStatus::Failed` (для retry)
- [ ] `ActionCompletedEvent` — broadcast event → Centrifugo канал `scenario-run:{id}`
- [ ] Расширить `action.config` схему: `execution_mode`, `wait_for_completion`, `retry`, `webhooks`
- [ ] `ActionRunController::run()` — читает конфиг, диспатчит sync/chain/batch
- [ ] Action-нода в сценарии — UI для настройки оркестровки в редакторе

## Связанные файлы

- `module/Actions/Services/ActionExecutor.php` — текущий синхронный executor
- `module/Actions/Services/ActionDataResolver.php` — шаблонизация `{{ input.field }}`
- `module/Actions/Services/Handlers/` — EmailActionHandler, HttpRequestActionHandler, etc.
- `module/Proxy/Webhooks/Motorinvest/` — пример sync-кейса (Brands/Dealers/Models)
- `docs/scenario-player.md` — как работает ScenarioPlayerService
