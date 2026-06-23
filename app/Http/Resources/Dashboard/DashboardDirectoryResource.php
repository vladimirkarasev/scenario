<?php

declare(strict_types=1);

namespace App\Http\Resources\Dashboard;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class DashboardDirectoryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $importsRaw = data_get($this->resource, 'imports', []);
        $importsArr = is_array($importsRaw) ? $importsRaw : [];
        $imports = collect($importsArr)
            ->map(static fn(mixed $item): mixed => is_array($item) ? (object)$item : $item);

        return [
            'id' => data_get($this->resource, 'id'),
            'project_id' => data_get($this->resource, 'project_id'),
            'name' => data_get($this->resource, 'name'),
            'slug' => data_get($this->resource, 'slug'),
            'description' => data_get($this->resource, 'description'),
            'match_by' => data_get($this->resource, 'match_by'),
            'versions_count' => data_get($this->resource, 'versions_count'),
            'latest_version' => data_get($this->resource, 'latest_version'),
            'imports' => DashboardDirectoryImportResource::collection($imports),
        ];
    }
}
