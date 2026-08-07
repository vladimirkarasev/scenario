<?php

declare(strict_types=1);

namespace Module\Scenario\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Module\Scenario\Models\ScenarioFieldPreset;

/** @mixin ScenarioFieldPreset */
final class ScenarioFieldPresetResource extends JsonResource
{
    /** @return array<string, mixed> */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'field_type' => $this->field_type->value,
            'field' => $this->field,
            'schema_version' => $this->schema_version,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
