<?php

declare(strict_types=1);

namespace Module\Scenario\Http\Resources\JsonApi;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiRequest;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Module\Scenario\DTO\CatalogItemRow;

/** @property CatalogItemRow $resource */
final class CatalogResource extends JsonApiResource
{
    protected bool $usesRequestQueryString = false;

    public function toId(Request $request): ?string
    {
        return match ($this->resource->item_type) {
            'category' => $this->resource->category_id,
            'scenario' => $this->resource->scenario_id,
            default => null,
        };
    }

    public function toType(Request $request): ?string
    {
        return $this->resource->item_type !== '' ? $this->resource->item_type : null;
    }

    /** @return array<string, mixed> */
    public function toAttributes(Request $request): array
    {
        return [
            'name' => $this->resource->name,
            'is_active' => $this->resource->is_active,
            'active_version_id' => $this->resource->active_version_id,
            'parent_id' => $this->resource->parent_id,
            'child_count' => $this->resource->child_count,
            'scenario_count' => $this->resource->scenario_count,
            'version_count' => $this->resource->version_count,
        ];
    }

    /** @return array<string, array<string, list<array<string, mixed>>>> */
    public function toRelationships(Request $request): array
    {
        return [
            'path' => [
                'data' => array_map(
                    static fn(array $category): array => [
                        'type' => 'category',
                        'id' => (string)$category['id'],
                        'meta' => [
                            'name' => $category['name'],
                        ],
                    ],
                    $this->path(),
                ),
            ],
        ];
    }

    /** @return array<string, array<string, list<array<string, mixed>>>> */
    protected function resolveResourceRelationshipIdentifiers(JsonApiRequest $request): array
    {
        return $this->toRelationships($request);
    }

    /** @return list<array{id: string, name: string}> */
    private function path(): array
    {
        $path = $this->resource->path;

        if ($path === '') {
            return [];
        }

        $decoded = json_decode($path, true);

        return is_array($decoded) ? $this->normalizePath($decoded) : [];
    }

    /**
     * @param  array<int|string, mixed>  $path
     * @return list<array{id: string, name: string}>
     */
    private function normalizePath(array $path): array
    {
        return array_values(
            array_filter(
                array_map(
                    static function (mixed $item): ?array {
                        if (!is_array($item) || !isset($item['id'], $item['name'])) {
                            return null;
                        }

                        $id = $item['id'];
                        $name = $item['name'];

                        if (!is_scalar($id) || !is_scalar($name)) {
                            return null;
                        }

                        return ['id' => (string)$id, 'name' => (string)$name];
                    },
                    $path,
                )
            )
        );
    }
}
