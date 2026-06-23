<?php

declare(strict_types=1);

namespace Module\Directories\Http\Resources\JsonApi;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/**
 * @property array<string, mixed> $resource
 */
final class DirectoryItemResource extends JsonApiResource
{
    protected bool $usesRequestQueryString = false;

    public function toId(Request $request): string
    {
        $id = $this->resource['id'];

        return is_scalar($id) ? (string) $id : '';
    }

    public function toType(Request $request): string
    {
        return 'directory-item';
    }

    /** @return array<string, mixed> */
    public function toAttributes(Request $request): array
    {
        $attributes = array_diff_key($this->resource, ['id' => null]);

        $rawFields = $request->input('fields.items');
        if (! is_string($rawFields) || $rawFields === '') {
            return $attributes;
        }

        $allowedKeys = explode(',', $rawFields)
                |> (fn ($x) => array_map('trim', $x))
                |> (fn ($x) => array_filter($x, static fn (string $k): bool => $k !== ''))
                |> array_flip(...);

        if ($allowedKeys === [] || ! isset($attributes['data']) || ! is_array($attributes['data'])) {
            return $attributes;
        }

        $attributes['data'] = array_intersect_key($attributes['data'], $allowedKeys);

        return $attributes;
    }
}
