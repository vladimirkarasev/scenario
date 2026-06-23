<?php

declare(strict_types=1);

namespace App\Http\Resources\Dashboard;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Category
 */
final class DashboardCategoryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'name' => $this->name,
            'is_active' => $this->is_active,
            'scenario_count' => $this->scenarios_count ?? $this->scenarios()->count(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'parent' => $this->relationLoaded('parent') ? ($this->parent ? [
                'id' => $this->parent->id,
                'name' => $this->parent->name,
            ] : null) : null,
            'created_by' => $this->createdBy ? DashboardActorResource::make($this->createdBy) : null,
            'updated_by' => $this->updatedBy ? DashboardActorResource::make($this->updatedBy) : null,
        ];
    }
}
