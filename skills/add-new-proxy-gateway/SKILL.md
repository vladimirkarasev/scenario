---
name: add-new-proxy-gateway
description: Add a new outbound API gateway to the Proxy module — gateway class, method objects, response DTOs, config/proxy.php entry, and env vars. Also covers adding a brand-specific client (same API, different credentials) on top of an existing gateway. Use when asked to integrate a new external service (CRM, SMS, API), add credentials for a new client/brand, or wire a gateway into a proxy handler.
---

# Add New Proxy Gateway

## Two Scenarios

| Scenario | When to use |
|---|---|
| **A. New gateway** | Integrating a brand-new external API (new service, new URL schema) |
| **B. New client** | Same external API already has a gateway class, but a new client needs its own credentials |

Start with **B** if the gateway class for the external service already exists (e.g., `AutoCrmGateway`). Use **A** for a completely new third-party API.

---

## Scenario A: New Gateway (new external API)

### Directory layout

```
module/Proxy/Gateway/<ServiceName>/
  DTO/
    <ServiceName>Data.php        ← normalised response object
    <ServiceName>ListQuery.php   ← (optional) query params builder
  Methods/
    Create<EntityMethod>.php     ← one class per API endpoint
    Get<Entity>Method.php
    Get<Entity>ListPageMethod.php
  <ServiceName>Gateway.php       ← the main gateway class
```

### Step 1 — Create Method objects

Each method is a `readonly` class extending `AbstractApiMethod`. Override only what differs from the base.

**GET with query params:**

```php
// module/Proxy/Gateway/MyCrm/Methods/GetDealerListMethod.php
final readonly class GetDealerListMethod extends AbstractApiMethod
{
    /** @param array<string, mixed> $query */
    public function __construct(private array $query = []) {}

    public function key(): string { return 'my_crm.dealer.list'; }
    public function method(): string { return 'GET'; }
    public function uri(): string { return '/api/v1/dealers'; }

    /** @return array<string, mixed> */
    public function query(): array { return $this->query; }
}
```

**POST with body:**

```php
// module/Proxy/Gateway/MyCrm/Methods/CreateLeadMethod.php
final readonly class CreateLeadMethod extends AbstractApiMethod
{
    /** @param array<string, mixed> $body */
    public function __construct(private array $body) {}

    public function key(): string { return 'my_crm.lead.create'; }
    public function method(): string { return 'POST'; }
    public function uri(): string { return '/api/v1/leads'; }

    /** @return array<string, mixed> */
    public function body(): array { return $this->body; }
}
```

`key()` is only used for logging — use a dot-notation string unique to this gateway.

### Step 2 — Create response DTO (optional)

If the gateway always returns the same envelope, create a typed wrapper. If the response shape varies per method, use `ApiGatewayResponse::json()` directly.

```php
// module/Proxy/Gateway/MyCrm/DTO/MyCrmData.php
final readonly class MyCrmData
{
    /** @param array<string, mixed> $data */
    public function __construct(public array $data) {}

    /** @param array<mixed, mixed> $data */
    public static function fromArray(array $data): self
    {
        $typed = [];
        foreach ($data as $k => $v) { $typed[(string) $k] = $v; }
        return new self($typed);
    }

    public function id(): int|string|null
    {
        $id = $this->data['id'] ?? null;
        return is_int($id) || is_string($id) ? $id : null;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->data, $key, $default);
    }
}
```

### Step 3 — Create the gateway class

```php
// module/Proxy/Gateway/MyCrm/MyCrmGateway.php
final class MyCrmGateway extends BaseApiGateway
{
    public function __construct(
        ApiGatewayConfigRepository $configs,
        GuzzleApiTransport $transport,
        MockApiTransport $mockTransport,
        LoggerInterface $logger,
        string $gatewayName = 'my_crm',   // matches key in config/proxy.php
    ) {
        parent::__construct(
            config: $configs->get($gatewayName),
            transport: $transport,
            mockTransport: $mockTransport,
            logger: $logger,
        );
    }

    /** @param array<string, mixed> $lead */
    public function createLead(array $lead): ApiGatewayResponse
    {
        return $this->send(new CreateLeadMethod($lead));
    }

    /** @return array<int, MyCrmData> */
    public function dealers(): array
    {
        $response = $this->send(new GetDealerListMethod());
        $items = is_array($response->body) ? $response->body : [];
        return array_values(array_map(
            static fn (array $item): MyCrmData => MyCrmData::fromArray($item),
            array_filter($items, 'is_array'),
        ));
    }
}
```

The gateway exposes **domain methods** (`createLead`, `dealers`), not raw HTTP calls. Handlers call the domain methods, not `$this->send(new ...)` directly.

### Step 4 — Add config entry

File: `config/proxy.php`, inside `'gateways'`:

```php
'my_crm' => [
    'base_uri'        => env('PROXY_GATEWAY_MY_CRM_BASE_URI', ''),
    'timeout'         => (float) env('PROXY_GATEWAY_MY_CRM_TIMEOUT', 10),
    'connect_timeout' => (float) env('PROXY_GATEWAY_MY_CRM_CONNECT_TIMEOUT', 5),
    'mock'            => (bool) env('PROXY_GATEWAY_MY_CRM_MOCK', false),
    'auth' => [
        'type'     => env('PROXY_GATEWAY_MY_CRM_AUTH_TYPE', 'bearer'),
        'token'    => env('PROXY_GATEWAY_MY_CRM_TOKEN'),
        'username' => env('PROXY_GATEWAY_MY_CRM_USERNAME'),
        'password' => env('PROXY_GATEWAY_MY_CRM_PASSWORD'),
    ],
    'headers' => [
        'Accept' => 'application/json',
    ],
],
```

Auth types supported by `GuzzleApiTransport`:
- `'none'` — no auth headers
- `'bearer'` — `Authorization: Bearer {token}`
- `'basic'` — HTTP Basic (`username` + `password` via Guzzle `auth` option)
- `'headers'` — arbitrary headers from `auth.headers` map

### Step 5 — Add env vars to `.env.example`

```dotenv
PROXY_GATEWAY_MY_CRM_BASE_URI=
PROXY_GATEWAY_MY_CRM_TOKEN=
PROXY_GATEWAY_MY_CRM_AUTH_TYPE=bearer
PROXY_GATEWAY_MY_CRM_TIMEOUT=10
PROXY_GATEWAY_MY_CRM_CONNECT_TIMEOUT=5
PROXY_GATEWAY_MY_CRM_MOCK=false
```

### Step 6 — Use in a proxy handler

```php
final class MyClientMyServiceHandler extends ProxyHandler
{
    public function __construct(
        private readonly MyCrmGateway $gateway,
    ) {}

    public function fields(): iterable
    {
        yield ProxyFieldString::make('phone')->required()->rules(['required', 'string']);
    }

    public function handle(ProxyContext $ctx): ProxyResponse
    {
        $response = $this->gateway->createLead([
            'phone' => $ctx->data('phone'),
        ]);

        if (! $response->successful()) {
            return ProxyResponse::error('CRM rejected the lead', 502);
        }

        return ProxyResponse::accepted([
            'request_id' => $ctx->requestId(),
            'crm_id'     => $response->json('id'),
        ]);
    }
}
```

Laravel's container auto-wires `MyCrmGateway` — no binding needed, as long as all its constructor parameters are resolvable (they are: `BaseApiGateway` deps are all singletons).

---

## Scenario B: New Client (same API, new credentials)

Use this when `AutoCrmGateway` (or another gateway class) already exists and a new brand/client needs the same API under different credentials.

### Step 1 — Create a brand-specific gateway subclass

```php
// module/Proxy/Gateway/AutoCrm/MyBrandAutoCrmGateway.php
final class MyBrandAutoCrmGateway extends AutoCrmGateway
{
    public function __construct(
        ApiGatewayConfigRepository $configs,
        GuzzleApiTransport $transport,
        MockApiTransport $mockTransport,
        LoggerInterface $logger,
    ) {
        parent::__construct(
            configs: $configs,
            transport: $transport,
            mockTransport: $mockTransport,
            logger: $logger,
            gatewayName: 'mybrand_autocrm',   // ← new config key
        );
    }
}
```

No other code needed — all domain methods (`dealers()`, `createLead()`, etc.) are inherited from `AutoCrmGateway`.

### Step 2 — Add config entry

```php
// config/proxy.php → gateways
'mybrand_autocrm' => [
    'base_uri'        => env('PROXY_GATEWAY_MYBRAND_AUTOCRM_BASE_URI', ''),
    'timeout'         => (float) env('PROXY_GATEWAY_MYBRAND_AUTOCRM_TIMEOUT', 10),
    'connect_timeout' => (float) env('PROXY_GATEWAY_MYBRAND_AUTOCRM_CONNECT_TIMEOUT', 5),
    'mock'            => (bool) env('PROXY_GATEWAY_MYBRAND_AUTOCRM_MOCK', false),
    'auth' => [
        'type'  => env('PROXY_GATEWAY_MYBRAND_AUTOCRM_AUTH_TYPE', 'bearer'),
        'token' => env('PROXY_GATEWAY_MYBRAND_AUTOCRM_TOKEN'),
    ],
    'headers' => ['Accept' => 'application/json'],
],
```

### Step 3 — Add env vars

```dotenv
PROXY_GATEWAY_MYBRAND_AUTOCRM_BASE_URI=
PROXY_GATEWAY_MYBRAND_AUTOCRM_TOKEN=
PROXY_GATEWAY_MYBRAND_AUTOCRM_AUTH_TYPE=bearer
PROXY_GATEWAY_MYBRAND_AUTOCRM_MOCK=false
```

### Step 4 — Use the typed class in handlers

```php
final class MyBrandDealersHandler extends BaseDealersProxyHandler
{
    public function __construct(private readonly MyBrandAutoCrmGateway $autoCrm) {}

    public function handle(ProxyContext $ctx): ProxyResponse
    {
        $dealers = $this->autoCrm->dealers();
        // ...
    }
}
```

Inject `MyBrandAutoCrmGateway`, not the base `AutoCrmGateway` — this forces the correct credentials to be loaded from config.

---

## Mock mode

Set `PROXY_GATEWAY_MY_CRM_MOCK=true` to use `MockApiTransport` instead of making real HTTP calls. The mock transport returns an empty 200 response. Useful for local development without real CRM credentials.

---

## Verification

```bash
./vendor/bin/phpstan analyse module/Proxy --memory-limit=512M

# Test the gateway directly in tinker:
php artisan tinker
> app(\Module\Proxy\Gateway\MyCrm\MyCrmGateway::class)->dealers()
```

Check Laravel logs for the `Proxy gateway request started/finished` entries.

## References

See [references/add-new-proxy-gateway-checklist.md](references/add-new-proxy-gateway-checklist.md) for the quick checklist.
