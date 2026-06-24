<?php

declare(strict_types=1);

namespace App\Http\Resources\Dashboard;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Module\Scenario\Models\ScenarioVersion;
use Module\Scenario\Models\ScenarioVersionRevision;
use stdClass;

/**
 * @mixin ScenarioVersion
 */
final class DashboardScenarioVersionResource extends JsonResource
{
    /** @return array<string, mixed> */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'status' => $this->status,
            'schema_version' => $this->latestRevision?->schema_version,
            'schema_json' => $this->latestRevision->schema_json ?? new stdClass,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'revisions' => $this->relationLoaded('revisions')
                ? $this->revisions->map(fn(ScenarioVersionRevision $revision): array => [
                    'id' => $revision->id,
                    'created_at' => $revision->created_at?->toIso8601String(),
                ])->values()
                : collect(),
        ];
    }
}
