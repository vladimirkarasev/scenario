<?php

declare(strict_types=1);

namespace Module\Directories\DTO;

final readonly class DirectoryPage
{
    /**
     * @param list<array<string, mixed>> $items
     * @param array<string, mixed> $dictionary
     */
    public function __construct(
        public array $items,
        public DirectoryPagination $pagination,
        public array $dictionary,
    ) {
    }

    /** @param array<string, mixed> $payload */
    public static function fromArray(array $payload): self
    {
        return new self(
            items: self::items($payload['data'] ?? null),
            pagination: DirectoryPagination::fromArray(self::stringKeyed($payload['meta'] ?? null)),
            dictionary: self::stringKeyed($payload['dictionary'] ?? null),
        );
    }

    /** @return array{meta: array<string, mixed>} */
    public function additional(): array
    {
        return [
            'meta' => [
                ...$this->pagination->toArray(),
                'dictionary' => $this->dictionary,
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    private static function items(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $items = [];

        foreach ($value as $item) {
            if (is_array($item)) {
                $items[] = self::stringKeyed($item);
            }
        }

        return $items;
    }

    /** @return array<string, mixed> */
    private static function stringKeyed(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $result = [];

        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $result[$key] = $item;
            }
        }

        return $result;
    }
}
