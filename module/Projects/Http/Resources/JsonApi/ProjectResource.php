<?php

declare(strict_types=1);

namespace Module\Projects\Http\Resources\JsonApi;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Module\Projects\Models\Project;

/**
 * @mixin Project
 */
final class ProjectResource extends JsonApiResource
{
    protected bool $usesRequestQueryString = false;

    public function toId(Request $request): string
    {
        return (string)$this->id;
    }

    public function toType(Request $request): string
    {
        return 'projects';
    }

    /** @return array<string, mixed> */
    public function toAttributes(Request $request): array
    {
        return [
            'name' => $this->name,
            'sitekey' => $this->sitekey,
            'host' => $this->host,
            'shared_secret' => $this->shared_secret,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
