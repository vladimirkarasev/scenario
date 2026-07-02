<?php

declare(strict_types=1);

namespace Module\Users\DTO;

use Module\Users\Http\Requests\UserIndexRequest;

final readonly class UserIndexData
{
    /**
     * @param  list<string>  $groupIds
     * @param  list<string>  $roleIds
     */
    public function __construct(
        public ?string $search,
        public array $groupIds,
        public array $roleIds,
        public int $perPage,
    ) {
    }

    public static function fromRequest(UserIndexRequest $request): self
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();
        $filter = is_array($validated['filter'] ?? null) ? $validated['filter'] : [];

        $search = isset($filter['search']) && is_string($filter['search']) && $filter['search'] !== ''
            ? $filter['search']
            : null;

        return new self(
            search: $search,
            groupIds: self::toStringList($filter['group_ids'] ?? []),
            roleIds: self::toStringList($filter['role_ids'] ?? []),
            perPage: $request->integer('per_page', 15),
        );
    }

    /** @return list<string> */
    private static function toStringList(mixed $input): array
    {
        return array_map(
                static fn(mixed $v): string => is_scalar($v) ? (string)$v : '',
                is_array($input) ? $input : [],
            )
                |> (fn($x) => array_filter($x, static fn(string $v): bool => $v !== ''))
                |> array_values(...);
    }
}
