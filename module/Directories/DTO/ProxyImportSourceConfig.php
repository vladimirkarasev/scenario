<?php

declare(strict_types=1);

namespace Module\Directories\DTO;

final readonly class ProxyImportSourceConfig implements ImportSourceConfig
{
    public function __construct(
        public int $endpointId,
        public string $proxyUuid,
    ) {
    }

    /** @return array{proxy_endpoint_id: int, proxy_uuid: string} */
    public function toArray(): array
    {
        return [
            'proxy_endpoint_id' => $this->endpointId,
            'proxy_uuid' => $this->proxyUuid,
        ];
    }
}
