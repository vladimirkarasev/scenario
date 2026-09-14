<?php

declare(strict_types=1);

namespace Module\Proxy\DTO;

final readonly class ProxyConnectionView
{
    /**
     * @param array<string, mixed> $config
     * @param array<string, bool>  $secretFilled
     */
    public function __construct(
        public int $id,
        public ?string $projectId,
        public string $name,
        public string $credentialType,
        public string $credentialLabel,
        public array $config,
        public array $secretFilled,
        public ?string $createdAt,
        public ?string $updatedAt,
    ) {}
}
