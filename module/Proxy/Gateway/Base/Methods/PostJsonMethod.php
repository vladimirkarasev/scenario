<?php

declare(strict_types=1);

namespace Module\Proxy\Gateway\Base\Methods;

final readonly class PostJsonMethod extends AbstractApiMethod
{
    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, mixed>  $query
     */
    public function __construct(
        private string $uri,
        private array $body = [],
        private array $query = [],
        private string $key = '',
    ) {
    }

    public function key(): string
    {
        return $this->key !== '' ? $this->key : parent::key().':'.$this->uri;
    }

    public function method(): string
    {
        return 'POST';
    }

    public function uri(): string
    {
        return $this->uri;
    }

    /** @return array<string, mixed> */
    public function query(): array
    {
        return $this->query;
    }

    /** @return array<string, mixed> */
    public function body(): array
    {
        return $this->body;
    }
}
