<?php

declare(strict_types=1);

namespace Module\Proxy\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Module\Proxy\DTO\ProxyEndpointData;
use Module\Proxy\DTO\ProxyEndpointIndexData;
use Module\Proxy\DTO\ProxyField;
use Module\Proxy\Http\Requests\ProxyEndpointRequest;
use Module\Proxy\Http\Resources\JsonApi\ProxyEndpointResource;
use Module\Proxy\Http\Resources\JsonApi\ProxyFieldResource;
use Module\Proxy\Http\Resources\JsonApi\ProxyHandlerResource;
use Module\Proxy\Models\ProxyEndpoint;
use Module\Proxy\Services\HandlerCatalog;
use Module\Proxy\Services\HandlerResolver;
use Module\Proxy\Services\ProxyEndpointService;
use Module\Proxy\Support\IntegrationCredentials;

final readonly class ProxyEndpointController
{
    public function __construct(
        private ProxyEndpointService $service,
        private HandlerResolver $handlers,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return ProxyEndpointResource::collection(
            $this->service->paginate(ProxyEndpointIndexData::fromRequest($request)),
        );
    }

    public function store(ProxyEndpointRequest $request): JsonResponse
    {
        $endpoint = $this->service->create(
            ProxyEndpointData::fromRequest($request),
            $request->user()?->id,
        );

        return new ProxyEndpointResource($endpoint)->response()->setStatusCode(201);
    }

    public function show(ProxyEndpoint $proxy): ProxyEndpointResource
    {
        return new ProxyEndpointResource($this->service->find($proxy));
    }

    public function update(ProxyEndpointRequest $request, ProxyEndpoint $proxy): ProxyEndpointResource
    {
        return new ProxyEndpointResource(
            $this->service->update(ProxyEndpointData::fromRequest($request, $proxy), $proxy),
        );
    }

    public function destroy(ProxyEndpoint $proxy): JsonResponse
    {
        $this->service->delete($proxy);

        return new JsonResponse(status: 204);
    }

    public function fields(ProxyEndpoint $proxy): AnonymousResourceCollection
    {
        $handler = $this->handlers->resolve($this->service->find($proxy));
        $fields = [];
        foreach ($handler->fields() as $field) {
            if ($field instanceof ProxyField) {
                $fields[] = $field->toArray();
            }
        }

        return ProxyFieldResource::collection($fields);
    }

    public function handlers(Request $request): AnonymousResourceCollection
    {
        $filter = $request->array('filter');
        $search = isset($filter['search']) && is_string($filter['search']) && trim($filter['search']) !== ''
            ? trim($filter['search'])
            : null;
        $page = max(1, $request->integer('page.number', 1));
        $perPage = max(1, min(100, $request->integer('page.size', 20)));
        $result = HandlerCatalog::search($search, $page, $perPage);
        $items = array_map(fn (array $handler): array => [
            'class' => $handler['class'],
            'label' => $handler['label'],
            'group' => $handler['group'],
            'credential_type' => $this->handlers->resolveClass($handler['class'])->credentialType(),
        ], $result['items']);

        return ProxyHandlerResource::collection(new LengthAwarePaginator(
            $items,
            $result['total'],
            $perPage,
            $page,
            ['path' => $request->url(), 'pageName' => 'page[number]'],
        ));
    }

    public function credentialSchema(): AnonymousResourceCollection
    {
        return ProxyFieldResource::collection(array_map(
            static fn (ProxyField $field): array => $field->toArray(),
            IntegrationCredentials::fields(),
        ));
    }
}
