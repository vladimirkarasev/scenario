<?php

declare(strict_types=1);

namespace Module\Scenario\Http\Resources\JsonApi;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Module\Scenario\Models\ScenarioVersion;
use Module\Scenario\Models\ScenarioVersionRevision;

/**
 * @mixin ScenarioVersion
 */
final class ScenariosVersionResource extends JsonApiResource
{
    protected bool $usesRequestQueryString = false;

    public function toType(Request $request): string
    {
        return 'scenario_version';
    }

    /** @return array<string, mixed> */
    public function toAttributes(Request $request): array
    {
        return [
            'scenario_id' => $this->scenario_id,
            'name'        => $this->name,
            'status'      => $this->status,
            'schema_json' => $this->whenLoaded(
                'latestRevision',
                fn () => $this->latestRevision->schema_json ?? new \stdClass(),
                new \stdClass(),
            ),
            'created_at'  => $this->created_at?->toIso8601String(),
            'updated_at'  => $this->updated_at?->toIso8601String(),
            'revisions'   => $this->whenLoaded(
                'revisions',
                fn () => $this->revisions->map(fn (ScenarioVersionRevision $r) => [
                    'id'         => $r->id,
                    'created_at' => $r->created_at?->toIso8601String(),
                ])->values()->all(),
                [],
            ),
        ];
    }
}
