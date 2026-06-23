# Add New Proxy — Quick Checklist

Replace `MyClient`, `MyService`, `my_client_my_service` with actual names.

## Handler (`module/Proxy/Proxies/<Client>/<Service>/MyHandler.php`)

- [ ] Extends `ProxyHandler`
- [ ] `fields(): iterable` — yield one `ProxyFieldXxx` per expected input
  - [ ] Each field has `->label()`, `->required()` or `->nullable()`, `->rules([...])`
  - [ ] Fields reading from non-payload sources use `->source('query.key')` / `->source('headers.x-foo')`
  - [ ] Add `->example(...)` for the `/api/proxies/{uuid}/fields` documentation endpoint
- [ ] `handle(ProxyContext $ctx): ProxyResponse` — business logic
  - [ ] Read data via `$ctx->data('key')` (not `$ctx->payload()` — data is already validated/normalised)
  - [ ] Return `ProxyResponse::accepted(...)`, `ProxyResponse::ok(...)`, or `ProxyResponse::error(...)`
  - [ ] Always include `'request_id' => $ctx->requestId()` in the response body
- [ ] Namespace is under `Module\Proxy\Proxies\...` (security requirement)

## Registry (`module/Proxy/Registry/<Client>/MyClientRegistry.php`)

- [ ] Add (or create) `ProxyEndpointDefinition` with:
  - [ ] `uuid` — UUID v4, generated once, never changed
  - [ ] `code` — `snake_case`, unique across **all** registries
  - [ ] `name` + `description` — human-readable Russian text
  - [ ] `handlerClass` — fully qualified class name
  - [ ] `method` — `'POST'` (default) or `'GET'`

## Master Registry (`module/Proxy/Registry/ProxyRegistry.php`)

- [ ] Add `yield from MyClientRegistry::all();` if this is a new client registry

## Sync

- [ ] Run `php artisan proxies:sync`
- [ ] Verify the row appears in `proxy_endpoints` table with `is_active = 1`

## Smoke Test

```bash
curl -X POST http://localhost/api/proxies/<uuid> \
  -H "Content-Type: application/json" \
  -d '{"phone": "+79990000000"}'
```

- [ ] Response is `202` with `request_id`
- [ ] Row appears in `proxy_requests` table

## Decision Table

| Situation | What to do |
|---|---|
| Same fields shared across multiple client handlers | Extract abstract base handler, `yield from $this->sharedFields()` |
| Field comes from URL query string | `->source('query.param_name')` |
| Field comes from a request header | `->source('headers.x-header-name')` |
| Field is nested in JSON body | `->source('payload.parent.child')` |
| Endpoint needs config per deployment | Read via `$ctx->config('key')` — set in `proxy_endpoints.config` |
| Handler calls an external API (CRM, gateway) | Inject gateway service via constructor; register binding in `ProxyServiceProvider` |
| New client with many handlers | Create `module/Proxy/Registry/<Client>/<Client>ProxyRegistry.php` + yield from in `ProxyRegistry` |
