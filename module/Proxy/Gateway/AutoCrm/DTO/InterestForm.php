<?php

declare(strict_types=1);

namespace Module\Proxy\Gateway\AutoCrm\DTO;

final readonly class InterestForm
{
    /** @param array<string, mixed> $data */
    public function __construct(private array $data) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    public static function minimal(
        int $requestTypeId,
        int $sourceId,
        string $firstName,
        string $lastName,
        ?string $phone = null,
    ): self {
        return new self(array_filter([
            'request_type_id' => $requestTypeId,
            'source_id' => $sourceId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'phone' => $phone,
        ], static fn (mixed $value): bool => $value !== null));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->data;
    }
}
