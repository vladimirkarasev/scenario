---
name: add-new-action
description: Add a new Action type to the Actions module — handler class, ActionType enum case, ActionRegistry registration. Use when asked to add a new integration type (SMS, push notification, CRM write, etc.) that executes as an action step in a scenario.
---

# Add New Action Type

## Overview

Actions — переиспользуемые шаги сценария. Каждый тип action-а имеет handler, который принимает `Action $action` (с настройками из `action.config`) и `array $input` (данные из сценария) и возвращает `ActionResult`.

Цепочка выполнения:

```
ScenarioRun → ActionNodeHandler → ActionOrchestratorService
           → ActionRegistry::handlerFor($type)
           → YourActionHandler::handle($action, $input)
           → ActionResult::success|failed|skipped
```

Шаблонные строки `{{ input.field }}` в `action.config` раскрываются через `ActionDataResolver` перед вызовом handler-а.

---

## Step 1 — Добавить case в `ActionType`

```php
// module/Actions/Enums/ActionType.php
enum ActionType: string
{
    case HttpRequest = 'http_request';
    case Webhook     = 'webhook';
    case Email       = 'email';
    case Xml         = 'xml';
    case Crm         = 'crm';
    case Custom      = 'custom';
    case Sms         = 'sms';  // ← новый
}
```

Значение (snake_case строка) — это то, что хранится в `actions.type` в БД.

---

## Step 2 — Создать handler

```php
// module/Actions/Services/Handlers/SmsActionHandler.php
<?php

declare(strict_types=1);

namespace Module\Actions\Services\Handlers;

use Module\Actions\Contracts\ActionHandlerInterface;
use Module\Actions\DTO\ActionResult;
use Module\Actions\Models\Action;
use Module\Actions\Services\ActionDataResolver;

final class SmsActionHandler implements ActionHandlerInterface
{
    public function __construct(
        private readonly ActionDataResolver $dataResolver,
        // инжекти любые зависимости — gateway, mailer, etc.
    ) {}

    public function handle(Action $action, array $input = []): ActionResult
    {
        // 1. Раскрыть шаблоны {{ input.field }} в config
        $config = $this->dataResolver->resolve($action->config ?? [], $input);

        // 2. Достать нужные поля из config
        $phone   = $config['phone'] ?? null;
        $message = $config['message'] ?? '';

        // 3. Валидация config
        if (! is_string($phone) || $phone === '') {
            return ActionResult::failed('SMS action requires `phone` in config.');
        }

        // 4. Выполнить операцию
        try {
            // $this->smsSender->send($phone, (string) $message);
        } catch (\Throwable $e) {
            return ActionResult::failed(
                error: 'SMS send failed: ' . $e->getMessage(),
                output: ['phone' => $phone],
            );
        }

        // 5. Вернуть результат
        return ActionResult::success([
            'phone'   => $phone,
            'message' => $message,
        ]);
    }
}
```

### ActionResult варианты

```php
ActionResult::success(['key' => 'value']);          // статус Success, output сохраняется в ActionRun

ActionResult::failed('Причина', ['debug' => ...]);  // статус Failed, error + output

ActionResult::skipped('Условие не выполнено');      // статус Skipped — не считается ошибкой
```

---

## Step 3 — Зарегистрировать в `ActionRegistry`

```php
// module/Actions/Services/ActionRegistry.php
public function handlerFor(string $type): ActionHandlerInterface
{
    return match (ActionType::from($type)) {
        ActionType::HttpRequest => app(HttpRequestActionHandler::class),
        ActionType::Webhook     => app(HttpRequestActionHandler::class),
        ActionType::Crm         => app(HttpRequestActionHandler::class),
        ActionType::Email       => app(EmailActionHandler::class),
        ActionType::Xml         => app(XmlActionHandler::class),
        ActionType::Custom      => app(CustomActionHandler::class),
        ActionType::Sms         => app(SmsActionHandler::class),  // ← добавить
    };
}
```

Используй `app()` — Laravel auto-wires зависимости handler-а.

---

## ActionDataResolver — как работает

`$this->dataResolver->resolve($config, $input)` рекурсивно обходит `$config` и заменяет `{{ input.field }}` на значение из `$input`:

```php
// config хранится как:
$config = [
    'phone'   => '{{ input.phone }}',
    'message' => 'Привет, {{ input.name }}!',
];

// input приходит из ScenarioRun:
$input = ['phone' => '+7...', 'name' => 'Иван'];

// после resolve:
$resolved = [
    'phone'   => '+7...',
    'message' => 'Привет, Иван!',
];
```

Всегда вызывай `resolve()` перед обращением к `$config`.

---

## Credential-ы (опционально)

Если action требует API-ключ или токен (не передаётся из сценария, а хранится в `ActionCredential`):

```php
public function __construct(
    private readonly ActionDataResolver $dataResolver,
    private readonly ActionCredentialResolver $credentialResolver,  // ← добавить
) {}

public function handle(Action $action, array $input = []): ActionResult
{
    $config      = $this->dataResolver->resolve($action->config ?? [], $input);
    $credentials = $this->credentialResolver->resolve($config['credential_id'] ?? null);
    // $credentials содержит ['headers' => [...], 'query' => [...]] в зависимости от типа
}
```

---

## Verification

```bash
./vendor/bin/phpstan analyse module/Actions --memory-limit=512M --error-format=table
./vendor/bin/pint module/Actions

# Быстрый smoke-test в tinker
php artisan tinker
> $action = Module\Actions\Models\Action::query()->where('type', 'sms')->first();
> app(Module\Actions\Services\ActionRegistry::class)->handlerFor('sms')->handle($action, ['phone' => '+7...'])
```

---

## Decision Table

| Ситуация | Что делать |
|---|---|
| Action шлёт HTTP-запрос | Переиспользуй `HttpRequestActionHandler` — добавь новый case в enum, но укажи тот же handler |
| Action использует внешний API (gateway) | Инжекти gateway-класс в конструктор handler-а |
| Нужны credentials (API-ключ) | Инжекти `ActionCredentialResolver`, читай `$config['credential_id']` |
| Config невалиден | Верни `ActionResult::failed('Причина')` — не бросай исключения |
| Нужно пропустить action при условии | Верни `ActionResult::skipped('Условие не выполнено')` |
| Action медленный (внешний API) | Laravel запускает через `ExecuteActionJob` в очереди — таймауты настраиваются в `action.config['timeout']` |

## References

See [references/add-new-action-checklist.md](references/add-new-action-checklist.md) for the quick checklist.
