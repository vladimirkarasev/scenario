<?php

declare(strict_types=1);

namespace Module\Users\DTO;

use Illuminate\Http\Request;

final readonly class RoleData
{
    /**
     * @param  list<string>  $permissions
     */
    public function __construct(
        public string $name,
        public array $permissions,
        public ?string $title,
        public ?string $description,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        $permissions = array_values(
            array_filter(
                is_array($request->input('permissions')) ? $request->input('permissions') : [],
                is_string(...),
            )
        );

        $title = (string)$request->string('title');
        $description = (string)$request->string('description');

        return new self(
            name: (string)$request->string('name'),
            permissions: $permissions,
            title: $title !== '' ? $title : null,
            description: $description !== '' ? $description : null,
        );
    }
}
