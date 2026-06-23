# Proxy / Webhook Gateway

## Архитектура

`Module\Proxy` — внутренний gateway-слой. Принимает входящие HTTP-запросы, нормализует данные через `fields()` handler-а, валидирует, ведёт лог жизненного цикла и вызывает `handler->handle()`.

Поток обработки:

```
HTTP → ProxyReceiverService
  → FieldResolver (нормализация полей)
  → Validator (правила из fields())
  → Event(ProxyRequestAccepted)
  → handler->handle($context)       ← handler делает всё что нужно: вызывает gateway, возвращает ответ
  → Event(ProxyRequestProcessed)
```

При ошибке валидации — `Event(ProxyRequestRejected)`, при исключении — `Event(ProxyRequestFailed)`.

Два подписчика на события:
- `LogProxyRequestStatus` — обновляет `status` и `response` в `proxy_requests`
- `PersistProxyContext` — сохраняет снапшот контекста в `message_box` (для диагностики и восстановления после сбоев)

Gateway (`BaseApiGateway` и его наследники) — чистый HTTP-клиент. Ничего не знает про proxy-слой.

## Управление эндпоинтами

Записи эндпоинтов хранятся в `proxy_endpoints`. Единственная точка истины — `module/Proxy/Registry/ProxyRegistry.php`. UUID-ы зафиксированы там, поэтому каждое окружение (локальное, stage, prod) получает одинаковый UUID для одного и того же эндпоинта.

Чтобы добавить новый эндпоинт:

1. Создать handler-класс, наследующий `ProxyHandler`.
2. Добавить `yield`-запись в нужный Registry:

```php
yield new ProxyEndpointDefinition(
    uuid:         'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx',
    code:         'my_endpoint',
    name:         'Мой эндпоинт',
    description:  'Что делает этот эндпоинт',
    handlerClass: MyProxyHandler::class,
);
```

3. Запустить синхронизацию:

```bash
php artisan proxies:sync
```

Команда вставляет строки, которых ещё нет, и обновляет `name`, `description`, `handler_class`, `method` для существующих. `is_active` и `config` не трогает — ими управляет оператор через UI.

Включи `proxies:sync` в pipeline деплоя после `php artisan migrate`.

`is_active` и `config` управляет оператор через UI.

## Таблицы

`proxy_endpoints` — конфигурация эндпоинта: `uuid`, `name`, `code`, `is_active`, `handler_class`, `method`, `config`, метаданные владельца.

`proxy_requests` — каждый входящий запрос:

| Колонка | Тип | Описание |
|---|---|---|
| `id` | uuid | PK |
| `proxy_endpoint_id` | uuid | FK на `proxy_endpoints` |
| `request_id` | string | Внутренний UUID попытки |
| `status` | enum | `received` → `accepted` → `processed` / `rejected` / `failed` |
| `request` | jsonb | Входящие данные: `method`, `path`, `ip`, `user_agent`, `headers`, `query`, `payload` |
| `normalized_data` | jsonb | Нормализованные поля после `FieldResolver` |
| `response` | jsonb | Ответ: `{status_code, headers, body}` или `{error}` |
| `message_box` | jsonb | Снапшот `ProxyContext` для диагностики и восстановления |
| `received_at` | datetime | Время получения |
| `processed_at` | datetime | Время завершения |

### Статусы

| Статус | Описание |
|---|---|
| `received` | Запрос получен и записан в БД |
| `accepted` | Прошёл валидацию, handler начал выполнение |
| `processed` | Handler вернул ответ без исключений |
| `rejected` | Не прошёл валидацию |
| `failed` | Handler бросил исключение |

`accepted` нужен для обнаружения краш-сбоев: если запрос завис в `accepted` — произошёл деплой или падение во время выполнения.

## Request ID

Каждый входящий запрос получает сгенерированный внутренний UUID (`request_id`). Входящий `X-Request-Id` хранится как `external_request_id` в `message_box.meta`. В ответах всегда присутствует заголовок `X-Request-Id`.

## ProxyContext

`ProxyContext` — иммутабельный readonly DTO, который собирается из входящего запроса и передаётся в `handler->handle()`. Содержит все данные запроса, нормализованные поля, конфигурацию эндпоинта и системные метаданные.

### Источники данных

```
Входящий HTTP-запрос
      │
      ├─ payload   — тело (JSON или form-data)
      ├─ query     — query string
      ├─ headers   — HTTP-заголовки (ключи в нижнем регистре)
      ├─ files     — загруженные файлы (UploadedFile)
      │
      └─ FieldResolver (маппинг через fields())
             │
             └─ data  — нормализованные поля (то, что handler читает в первую очередь)

endpoint.config → config  — конфиг из БД (заполняет оператор через UI)
system          → request_id, ip, received_at
```

### API

```php
// Нормализованные поля (source-маппинг через fields())
$context->data('name')                   // конкретное поле
$context->data('address.city')           // dot-нотация
$context->data()                         // весь массив data

// Сырые данные запроса
$context->payload('nested.key')
$context->query('utm_source')
$context->header('x-signature')          // всегда нижний регистр
$context->file('avatar')                 // ?UploadedFile (одиночный)
$context->fileList('photos')             // array<UploadedFile> (множество)

// Конфигурация эндпоинта из proxy_endpoints.config
$context->config('belgee.autocrm.brand_id')
$context->config()                       // весь массив

// Системные метаданные
$context->requestId()                    // UUID этого запроса
$context->system('ip')
$context->system('received_at')

// Идентификаторы
$context->endpoint->uuid
$context->endpoint->code
$context->proxyRequest->id

// Meta — произвольные данные, накапливаемые внутри handler-а
// meta() иммутабелен: возвращает новый ProxyContext с добавленным ключом
$context2 = $context->meta('crm_id', 42);   // запись → новый экземпляр
$context->meta('crm_id');                    // чтение → mixed
```

### data vs payload

`data` — предпочтительный источник. Содержит уже нормализованные значения: handler объявил поле с `source('query.utm')`, и `data('utm')` вернёт значение из query string — handler не думает, откуда пришли данные.

`payload` — сырое тело запроса. Используй только если нужен доступ к полю, которое не объявлено через `fields()`.

### Пример handler-а

```php
public function handle(ProxyContext $context): ProxyResponse
{
    $phone   = $context->data('phone');
    $source  = $context->data('source', 'web');
    $brandId = $context->config('autocrm.brand_id');

    $result = $this->crm->createLead([
        'phone'    => $phone,
        'source'   => $source,
        'brand_id' => $brandId,
        'ip'       => $context->system('ip'),
    ]);

    return ProxyResponse::accepted([
        'request_id' => $context->requestId(),
        'crm_id'     => $result['id'],
    ]);
}
```

## Outbound Gateway

`Module\Proxy\Gateway\Base` — переиспользуемая инфраструктура исходящих HTTP-вызовов. Реализована на Guzzle. Каждая операция — отдельный method-класс, реализующий `ApiMethod`. Конфиги сервисов — в `config/proxy.php → gateways`.

Gateway — обычный клиент. Он не знает про `ProxyContext`, события или lifecycle запроса. Handler внедряет gateway через конструктор и вызывает его методы напрямую.

Поддерживаемые типы авторизации: `none`, `basic`, `bearer`, `headers`.

### Вариант 1. ApiGatewayFactory прямо в handler-е

Подходит, когда метод один-два и выделять отдельный класс избыточно.

**config/proxy.php:**

```php
'gateways' => [
    'my_service' => [
        'base_uri'        => env('MY_SERVICE_BASE_URI', ''),
        'timeout'         => 10.0,
        'connect_timeout' => 5.0,
        'mock'            => (bool) env('MY_SERVICE_MOCK', false),
        'auth'            => [
            'type'  => 'bearer',
            'token' => env('MY_SERVICE_TOKEN'),
        ],
        'headers' => ['Accept' => 'application/json'],
    ],
],
```

**Handler:**

```php
use Module\Proxy\Gateway\Base\Methods\PostJsonMethod;
use Module\Proxy\Gateway\Base\Services\ApiGatewayFactory;

final readonly class MyProxyHandler extends ProxyHandler
{
    public function __construct(private ApiGatewayFactory $gateways) {}

    public function handle(ProxyContext $context): ProxyResponse
    {
        $response = $this->gateways
            ->make('my_service')
            ->send(new PostJsonMethod('/leads', [
                'phone' => $context->data('phone'),
                'name'  => $context->data('name'),
            ], key: 'create-lead'));

        return ProxyResponse::accepted([
            'request_id' => $context->requestId(),
            'lead_id'    => $response->json('id'),
        ]);
    }
}
```

### Вариант 2. Типизированный gateway-класс

Подходит, когда методов несколько или gateway шарится между handler-ами.

```php
// module/Proxy/Gateway/MyService/MyServiceGateway.php
class MyServiceGateway extends BaseApiGateway
{
    public function __construct(
        ApiGatewayConfigRepository $configs,
        GuzzleApiTransport $transport,
        MockApiTransport $mockTransport,
        LoggerInterface $logger,
        string $gatewayName = 'my_service',
    ) {
        parent::__construct($configs->get($gatewayName), $transport, $mockTransport, $logger);
    }

    /** @return array<string, mixed> */
    public function createLead(array $data): array
    {
        $response = $this->send(new PostJsonMethod('/leads', $data, key: 'create-lead'));
        return $response->json() ?? [];
    }
}
```

Handler внедряет gateway через конструктор:

```php
final readonly class MyProxyHandler extends ProxyHandler
{
    public function __construct(private MyServiceGateway $gateway) {}

    public function handle(ProxyContext $context): ProxyResponse
    {
        $lead = $this->gateway->createLead(['phone' => $context->data('phone')]);
        return ProxyResponse::accepted(['lead_id' => $lead['id'] ?? null]);
    }
}
```

### Вариант 3. Несколько учётных данных для одного API

Когда один API используется для разных клиентов с разными ключами — наследуем gateway-класс и передаём другой `gatewayName`. Родительский класс **не должен** быть `final`.

```php
final class ClientAGateway extends MyServiceGateway
{
    public function __construct(
        ApiGatewayConfigRepository $configs,
        GuzzleApiTransport $transport,
        MockApiTransport $mockTransport,
        LoggerInterface $logger,
    ) {
        parent::__construct($configs, $transport, $mockTransport, $logger, gatewayName: 'client_a');
    }
}
```

Конфиг:

```php
'gateways' => [
    'client_a' => ['base_uri' => env('CLIENT_A_URI'), 'auth' => ['type' => 'bearer', 'token' => env('CLIENT_A_TOKEN')], ...],
    'client_b' => ['base_uri' => env('CLIENT_B_URI'), 'auth' => ['type' => 'basic', 'username' => env('CLIENT_B_USER'), 'password' => env('CLIENT_B_PASS')], ...],
],
```

### Method-классы

Встроенные:

| Класс | HTTP-метод | Параметры конструктора |
|---|---|---|
| `GetJsonMethod` | GET | `uri`, `query[]`, `key` |
| `PostJsonMethod` | POST | `uri`, `body[]`, `query[]`, `key` |

`key` — строковый идентификатор вызова, нужен для mock в тестах. Если не задан — используется имя класса + URI.

Кастомный method-класс (нестандартный HTTP-метод, особые заголовки):

```php
use Module\Proxy\Gateway\Base\Methods\AbstractApiMethod;

final readonly class PatchLeadMethod extends AbstractApiMethod
{
    public function __construct(private int $id, private array $data) {}

    public function key(): string    { return "patch-lead:{$this->id}"; }
    public function method(): string { return 'PATCH'; }
    public function uri(): string    { return "/leads/{$this->id}"; }
    public function body(): mixed    { return $this->data; }
}
```

### ApiGatewayResponse

```php
$response->statusCode          // int
$response->successful()        // statusCode 2xx
$response->body                // mixed (уже decoded JSON)
$response->json()              // array (безопасно, всегда array)
$response->json('data.id')     // dot-нотация
$response->header('x-token')   // case-insensitive
```

### Mock в тестах

```php
$factory = app(ApiGatewayFactory::class);
$factory->mock()->fake('my_service', 'create-lead', new ApiGatewayResponse(201, ['id' => 42]));
```

Или через `config/proxy.php` для окружения:

```php
'mock' => (bool) env('MY_SERVICE_MOCK', false),
```

## Поля handler-а

Handler объявляет контракт входных данных через метод `fields()`. Поля используются для трёх целей: нормализация (маппинг из источника), валидация и документация через API.

### API endpoints

**Публичный (по UUID, только активные, без авторизации):**

```
GET /api/proxies/{uuid}/fields
```

**Внутренний (по id, требует авторизации):**

```
GET /api/proxy/proxys/{id}/fields
```

Оба возвращают:

```json
{
  "items": [
    {
      "key": "phone",
      "label": "Телефон",
      "source": "payload.phone",
      "type": "string",
      "required": true,
      "nullable": false,
      "default": null,
      "rules": ["required", "string", "max:30"],
      "example": "+79990000000",
      "description": null,
      "filterable": false,
      "filter_key": null
    }
  ]
}
```

### Типы полей

| Класс | `type` в JSON | Особенности |
|---|---|---|
| `ProxyField` | (задаётся вручную) | Базовый класс |
| `ProxyFieldString` | `string` | — |
| `ProxyFieldInteger` | `integer` | — |
| `ProxyFieldBoolean` | `boolean` | Добавляет правило `boolean` |
| `ProxyFieldList` | `list` | Поддерживает `->values([...])` для допустимых значений |
| `ProxyFieldArray` | `array` | Добавляет правило `array` |
| `ProxyFieldArrayList` | `array_list` | Добавляет правила `array`, `list` |
| `ProxyFieldFileUrl` | `file_url` | Строковый URL файла; правила `string`, `url`, `max:2048` |
| `ProxyFieldFileUrlList` | `file_url_list` | Массив URL; правило `array` |
| `ProxyFieldUpload` | `file` | Источник `files.{key}`, мультипарт |
| `ProxyFieldUploadList` | `file_list` | Источник `files.{key}`, мультипарт |

### Builder-методы

```php
ProxyFieldString::make('phone')
    ->label('Телефон')             // отображаемое имя (default = key)
    ->source('payload.phone')      // откуда брать значение (default = payload.{key})
    ->required()                   // поле обязательно
    ->nullable()                   // поле опционально
    ->default('+7')                // значение по умолчанию
    ->rules(['string', 'max:30'])  // правила валидации
    ->example('+79990000000')      // пример для документации
    ->description('...')
    ->filterable('phone_filter')   // участвует в фильтрации; опциональный alias
```

### Source paths

| Префикс | Источник |
|---|---|
| `payload.{path}` | тело запроса (JSON или form-data) |
| `query.{path}` | query string |
| `headers.{name}` | HTTP-заголовок (нижний регистр) |
| `system.{path}` | системные метаданные (`request_id`, `ip`, `received_at`) |
| `files.{key}` | загруженный файл (`UploadedFile`) |

## Безопасность HandlerResolver

`HandlerResolver` проверяет handler-класс перед инстанциированием:

1. Класс должен существовать (`class_exists`).
2. Класс должен начинаться с `proxy.handler_namespace` (по умолчанию `Module\Proxy\Proxies\`) **или** быть в списке `proxy.allowed_handlers`.
3. Класс должен быть наследником `ProxyHandler`.

Классы за пределами доверенного namespace добавляются в `config/proxy.php`:

```php
'allowed_handlers' => [
    \Some\Other\Ns\MyHandler::class,
],
```

## AutoCRM Gateway

`Module\Proxy\Gateway\AutoCrm\AutoCrmGateway` — типизированный клиент AutoCRM API. Специфичные подклассы используют собственный именованный конфиг: `BelgeeAutoCrmGateway` → `proxy.gateways.belgee_autocrm`, `MotorinvestAutoCrmGateway` → `proxy.gateways.motorinvest_autocrm`.

Доступные методы:

```php
$gateway->brands(AutoCrmListQuery::make());
$gateway->brand(1);
$gateway->cities(AutoCrmListQuery::make(['country_id' => 1]));
$gateway->city(1);
$gateway->dealers(AutoCrmListQuery::make(['status' => 'active']));
$gateway->dealer(1);
$gateway->distributors(AutoCrmListQuery::make(['country_id[]' => [1]]));
$gateway->distributor(1);
$gateway->executorCategories(AutoCrmListQuery::make());
$gateway->executorCategory(1);
$gateway->interests(AutoCrmListQuery::make(['request_type_id' => 1]));
$gateway->interest(1);
$gateway->createInterest(InterestForm::fromArray([...]));
$gateway->models(AutoCrmListQuery::make(['is_recent' => 1]));
$gateway->model(1, expand: 'country_ids');
$gateway->requestTypes(AutoCrmListQuery::make());
$gateway->requestType(1);
$gateway->results(AutoCrmListQuery::make(['for_lead' => 1]));
$gateway->result(1);
$gateway->createLead([...]);
```

List-методы автоматически обходят страницы через заголовок `x-pagination-page-count`.

Учётные данные из переменных окружения:

```dotenv
PROXY_GATEWAY_BELGEE_AUTOCRM_BASE_URI=https://belgee-autocrm.example
PROXY_GATEWAY_BELGEE_AUTOCRM_AUTH_TYPE=bearer
PROXY_GATEWAY_BELGEE_AUTOCRM_TOKEN=...
```

Бизнес-маппинг хранится в `proxy_endpoints.config`, а не в env:

```json
{
  "belgee": {
    "autocrm": {
      "brand_id": 10,
      "country_id": 1,
      "request_type_id": 20,
      "source_id": 40,
      "result_id": 30,
      "dealer_code": "D001"
    }
  }
}
```
