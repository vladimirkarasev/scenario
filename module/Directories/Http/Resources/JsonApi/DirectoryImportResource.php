<?php

declare(strict_types=1);

namespace Module\Directories\Http\Resources\JsonApi;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/**
 * @property array<string, mixed> $resource
 */
final class DirectoryImportResource extends JsonApiResource
{
    protected bool $usesRequestQueryString = false;

    public function toId(Request $request): string
    {
        $id = $this->resource['id'];

        return is_scalar($id) ? (string)$id : '';
    }

    public function toType(Request $request): string
    {
        return 'directory-import';
    }

    /** @return array<string, mixed> */
    #[\Override]
    public function toAttributes(Request $request): array
    {
        return array_diff_key($this->resource, ['id' => null]);
    }
}
