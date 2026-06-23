<?php

declare(strict_types=1);

namespace App\Http\Resources\Dashboard;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class DashboardCatalogItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $data = is_array($this->resource) ? $this->resource : [];

        return [
            'type' => $data['type'] ?? null,
            'id' => $data['id'] ?? null,
            'name' => $data['name'] ?? null,
            'is_active' => $data['is_active'] ?? null,
            'parent_id' => $data['parent_id'] ?? null,
            'child_count' => $data['child_count'] ?? null,
            'scenario_count' => $data['scenario_count'] ?? null,
            'version_count' => $data['version_count'] ?? null,
        ];
    }
}
