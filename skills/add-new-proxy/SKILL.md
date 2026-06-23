---
name: add-new-proxy
description: Add a new proxy webhook endpoint to the Proxy module — handler class with fields, client registry, ProxyRegistry wiring, and DB sync. Use when asked to add a new webhook endpoint, integrate a new CRM/third-party inbound API, or register a new proxy handler.
---

# Add New Proxy Endpoint

## Overview

A proxy endpoint is a public URL (`POST /api/proxies/{uuid}`) that accepts an inbound webhook, validates and normalises its payload via a **handler**, and calls your custom business logic. The system automatically logs the request, fires events, and returns a typed `ProxyResponse`.

Key paths:
- Handlers: `module/Proxy/Proxies/<Client>/<Service>/`
- Registries: `module/Proxy/Registry/<Client>/`
- Master registry: `module/Proxy/Registry/ProxyRegistry.php`
- Base class: `module/Proxy/ProxyHandler.php`
- Field types: `module/Proxy/DTO/ProxyFieldXxx.php`

## Processing Pipeline

```
POST /api/proxies/{uuid}
  → PublicProxyController
  → ProxyReceiverService::receiveHttp()
      1. Look up ProxyEndpoint by UUID (must be active)
      2. Validate HTTP method and payload size
      3. Instantiate handler via HandlerResolver
      4. Resolve fields → build ProxyContext (data, payload, query, headers, files)
      5. Call handler->handle($context)
      6. Log request + fire events (ProxyRequestAccepted / ProxyRequestFailed)
      7. Return ProxyResponse as JSON
```

## Step-by-Step

### 1. Create the handler

File: `module/Proxy/Proxies/<Client>/<Service>/MyHandler.php`

```php
<?php

declare(strict_types=1);

namespace Module\Proxy\Proxies\MyClient\MyService;

use Module\Proxy\DTO\ProxyContext;
use Module\Proxy\DTO\ProxyFieldInteger;
use Module\Proxy\DTO\ProxyFieldString;
use Module\Proxy\DTO\ProxyResponse;
use Module\Proxy\ProxyHandler;

final class MyHandler extends ProxyHandler
{
    public function fields(): iterable
    {
        yield ProxyFieldString::make('phone')
            ->label('Телефон')
            ->required()
            ->rules(['required', 'string', 'max:30'])
            ->example('+79990000000');

        yield ProxyFieldString::make('name')
            ->label('Имя')
            ->nullable()
            ->rules(['nullable', 'string', 'max:255'])
            ->example('Иван Иванов');

        yield ProxyFieldString::make('source')
            ->label('UTM-источник')
            ->source('query.utm_source')   // ← reads from ?utm_source=...
            ->nullable()
            ->default('direct');
    }

    public function handle(ProxyContext $ctx): ProxyResponse
    {
        $phone = is_string($ctx->data('phone')) ? $ctx->data('phone') : '';

        // call a service, dispatch a job, etc.

        return ProxyResponse::accepted([
            'request_id' => $ctx->requestId(),
            'status'     => 'created',
        ]);
    }
}
```

#### `fields()` rules

- Yield one `ProxyFieldXxx` per field the webhook is expected to send.
- The framework automatically validates, normalises, and maps each field into `$ctx->data(key)`.
- If a field is missing from the request, `default()` is used; without a default, it is `null`.

#### Reading data in `handle()`

| Method | What it returns |
|---|---|
| `$ctx->data('key')` | Resolved, normalised field value (primary source) |
| `$ctx->payload('key')` | Raw request body value (pre-normalisation) |
| `$ctx->query('key')` | URL query param |
| `$ctx->header('x-api-key')` | Request header (lowercase key) |
| `$ctx->config('crm_url')` | Endpoint config stored in DB |
| `$ctx->file('photo')` | `UploadedFile` or `null` |
| `$ctx->fileList('photos')` | `array<int, UploadedFile>` |
| `$ctx->requestId()` | Unique request UUID for logging |

#### Returning a response

```php
ProxyResponse::accepted(['request_id' => $ctx->requestId()])  // 202
ProxyResponse::ok(['items' => [...]])                           // 200
ProxyResponse::error('Dealer not found', 422)                  // any 4xx/5xx
```

### 2. Add to the client registry

File: `module/Proxy/Registry/<Client>/MyClientRegistry.php`

If the file already exists, add a new `yield`:

```php
yield new ProxyEndpointDefinition(
    uuid: 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx',   // generate once, never change
    code: 'my_client_my_service',                   // snake_case, unique across all registries
    name: 'My Client — My Service',
    description: 'Принимает заявку от My Service и создаёт лид в CRM',
    handlerClass: MyHandler::class,
    method: 'POST',                                 // 'GET' or 'POST' (default POST)
);
```

Generate a UUID with `php artisan tinker --execute="echo \Illuminate\Support\Str::uuid();"` or any UUID v4 generator. **Never reuse or change a UUID after sync.**

If this is a new client, create a new registry class:

```php
final class MyClientRegistry
{
    private function __construct() {}

    /** @return Generator<int, ProxyEndpointDefinition, mixed, void> */
    public static function all(): Generator
    {
        yield new ProxyEndpointDefinition(/* ... */);
    }
}
```

### 3. Register in ProxyRegistry

File: `module/Proxy/Registry/ProxyRegistry.php`

```php
public static function all(): Generator
{
    yield from MotorinvestProxyRegistry::all();
    yield from TestProxyRegistry::all();
    yield from MyClientRegistry::all();   // ← add this
}
```

### 4. Sync to the database

```bash
php artisan proxies:sync
```

This command upserts `proxy_endpoints` rows for every `ProxyEndpointDefinition` in `ProxyRegistry::all()`:
- **New code** → inserts with `is_active = true`.
- **Existing code** → updates `name`, `description`, `handler_class`, `method`. UUID and `is_active` are not overwritten.

Run after every `migrate` that touches the `proxy_endpoints` table, and after adding new handlers.

### 5. Verify the endpoint is live

```bash
php artisan route:list --name=proxy.proxies
```

Test with curl or Postman:
```bash
curl -X POST http://localhost/api/proxies/<uuid> \
  -H "Content-Type: application/json" \
  -d '{"phone": "+79990000000", "name": "Test"}'
```

Check `proxy_requests` table for the logged entry.

## Field Types Reference

| Class | PHP type | Default source |
|---|---|---|
| `ProxyFieldString` | `string` | `payload.<key>` |
| `ProxyFieldInteger` | `int` | `payload.<key>` |
| `ProxyFieldBoolean` | `bool` | `payload.<key>` |
| `ProxyFieldList` | `string` (enum) | `payload.<key>` — use `->values([...])` to constrain |
| `ProxyFieldArray` | `array` | `payload.<key>` |
| `ProxyFieldArrayList` | `array[]` | `payload.<key>` |
| `ProxyFieldUpload` | `UploadedFile` | `files.<key>` |
| `ProxyFieldUploadList` | `UploadedFile[]` | `files.<key>` |
| `ProxyFieldFileUrl` | `string` (URL) | `payload.<key>` |
| `ProxyFieldFileUrlList` | `string[]` (URLs) | `payload.<key>` |

### `->source(string $path)` — custom data source

Override where the framework reads the field value from:

```php
->source('query.utm_source')          // URL ?utm_source=...
->source('headers.x-dealer-id')       // request header
->source('payload.lead.phone')        // nested JSON key
->source('system.ip')                 // system metadata
```

Default source for every field: `payload.<key>`.

## Shared Base Handlers (inheritance pattern)

When multiple client registries share the same input shape (e.g., all AutoCRM leads have the same fields), extract a base handler:

```php
// module/Proxy/Proxies/Base/MyService/BaseMyServiceHandler.php
abstract class BaseMyServiceHandler extends ProxyHandler
{
    /** @return iterable<ProxyField> */
    protected function leadFields(): iterable
    {
        yield ProxyFieldString::make('phone')->required()-> ...;
        yield ProxyFieldString::make('name')->nullable()-> ...;
    }
}

// module/Proxy/Proxies/MyClient/MyService/MyClientMyServiceHandler.php
final class MyClientMyServiceHandler extends BaseMyServiceHandler
{
    public function fields(): iterable
    {
        yield from $this->leadFields();
        yield ProxyFieldString::make('dealer_code')->nullable()-> ...;  // client-specific extras
    }

    public function handle(ProxyContext $ctx): ProxyResponse { ... }
}
```

## Security

The handler class namespace must be in `Module\Proxy\` (or explicitly allowed in `config/proxy.php → allowed_handlers`). The `HandlerResolver` will reject unknown namespaces.

## References

See [references/add-new-proxy-checklist.md](references/add-new-proxy-checklist.md) for the quick checklist.
