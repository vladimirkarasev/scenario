<?php

declare(strict_types=1);

namespace Module\Proxy\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Module\Proxy\DTO\ProxyField;
use Module\Proxy\DTO\ProxyFieldString;
use Module\Proxy\Enums\ProxyEndpointType;
use Module\Proxy\Models\ProxyEndpoint;
use Module\Proxy\Services\HandlerCatalog;
use Module\Proxy\Services\HandlerResolver;

/**
 * CRUD интеграций: одна запись = обработчик + доступы + mock + публичный uuid.
 * Обработчик выбирается из {@see HandlerCatalog}, доступы шифруются и маскируются.
 */
final class ProxyEndpointController extends Controller
{
    public function __construct(private readonly HandlerResolver $handlers) {}

    public function index(Request $request): JsonResponse
    {
        $filter = $request->array('filter');
        $search = isset($filter['search']) && is_string($filter['search']) && trim($filter['search']) !== ''
            ? trim($filter['search'])
            : null;

        $type = isset($filter['type']) && is_string($filter['type']) && trim($filter['type']) !== ''
            ? trim($filter['type'])
            : null;

        $query = ProxyEndpoint::query()->latest();

        if ($type !== null) {
            $query->where('type', $type);
        }

        // При поиске (например, выпадающий фильтр с большим числом записей) ищем
        // по name/code и ограничиваем выдачу. Без поиска — полный список.
        if ($search !== null) {
            $like = '%'.mb_strtolower($search).'%';
            $query->where(static function (Builder $q) use ($like): void {
                $q->whereRaw('LOWER(name) like ?', [$like])
                    ->orWhereRaw('LOWER(code) like ?', [$like]);
            })->limit(50);
        }

        return new JsonResponse([
            'items' => $query->get()
                ->map(fn (ProxyEndpoint $endpoint) => $this->payload($endpoint))
                ->all(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $endpoint = new ProxyEndpoint([
            'uuid' => (string) Str::uuid(),
            'name' => $data['name'],
            'code' => $data['code'],
            'type' => $data['type'] ?? ProxyEndpointType::Webhook->value,
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'is_mocked' => $data['is_mocked'] ?? false,
            'handler_class' => $data['handler_class'],
            'method' => $data['method'] ?? 'POST',
            'config' => $data['config'] ?? [],
            'mock_responses' => $data['mock_responses'] ?? null,
            'created_by' => $request->user()?->id,
        ]);

        $this->applyCredentials($endpoint, $this->incomingCredentials($data));
        $endpoint->save();

        return new JsonResponse(['item' => $this->payload($endpoint)], 201);
    }

    public function show(ProxyEndpoint $proxy): JsonResponse
    {
        return new JsonResponse(['item' => $this->payload($proxy)]);
    }

    public function update(Request $request, ProxyEndpoint $proxy): JsonResponse
    {
        $data = $this->validated($request);

        $proxy->fill([
            'name' => $data['name'],
            'code' => $data['code'],
            'type' => $data['type'] ?? $proxy->type->value,
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? $proxy->is_active,
            'is_mocked' => $data['is_mocked'] ?? $proxy->is_mocked,
            'handler_class' => $data['handler_class'],
            'method' => $data['method'] ?? $proxy->method,
            'config' => $data['config'] ?? $proxy->config,
            'mock_responses' => $data['mock_responses'] ?? $proxy->mock_responses,
        ]);

        $this->applyCredentials($proxy, $this->incomingCredentials($data));
        $proxy->save();

        return new JsonResponse(['item' => $this->payload($proxy)]);
    }

    public function destroy(ProxyEndpoint $proxy): JsonResponse
    {
        $proxy->delete();

        return new JsonResponse(status: 204);
    }

    /** Входные поля обработчика (что присылает клиент). */
    public function fields(ProxyEndpoint $proxy): JsonResponse
    {
        $handler = $this->handlers->resolve($proxy);
        $items = [];

        foreach ($handler->fields() as $field) {
            if ($field instanceof ProxyField) {
                $items[] = $field->toArray();
            }
        }

        return new JsonResponse(['items' => $items]);
    }

    /** Каталог доступных обработчиков для селекта в UI. */
    public function handlers(): JsonResponse
    {
        return new JsonResponse(['items' => HandlerCatalog::options()]);
    }

    /** Схема полей доступов (фиксированная) для формы интеграции. */
    public function credentialSchema(): JsonResponse
    {
        return new JsonResponse([
            'items' => array_map(
                static fn (ProxyField $field): array => $field->toArray(),
                self::credentialFields(),
            ),
        ]);
    }

    /**
     * Фиксированная схема доступов интеграции. Единое место объявления полей —
     * UI рендерит форму по ней, контроллер по ней же шифрует/маскирует значения.
     *
     * @return array<int, ProxyField>
     */
    public static function credentialFields(): array
    {
        return [
            ProxyFieldString::make('base_uri')
                ->label('URL сервиса')
                ->example('https://crm.example.com/api/v1'),
            ProxyFieldString::make('bearer_token')
                ->label('Токен доступа')
                ->secret(),
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function applyCredentials(ProxyEndpoint $endpoint, array $values): void
    {
        // base_uri — отдельная колонка (не секрет, удобно показывать/искать).
        $baseUri = $values['base_uri'] ?? null;
        unset($values['base_uri']);
        $endpoint->base_uri = is_scalar($baseUri) && (string) $baseUri !== '' ? (string) $baseUri : null;

        // Секреты: пустое значение при update = «не менять», берём из сохранённых.
        $existing = $endpoint->credentials ?? [];
        foreach (self::credentialFields() as $field) {
            if (! $field->isSecret() || $field->key() === 'base_uri') {
                continue;
            }
            if (! filled($values[$field->key()] ?? null) && array_key_exists($field->key(), $existing)) {
                $values[$field->key()] = $existing[$field->key()];
            }
        }

        $endpoint->credentials = $values === [] ? null : $values;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function incomingCredentials(array $data): array
    {
        $values = $data['credentials'] ?? [];

        if (! is_array($values)) {
            return [];
        }

        $result = [];
        foreach ($values as $key => $value) {
            $result[(string) $key] = $value;
        }

        return $result;
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        /** @var array<string, mixed> */
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:255'],
            'type' => ['sometimes', Rule::enum(ProxyEndpointType::class)],
            'method' => ['sometimes', 'nullable', Rule::in(['GET', 'POST'])],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'is_mocked' => ['sometimes', 'boolean'],
            'handler_class' => ['required', 'string', Rule::in(array_keys(HandlerCatalog::all()))],
            'credentials' => ['nullable', 'array'],
            'config' => ['nullable', 'array'],
            'mock_responses' => ['nullable', 'array'],
            'mock_responses.*.name' => ['nullable', 'string', 'max:255'],
            'mock_responses.*.status' => ['required_with:mock_responses.*', 'integer', 'between:100,599'],
            'mock_responses.*.body' => ['nullable', 'array'],
            'mock_responses.*.headers' => ['nullable', 'array'],
            'mock_responses.*.match' => ['nullable', 'array'],
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(ProxyEndpoint $endpoint): array
    {
        $credentials = $endpoint->credentials ?? [];
        $masked = [];
        $secretFilled = [];

        $secretKeys = [];
        foreach (self::credentialFields() as $field) {
            if ($field->isSecret()) {
                $secretKeys[$field->key()] = true;
            }
        }

        foreach ($credentials as $key => $value) {
            if (isset($secretKeys[$key])) {
                $masked[$key] = null;
                $secretFilled[$key] = filled($value);

                continue;
            }
            $masked[$key] = $value;
        }

        return [
            'id' => $endpoint->id,
            'uuid' => $endpoint->uuid,
            'name' => $endpoint->name,
            'code' => $endpoint->code,
            'type' => $endpoint->type->value,
            'method' => $endpoint->method,
            'description' => $endpoint->description,
            'is_active' => $endpoint->is_active,
            'is_mocked' => $endpoint->is_mocked,
            'handler_class' => $endpoint->handler_class,
            'base_uri' => $endpoint->base_uri,
            'credentials' => $masked,
            'secret_filled' => $secretFilled,
            'config' => $endpoint->config ?? [],
            'mock_responses' => $endpoint->mock_responses ?? [],
            'receive_url' => route('proxy.proxies.receive', $endpoint->uuid),
            'updated_at' => $endpoint->updated_at?->toISOString(),
        ];
    }
}
