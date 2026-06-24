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
use Module\Proxy\Enums\ProxyEndpointType;
use Module\Proxy\Models\ProxyEndpoint;
use Module\Proxy\Services\HandlerResolver;

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

        // При поиске (например, выпадающий фильтр с большим числом эндпоинтов)
        // ищем по name/code и ограничиваем выдачу. Без поиска — полный список.
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

        $endpoint = ProxyEndpoint::query()->create([
            ...$data,
            'uuid' => (string) Str::uuid(),
            'created_by' => $request->user()?->id,
        ]);

        return new JsonResponse(['item' => $this->payload($endpoint)], 201);
    }

    public function show(ProxyEndpoint $proxy): JsonResponse
    {
        return new JsonResponse(['item' => $this->payload($proxy)]);
    }

    public function update(Request $request, ProxyEndpoint $proxy): JsonResponse
    {
        $proxy->update($this->validated($request));
        $proxy->refresh();

        return new JsonResponse(['item' => $this->payload($proxy)]);
    }

    public function destroy(ProxyEndpoint $proxy): JsonResponse
    {
        $proxy->delete();

        return new JsonResponse(status: 204);
    }

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

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        /** @var array<string, mixed> */
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:255'],
            'type' => ['sometimes', Rule::enum(ProxyEndpointType::class)],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'is_mocked' => ['sometimes', 'boolean'],
            'handler_class' => ['required', 'string', 'max:512'],
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
        return [
            'id' => $endpoint->id,
            'uuid' => $endpoint->uuid,
            'name' => $endpoint->name,
            'code' => $endpoint->code,
            'type' => $endpoint->type->value,
            'description' => $endpoint->description,
            'is_active' => $endpoint->is_active,
            'is_mocked' => $endpoint->is_mocked,
            'handler_class' => $endpoint->handler_class,
            'config' => $endpoint->config ?? [],
            'mock_responses' => $endpoint->mock_responses ?? [],
            'updated_at' => $endpoint->updated_at?->toISOString(),
        ];
    }
}
