<?php

declare(strict_types=1);

namespace Module\Users\DTO;

use Illuminate\Http\Request;

final readonly class UserData
{
    /**
     * @param string[] $roles
     * @param string[] $groupIds
     */
    public function __construct(
        public string $name,
        public ?string $fio,
        public string $email,
        public ?string $login,
        public ?string $externalId,
        public ?string $password,
        public array $roles = [],
        public array $groupIds = [],
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            name: $request->string('name')->toString(),
            fio: $request->filled('fio') ? $request->string('fio')->toString() : null,
            email: $request->string('email')->toString(),
            login: $request->filled('login') ? $request->string('login')->toString() : null,
            externalId: $request->filled('external_id') ? $request->string('external_id')->toString() : null,
            password: $request->filled('password') ? $request->string('password')->toString() : null,
            roles: array_values(array_filter($request->array('roles'), is_string(...))),
            groupIds: array_values(array_filter($request->array('group_ids'), is_string(...))),
        );
    }
}
