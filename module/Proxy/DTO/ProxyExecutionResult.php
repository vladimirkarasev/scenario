<?php

declare(strict_types=1);

namespace Module\Proxy\DTO;

/**
 * Результат выполнения proxy: сам ответ + флаг был ли он замокан.
 */
final readonly class ProxyExecutionResult
{
    public function __construct(
        public ProxyResponse $response,
        public bool $mocked,
    ) {
    }
}
