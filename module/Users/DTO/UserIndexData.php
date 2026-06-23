<?php

declare(strict_types=1);

namespace Module\Users\DTO;

use Illuminate\Http\Request;

final readonly class UserIndexData
{
    /**
     * @param list<string> $groupIds
     * @param list<string> $roleIds
     */
    public function __construct(
        public ?string $search,
        public array $groupIds,
        public array $roleIds,
        public int $perPage,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $filter = $request->array('filter');

        $search = isset($filter['search']) && is_string($filter['search']) && $filter['search'] !== ''
            ? $filter['search']
            : null;

        return new self(
            search: $search,
            groupIds: self::toStringList($filter['group_ids'] ?? []),
            roleIds: self::toStringList($filter['role_ids'] ?? []),
            perPage: (int) $request->integer('per_page', 20),
        );
    }

    /** @return list<string> */
    private static function toStringList(mixed $input): array
    {
        return array_values(array_filter(
            array_map(
                static fn (mixed $v): string => is_scalar($v) ? (string) $v : '',
                is_array($input) ? $input : [],
            ),
            static fn (string $v): bool => $v !== '',
        ));
    }
}
