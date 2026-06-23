<?php

declare(strict_types=1);

namespace Module\Directories\DTO;

use Illuminate\Http\Request;

final readonly class DirectoryManualItemData
{
    /** @param  array<string, mixed>  $data */
    public function __construct(
        public array $data,
        public ?string $matchBy,
        public ?int $parentId,
        public ?string $externalKey = null,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        /** @var array<string, mixed> $data */
        $data = $request->array('data');

        return new self(
            data: $data,
            matchBy: $request->filled('match_by') ? $request->str('match_by')->toString() : null,
            parentId: $request->filled('parent_id') ? $request->integer('parent_id') : null,
            externalKey: $request->filled('external_key') ? $request->str('external_key')->toString() : null,
        );
    }
}
