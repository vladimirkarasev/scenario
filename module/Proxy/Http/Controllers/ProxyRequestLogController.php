<?php

declare(strict_types=1);

namespace Module\Proxy\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Module\Proxy\DTO\ProxyRequestIndexData;
use Module\Proxy\Http\Resources\JsonApi\ProxyRequestResource;
use Module\Proxy\Models\ProxyRequest;
use Module\Proxy\Services\ProxyRequestQueryService;

final class ProxyRequestLogController extends Controller
{
    public function __construct(
        private readonly ProxyRequestQueryService $service,
    ) {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        return ProxyRequestResource::collection(
            $this->service->paginate(ProxyRequestIndexData::fromRequest($request)),
        );
    }

    public function show(ProxyRequest $proxyRequest): ProxyRequestResource
    {
        return new ProxyRequestResource($this->service->find($proxyRequest));
    }
}
