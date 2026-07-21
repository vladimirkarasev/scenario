<?php

declare(strict_types=1);

namespace Module\Proxy\DTO;

final readonly class ProxyExecutionResult
{
    public function __construct(
        public ProxyResponse $response,
        public bool $mocked,
    ) {}
}
