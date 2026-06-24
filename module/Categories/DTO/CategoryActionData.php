<?php

declare(strict_types=1);

namespace Module\Categories\DTO;

use Illuminate\Http\Request;

final readonly class CategoryActionData
{
    public function __construct(
        public bool $canManageCatalog,
    ) {}

    public static function fromRequest(Request $request, ?bool $canManageCatalog = null): self
    {
        return new self(
            canManageCatalog: $canManageCatalog ?? (bool) $request->user()?->can('category_create'),
        );
    }
}
