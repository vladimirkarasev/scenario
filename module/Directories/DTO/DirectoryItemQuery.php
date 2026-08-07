<?php

declare(strict_types=1);

namespace Module\Directories\DTO;

use Illuminate\Http\Request;
use Module\Directories\Models\Directory;

final readonly class DirectoryItemQuery
{
    /**
     * @param array<string, string|list<string>> $filters
     * @param array<string, string> $filtersTo
     */
    public function __construct(
        public ?string $versionId = null,
        public array $filters = [],
        public array $filtersTo = [],
        public ?string $search = null,
        public ?string $sortKey = null,
        public string $sortDirection = 'asc',
        public bool $withOther = false,
    ) {
    }

    public static function fromRequest(Request $request, Directory $directory): self
    {
        $raw = (array)$request->input('filter', []);
        $versionId = is_string($raw['version_id'] ?? null) ? $raw['version_id'] : null;
        $searchValue = $raw['search'] ?? $raw['q'] ?? null;
        $search = is_string($searchValue) && trim($searchValue) !== '' ? trim($searchValue) : null;
        $filters = [];

        foreach ($raw as $key => $value) {
            if (!is_string($key) || in_array($key, ['version_id', 'q', 'search', 'with_other'], true)) {
                continue;
            }

            if (is_string($value) && $value !== '') {
                $filters[$key] = $value;
            } elseif (is_array($value)) {
                $values = array_values(array_filter($value, static fn(mixed $item): bool => is_string($item) && $item !== ''));

                if ($values !== []) {
                    $filters[$key] = $values;
                }
            }
        }

        $filtersTo = [];
        foreach ((array)$request->input('filter_to', []) as $key => $value) {
            if (is_string($key) && is_string($value) && $value !== '') {
                $filtersTo[$key] = $value;
            }
        }

        $sort = $request->input('sort', $directory->default_sort);
        $sort = is_string($sort) ? $sort : '';

        return new self(
            versionId: $versionId,
            filters: $filters,
            filtersTo: $filtersTo,
            search: $search,
            sortKey: $sort === '' ? null : ltrim($sort, '-'),
            sortDirection: str_starts_with($sort, '-') ? 'desc' : 'asc',
            withOther: filter_var($raw['with_other'] ?? false, FILTER_VALIDATE_BOOLEAN),
        );
    }
}
