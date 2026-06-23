<?php

declare(strict_types=1);

namespace Module\Proxy\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Module\Proxy\DTO\ProxyRequestIndexData;
use Module\Proxy\Models\ProxyRequest;

final class ProxyRequestQueryService
{
    /**
     * @return LengthAwarePaginator<int, ProxyRequest>
     */
    public function paginate(ProxyRequestIndexData $filters): LengthAwarePaginator
    {
        return ProxyRequest::query()
            ->with('endpoint')
            ->latest()
            ->forEndpoint($filters->endpointId)
            ->forStatus($filters->status)
            ->search($filters->search)
            ->paginate($filters->perPage, ['*'], 'page[number]');
    }

    public function find(ProxyRequest $request): ProxyRequest
    {
        return $request->load('endpoint');
    }
}
