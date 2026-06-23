<?php

declare(strict_types=1);

namespace App\Http\Resources\Dashboard;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class DashboardDirectoryImportResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => data_get($this->resource, 'id'),
            'directory_id' => data_get($this->resource, 'directory_id'),
            'directory_version_id' => data_get($this->resource, 'directory_version_id'),
            'mode' => data_get($this->resource, 'mode'),
            'status' => data_get($this->resource, 'status'),
            'match_by' => data_get($this->resource, 'match_by'),
            'chunk_size' => data_get($this->resource, 'chunk_size'),
            'processed_rows' => data_get($this->resource, 'processed_rows'),
            'imported_rows' => data_get($this->resource, 'imported_rows'),
            'failed_rows' => data_get($this->resource, 'failed_rows'),
            'error_message' => data_get($this->resource, 'error_message'),
            'started_at' => data_get($this->resource, 'started_at'),
            'finished_at' => data_get($this->resource, 'finished_at'),
            'created_at' => data_get($this->resource, 'created_at'),
        ];
    }
}
