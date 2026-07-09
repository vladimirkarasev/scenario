<?php

declare(strict_types=1);

namespace Module\Proxy\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Module\Proxy\DTO\ProxyConnectionData;
use Module\Proxy\DTO\ProxyConnectionIndexData;
use Module\Proxy\Http\Requests\ProxyConnectionRequest;
use Module\Proxy\Http\Resources\JsonApi\ProxyConnectionResource;
use Module\Proxy\Http\Resources\JsonApi\ProxyCredentialTypeResource;
use Module\Proxy\Models\ProxyConnection;
use Module\Proxy\Services\CredentialCatalog;
use Module\Proxy\Services\ProxyConnectionService;

final class ProxyConnectionController extends Controller
{
    public function __construct(private readonly ProxyConnectionService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return ProxyConnectionResource::collection(
            $this->service->paginate(ProxyConnectionIndexData::fromRequest($request)),
        );
    }

    public function store(ProxyConnectionRequest $request): JsonResponse
    {
        $connection = $this->service->create(ProxyConnectionData::fromRequest($request));

        return (new ProxyConnectionResource($connection))->response()->setStatusCode(201);
    }

    public function show(ProxyConnection $connection): ProxyConnectionResource
    {
        return new ProxyConnectionResource($this->service->find($connection));
    }

    public function update(
        ProxyConnectionRequest $request,
        ProxyConnection $connection,
    ): ProxyConnectionResource {
        return new ProxyConnectionResource(
            $this->service->update(ProxyConnectionData::fromRequest($request), $connection),
        );
    }

    public function destroy(ProxyConnection $connection): JsonResponse
    {
        $this->service->delete($connection);

        return new JsonResponse(status: 204);
    }

    public function types(): AnonymousResourceCollection
    {
        return ProxyCredentialTypeResource::collection(CredentialCatalog::options());
    }
}
