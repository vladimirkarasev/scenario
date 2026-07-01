<?php

declare(strict_types=1);

namespace Module\Proxy\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Module\Proxy\DTO\ProxyField;
use Module\Proxy\Enums\ProxyEndpointType;
use Module\Proxy\Http\Resources\JsonApi\ProxyEndpointResource;
use Module\Proxy\Models\ProxyConnection;
use Module\Proxy\Models\ProxyEndpoint;
use Module\Proxy\Services\HandlerCatalog;
use Module\Proxy\Services\HandlerResolver;
use Module\Proxy\Support\IntegrationCredentials;
use Module\Projects\CurrentProject;

/**
 * CRUD интеграций: одна запись = обработчик + доступы + mock + публичный uuid.
 * Обработчик выбирается из {@see HandlerCatalog}, доступы шифруются и маскируются.
 * Ответы — по JSON:API (data/attributes + meta-пагинация).
 */
final class ProxyEndpointController extends Controller
{
    public function __construct(
        private readonly HandlerResolver $handlers,
        private readonly CurrentProject $currentProject,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filter = $request->array('filter');
        $search = isset($filter['search']) && is_string($filter['search']) && trim($filter['search']) !== ''
            ? trim($filter['search'])
            : null;
        $type = isset($filter['type']) && is_string($filter['type']) && trim($filter['type']) !== ''
            ? trim($filter['type'])
            : null;

        /** @var list<string> $categoryIds */
        $categoryIds = array_values(array_filter(
            (array) ($filter['category_ids'] ?? []),
            static fn (mixed $id): bool => is_string($id) && $id !== '',
        ));

        $perPage = max(1, min(100, $request->integer('page.size', 20)));

        $query = ProxyEndpoint::query()->with('categories')->latest();

        // Скоуп по текущему проекту: в контексте проекта видны только его интеграции.
        $projectId = $this->currentProject->id();
        if ($projectId !== null) {
            $query->where('project_id', $projectId);
        }

        if ($type !== null) {
            $query->where('type', $type);
        }

        if ($categoryIds !== []) {
            $query->whereHas('categories', static function (Builder $q) use ($categoryIds): void {
                $q->whereIn('categories.id', $categoryIds);
            });
        }

        if ($search !== null) {
            $like = '%'.mb_strtolower($search).'%';
            $query->where(static function (Builder $q) use ($like): void {
                $q->whereRaw('LOWER(name) like ?', [$like])
                    ->orWhereRaw('LOWER(code) like ?', [$like]);
            });
        }

        return ProxyEndpointResource::collection($query->paginate($perPage, ['*'], 'page[number]'));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $endpoint = new ProxyEndpoint([
            'project_id' => $this->currentProject->id(),
            'uuid' => (string) Str::uuid(),
            'name' => $data['name'],
            'code' => $data['code'],
            'type' => $data['type'] ?? ProxyEndpointType::Webhook->value,
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'is_mocked' => $data['is_mocked'] ?? false,
            'handler_class' => $data['handler_class'],
            'connection_id' => $this->resolveConnectionId($data),
            'method' => $data['method'] ?? 'POST',
            'config' => $data['config'] ?? [],
            'mock_responses' => $data['mock_responses'] ?? null,
            'created_by' => $request->user()?->id,
        ]);

        $this->applyCredentials($endpoint, $this->incomingCredentials($data));
        $endpoint->save();

        $endpoint->categories()->sync($this->categoryPivot($this->categoryIds($data)));
        $endpoint->load('categories');

        return (new ProxyEndpointResource($endpoint))->response()->setStatusCode(201);
    }

    public function show(ProxyEndpoint $proxy): ProxyEndpointResource
    {
        $proxy->load('categories');

        return new ProxyEndpointResource($proxy);
    }

    public function update(Request $request, ProxyEndpoint $proxy): ProxyEndpointResource
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
            'connection_id' => $this->resolveConnectionId($data),
            'method' => $data['method'] ?? $proxy->method,
            'config' => $data['config'] ?? $proxy->config,
            'mock_responses' => $data['mock_responses'] ?? $proxy->mock_responses,
        ]);

        $this->applyCredentials($proxy, $this->incomingCredentials($data));
        $proxy->save();

        if (array_key_exists('category_ids', $data)) {
            $proxy->categories()->sync($this->categoryPivot($this->categoryIds($data)));
        }
        $proxy->load('categories');

        return new ProxyEndpointResource($proxy);
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

    /** Каталог обработчиков для селекта в UI. JSON:API: filter[search], page[number], page[size]. */
    public function handlers(Request $request): JsonResponse
    {
        $filter = $request->array('filter');
        $search = isset($filter['search']) && is_string($filter['search']) && trim($filter['search']) !== ''
            ? trim($filter['search'])
            : null;

        $page = max(1, $request->integer('page.number', 1));
        $perPage = max(1, min(100, $request->integer('page.size', 20)));

        $result = HandlerCatalog::search($search, $page, $perPage);
        $total = $result['total'];

        return new JsonResponse([
            'data' => array_map(function (array $o): array {
                $credentialType = $this->handlers->resolveClass($o['class'])->credentialType();

                return [
                    'type' => 'proxy-handlers',
                    'id' => $o['class'],
                    'attributes' => [
                        'label' => $o['label'],
                        'group' => $o['group'],
                        'credential_type' => $credentialType,
                    ],
                ];
            }, $result['items']),
            'meta' => [
                'current_page' => $page,
                'last_page' => max(1, (int) ceil($total / $perPage)),
                'per_page' => $perPage,
                'total' => $total,
            ],
        ]);
    }

    /** Схема полей доступов (фиксированная) для формы интеграции. */
    public function credentialSchema(): JsonResponse
    {
        return new JsonResponse([
            'items' => array_map(
                static fn (ProxyField $field): array => $field->toArray(),
                IntegrationCredentials::fields(),
            ),
        ]);
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
        foreach (IntegrationCredentials::secretKeys() as $key) {
            if (! filled($values[$key] ?? null) && array_key_exists($key, $existing)) {
                $values[$key] = $existing[$key];
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

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function categoryIds(array $data): array
    {
        $ids = $data['category_ids'] ?? [];

        if (! is_array($ids)) {
            return [];
        }

        return array_values(array_filter(
            $ids,
            static fn (mixed $id): bool => is_string($id) && $id !== '',
        ));
    }

    /**
     * Проверяет совместимость доступа с обработчиком и возвращает connection_id.
     * Привязать можно только доступ типа, который требует обработчик
     * ({@see \Module\Proxy\ProxyHandler::credentialType()}). Для обработчиков без доступа
     * (mock/тест) connection_id должен быть пустым.
     *
     * @param  array<string, mixed>  $data
     */
    private function resolveConnectionId(array $data): ?int
    {
        $rawId = $data['connection_id'] ?? null;
        $connectionId = is_numeric($rawId) ? (int) $rawId : null;

        $handlerClass = is_string($data['handler_class'] ?? null) ? $data['handler_class'] : '';
        $required = $this->handlers->resolveClass($handlerClass)->credentialType();

        if ($connectionId === null) {
            return null;
        }

        if ($required === null) {
            throw ValidationException::withMessages([
                'connection_id' => 'Этот обработчик не использует доступы.',
            ]);
        }

        $connection = ProxyConnection::query()->find($connectionId);

        if ($connection === null || $connection->credential_type !== $required) {
            throw ValidationException::withMessages([
                'connection_id' => 'Доступ не подходит выбранному обработчику.',
            ]);
        }

        return $connectionId;
    }

    /**
     * @param  list<string>  $categoryIds
     * @return array<string, array{project_id: string|null}>
     */
    private function categoryPivot(array $categoryIds): array
    {
        $projectId = $this->currentProject->id();

        $pivot = [];
        foreach ($categoryIds as $id) {
            $pivot[$id] = ['project_id' => $projectId];
        }

        return $pivot;
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
            'handler_class' => ['required', 'string', Rule::in(array_keys(HandlerCatalog::all()))],
            'connection_id' => ['nullable', 'integer', 'exists:proxy_connections,id'],
            'category_ids' => ['sometimes', 'array'],
            'category_ids.*' => ['required', 'uuid', 'exists:categories,id'],
            'credentials' => ['nullable', 'array'],
            'config' => ['nullable', 'array'],
            'is_mocked' => ['sometimes', 'boolean'],
            'mock_responses' => ['nullable', 'array'],
            'mock_responses.*.name' => ['nullable', 'string', 'max:255'],
            'mock_responses.*.status' => ['required_with:mock_responses.*', 'integer', 'between:100,599'],
            'mock_responses.*.body' => ['nullable', 'array'],
            'mock_responses.*.headers' => ['nullable', 'array'],
            'mock_responses.*.is_active' => ['boolean'],
        ]);
    }
}
