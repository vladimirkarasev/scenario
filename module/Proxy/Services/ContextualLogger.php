<?php

declare(strict_types=1);

namespace Module\Proxy\Services;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;

final readonly class ContextualLogger implements LoggerInterface
{
    use LoggerTrait;

    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        private LoggerInterface $inner,
        private array $context = [],
    ) {}

    /**
     * @param array<string, mixed> $context
     */
    public function with(array $context): self
    {
        return new self($this->inner, array_merge($this->context, $context));
    }

    public function log(mixed $level, string|\Stringable $message, array $context = []): void
    {
        $this->inner->log($level, $message, array_merge($this->context, $context));
    }
}
