# Add New Proxy Gateway — Quick Checklist

## Scenario B: New client on existing gateway (most common)

- [ ] Create `module/Proxy/Gateway/<Service>/<Brand><Service>Gateway.php`
  - [ ] `extends <Service>Gateway`
  - [ ] Constructor calls `parent::__construct(..., gatewayName: '<brand>_<service>')`
- [ ] Add `'<brand>_<service>'` block to `config/proxy.php → gateways`
- [ ] Add env vars to `.env.example` with prefix `PROXY_GATEWAY_<BRAND>_<SERVICE>_`
- [ ] Inject typed subclass (not base gateway) into handler constructor

## Scenario A: Completely new external API gateway

### Method objects (`module/Proxy/Gateway/<ServiceName>/Methods/`)

- [ ] One class per API endpoint, `final readonly`, extends `AbstractApiMethod`
- [ ] Override only what differs: `key()`, `method()`, `uri()`, `query()`, `body()`
- [ ] `key()` — dot-notation log key unique to this gateway, e.g. `my_crm.lead.create`
- [ ] `body()` returns `array<string, mixed>` for POST methods

### Response DTO (`module/Proxy/Gateway/<ServiceName>/DTO/`)

- [ ] `final readonly class <ServiceName>Data` with `array $data`
- [ ] Static `fromArray(array $data): self` factory
- [ ] `id(): int|string|null` and `get(string $key, mixed $default = null): mixed`
- [ ] (Optional) `<ServiceName>ListQuery` if the API has paginated list endpoints

### Gateway class (`module/Proxy/Gateway/<ServiceName>/<ServiceName>Gateway.php`)

- [ ] `final class <ServiceName>Gateway extends BaseApiGateway`
- [ ] Constructor: accepts `ApiGatewayConfigRepository`, `GuzzleApiTransport`, `MockApiTransport`, `LoggerInterface`, and `string $gatewayName = '<snake_case_key>'`
- [ ] Each public method wraps `$this->send(new XxxMethod(...))` and returns typed result
- [ ] No raw `$this->send()` calls from handlers — expose domain methods only

### Config (`config/proxy.php → gateways`)

- [ ] Key matches `$gatewayName` default in the gateway constructor
- [ ] Fields: `base_uri`, `timeout`, `connect_timeout`, `mock`, `auth`, `headers`
- [ ] `auth.type`: `none` | `bearer` | `basic` | `headers`

### Env vars (`.env.example`)

- [ ] `PROXY_GATEWAY_<SERVICE>_BASE_URI=`
- [ ] `PROXY_GATEWAY_<SERVICE>_TOKEN=` (if bearer)
- [ ] `PROXY_GATEWAY_<SERVICE>_USERNAME=` + `_PASSWORD=` (if basic)
- [ ] `PROXY_GATEWAY_<SERVICE>_AUTH_TYPE=bearer`
- [ ] `PROXY_GATEWAY_<SERVICE>_TIMEOUT=10`
- [ ] `PROXY_GATEWAY_<SERVICE>_CONNECT_TIMEOUT=5`
- [ ] `PROXY_GATEWAY_<SERVICE>_MOCK=false`

## Decision Table

| Situation | What to do |
|---|---|
| Same API, different brand credentials | Scenario B — subclass with new `gatewayName` |
| Brand-new external service URL | Scenario A — full gateway |
| Paginated list endpoint | Abstract `ListPageMethod`, call in a `for` loop like `AutoCrmGateway::list()` |
| No auth needed | `auth.type = 'none'`, no token/username env vars |
| Custom header auth (e.g., `X-Api-Key`) | `auth.type = 'headers'`, put headers in `auth.headers` map |
| Need to test without real credentials | Set `PROXY_GATEWAY_..._MOCK=true` — `MockApiTransport` returns empty 200 |
| No binding needed in ServiceProvider | Correct — Laravel auto-wires concrete gateway classes |

## Verification

```bash
./vendor/bin/phpstan analyse module/Proxy --memory-limit=512M
php artisan tinker --execute="app(\Module\Proxy\Gateway\MyCrm\MyCrmGateway::class)->dealers()"
```
