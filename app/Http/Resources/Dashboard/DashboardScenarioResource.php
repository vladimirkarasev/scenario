<?php

declare(strict_types=1);

namespace App\Http\Resources\Dashboard;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Module\Scenario\Models\Scenario;

/**
 * @mixin Scenario
 */
final class DashboardScenarioResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'active_version_id' => $this->active_version_id,
            'tag' => $this->tag,
            'aliases' => array_values(array_filter($this->aliases ?? [])),
            'categories' => DashboardCategorySummaryResource::collection(
                $this->relationLoaded('categories')
                    ? $this->categories->sortBy('name')->values()
                    : collect()
            ),
            'versions' => DashboardScenarioVersionResource::collection(
                $this->relationLoaded('versions') ? $this->versions : collect()
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'created_by' => $this->createdBy ? DashboardActorResource::make($this->createdBy) : null,
            'updated_by' => $this->updatedBy ? DashboardActorResource::make($this->updatedBy) : null,
        ];
    }
}
