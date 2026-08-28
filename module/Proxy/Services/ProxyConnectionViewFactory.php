<?php

declare(strict_types=1);

namespace Module\Proxy\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Module\Proxy\DTO\ProxyConnectionView;
use Module\Proxy\Models\ProxyConnection;

final readonly class ProxyConnectionViewFactory
{
    public function __construct(private CredentialCatalog $credentials) {}

    public function make(ProxyConnection $connection): ProxyConnectionView
    {
        $credential = $this->credentials->has($connection->credential_type)
            ? $this->credentials->get($connection->credential_type)
            : null;
        $secrets = $connection->secrets ?? [];
        $secretFilled = [];

        foreach ($credential?->secretKeys() ?? [] as $key) {
            $value = $secrets[$key] ?? null;
            $secretFilled[$key] = $value !== null && $value !== '';
        }

        return new ProxyConnectionView(
            id: $connection->id,
            projectId: $connection->project_id,
            name: $connection->name,
            credentialType: $connection->credential_type,
            credentialLabel: $credential?->label() ?? $connection->credential_type,
            config: $connection->config ?? [],
            secretFilled: $secretFilled,
            createdAt: $connection->created_at?->toIso8601String(),
            updatedAt: $connection->updated_at?->toIso8601String(),
        );
    }

    /**
     * @param  LengthAwarePaginator<int, ProxyConnection>     $connections
     * @return LengthAwarePaginator<int, ProxyConnectionView>
     */
    public function paginate(LengthAwarePaginator $connections): LengthAwarePaginator
    {
        return $connections->through(fn (ProxyConnection $connection): ProxyConnectionView => $this->make($connection));
    }
}
