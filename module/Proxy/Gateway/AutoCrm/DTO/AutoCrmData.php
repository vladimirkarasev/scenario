<?php

declare(strict_types=1);

namespace Module\Proxy\Gateway\AutoCrm\DTO;

final readonly class AutoCrmData
{
    /** @param  array<string, mixed>  $data */
    public function __construct(
        public array $data,
    ) {
    }

    /** @param  array<mixed, mixed>  $data */
    public static function fromArray(array $data): self
    {
        $typed = [];
        foreach ($data as $k => $v) {
            $typed[(string)$k] = $v;
        }

        return new self($typed);
    }

    public function id(): int|string|null
    {
        $id = $this->data['id'] ?? null;

        return is_int($id) || is_string($id) ? $id : null;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->data, $key, $default);
    }
}
