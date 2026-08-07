<?php

declare(strict_types=1);

namespace Module\Proxy\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Module\Proxy\DTO\ProxyEndpointData;
use Module\Proxy\DTO\ProxyEndpointIndexData;
use Module\Proxy\DTO\ProxyField;
use Module\Proxy\Http\Requests\ProxyEndpointRequest;
use Module\Proxy\Http\Resources\JsonApi\ProxyEndpointResource;
use Module\Proxy\Http\Resources\JsonApi\ProxyFieldResource;
use Module\Proxy\Http\Resources\JsonApi\ProxyHandlerResource;
use Module\Proxy\Models\ProxyEndpoint;
use Module\Proxy\Proxies\HandlerCatalog;
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
        foreach ($handler->requestFields() as $field) {
            if ($field instanceof ProxyField) {
                $fields[] = $field->toArray();
            }
        }

        return ProxyFieldResource::collection($fields);
    }

    public function handlers(): AnonymousResourceCollection
    {
        $items = array_map(function (array $handler): array {
            $resolved = $this->handlers->resolveClass($handler['class']);

            return [
                'class' => $handler['class'],
                'label' => $handler['label'],
                'group' => $handler['group'],
                'credential_type' => $resolved->credentialType(),
                'method' => $resolved->method(),
            ];
        }, HandlerCatalog::options());

        return ProxyHandlerResource::collection($items);
    }

    public function credentialSchema(): AnonymousResourceCollection
    {
        return ProxyFieldResource::collection(array_map(
            static fn (ProxyField $field): array => $field->toArray(),
            IntegrationCredentials::fields(),
        ));
    }
}
