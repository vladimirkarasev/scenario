<?php

declare(strict_types=1);

namespace Module\Scenario\Services\Nodes\Block\Fields;

final readonly class MapBlockField extends AbstractBlockField
{
    public function toArray(): array
    {
        return $this->response(match ($this->type) {
            'route' => [
                ...$this->baseProps(),
                'routingMode' => $this->data->string($this->field, 'routingMode', 'auto'),
                'showAlternatives' => $this->data->boolean($this->field, 'showAlternatives', true),
                'maxWaypoints' => $this->data->integer($this->field, 'maxWaypoints', 10),
            ],
            'directory_map' => [
                ...$this->baseProps(),
                'directoryId' => $this->data->string($this->field, 'directoryId'),
                'versionId' => $this->data->string($this->field, 'versionId'),
                'latKey' => $this->data->string($this->field, 'latKey'),
                'lngKey' => $this->data->string($this->field, 'lngKey'),
                'detailDocument' => is_array($this->field['detailDocument'] ?? null) ? $this->field['detailDocument'] : null,
                'fields' => is_array($this->field['fields'] ?? null) ? $this->field['fields'] : [],
                'defaultSearch' => $this->data->string($this->field, 'defaultSearch'),
                'defaultZoom' => $this->data->integer($this->field, 'defaultZoom', 12),
            ],
            default => [
                ...$this->baseProps(),
                'lat' => $this->numeric('lat'),
                'lng' => $this->numeric('lng'),
                'address' => $this->data->string($this->field, 'address'),
                'defaultZoom' => $this->data->integer($this->field, 'defaultZoom', 15),
            ],
        });
    }
}
