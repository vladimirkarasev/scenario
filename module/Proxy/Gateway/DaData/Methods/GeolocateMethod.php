<?php

declare(strict_types=1);

namespace Module\Proxy\Gateway\DaData\Methods;

use Module\Proxy\Gateway\Base\Methods\AbstractApiMethod;

final readonly class GeolocateMethod extends AbstractApiMethod
{
    public function __construct(
        private string $type,
        private float $lat,
        private float $lon,
        private int $radiusMeters = 100,
        private int $count = 5,
    ) {}

    #[\Override]
    public function key(): string
    {
        return 'dadata.geolocate.'.$this->type;
    }

    public function method(): string
    {
        return 'POST';
    }

    public function uri(): string
    {
        return 'geolocate/'.$this->type;
    }

    /** @return array<string, mixed> */
    #[\Override]
    public function body(): array
    {
        return [
            'lat' => $this->lat,
            'lon' => $this->lon,
            'radius_meters' => $this->radiusMeters,
            'count' => $this->count,
        ];
    }
}
