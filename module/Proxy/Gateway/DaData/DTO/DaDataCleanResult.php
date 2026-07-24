<?php

declare(strict_types=1);

namespace Module\Proxy\Gateway\DaData\DTO;

final readonly class DaDataCleanResult
{
    /** @param array<string, mixed> $data */
    public function __construct(public array $data) {}

    /** @param array<mixed, mixed> $data */
    public static function fromArray(array $data): self
    {
        $typed = [];
        foreach ($data as $k => $v) {
            $typed[(string) $k] = $v;
        }

        return new self($typed);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->data, $key, $default);
    }

    public function qc(): ?int
    {
        $qc = $this->data['qc'] ?? null;

        return is_int($qc) ? $qc : null;
    }
}
