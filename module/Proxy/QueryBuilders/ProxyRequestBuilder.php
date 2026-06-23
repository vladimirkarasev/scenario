<?php

declare(strict_types=1);

namespace Module\Proxy\QueryBuilders;

use Illuminate\Database\Eloquent\Builder;
use Module\Proxy\Models\ProxyRequest;

/**
 * @extends Builder<ProxyRequest>
 */
final class ProxyRequestBuilder extends Builder
{
    public function forEndpoint(?int $endpointId): static
    {
        if ($endpointId === null) {
            return $this;
        }

        return $this->where('proxy_endpoint_id', $endpointId);
    }

    public function forStatus(?string $status): static
    {
        if ($status === null || $status === '') {
            return $this;
        }

        return $this->where('status', $status);
    }

    public function search(?string $value): static
    {
        if ($value === null || $value === '') {
            return $this;
        }

        $like = '%'.mb_strtolower($value).'%';

        return $this->where(static function (Builder $q) use ($like): void {
            $q->whereRaw('LOWER(request_id) like ?', [$like])
                ->orWhereHas('endpoint', static fn(Builder $e) => $e->whereRaw('LOWER(name) like ?', [$like]));
        });
    }
}
