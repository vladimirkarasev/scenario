<?php

declare(strict_types=1);

namespace Module\Proxy\Gateway\DaData\DTO;

final readonly class DaDataSuggestion
{
    /** @param array<string, mixed> $data */
    public function __construct(
        public string $value,
        public string $unrestrictedValue,
        public array $data,
    ) {}

    /** @param array<mixed, mixed> $item */
    public static function fromArray(array $item): self
    {
        $rawData = $item['data'] ?? null;

        return new self(
            value: is_string($item['value'] ?? null) ? $item['value'] : '',
            unrestrictedValue: is_string($item['unrestricted_value'] ?? null) ? $item['unrestricted_value'] : '',
            data: is_array($rawData) ? self::toStringKeyed($rawData) : [],
        );
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->data, $key, $default);
    }

    /**
     * @param  array<mixed, mixed>  $array
     * @return array<string, mixed>
     */
    private static function toStringKeyed(array $array): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            $result[(string) $key] = $value;
        }

        return $result;
    }
}
