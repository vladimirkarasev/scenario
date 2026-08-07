<?php

declare(strict_types=1);

namespace Module\Directories\DTO;

use Illuminate\Http\Request;

final readonly class DirectoryQuery
{
    /**
     * @param array<string, mixed> $filters
     * @param array<string, mixed> $parameters
     */
    public function __construct(
        public int $page = 1,
        public int $perPage = 50,
        public array $filters = [],
        public ?string $sort = null,
        public string $direction = 'asc',
        private array $parameters = [],
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        /** @var array<string, mixed> $parameters */
        $parameters = $request->query();

        return self::fromArray($parameters);
    }

    /** @param array<string, mixed> $parameters */
    public static function fromArray(array $parameters): self
    {
        $page = self::integer($parameters['page'] ?? null, 1);
        $perPage = min(100, self::integer($parameters['per_page'] ?? null, 50));
        $filters = [];
        $rawFilters = $parameters['filter'] ?? null;

        if (is_array($rawFilters)) {
            foreach ($rawFilters as $key => $value) {
                if (is_string($key)) {
                    $filters[$key] = $value;
                }
            }
        }
        $sort = is_string($parameters['sort'] ?? null) && $parameters['sort'] !== ''
            ? $parameters['sort']
            : null;
        $direction = strtolower(is_string($parameters['direction'] ?? null) ? $parameters['direction'] : 'asc');

        return new self(
            page: max(1, $page),
            perPage: max(1, $perPage),
            filters: $filters,
            sort: $sort,
            direction: $direction === 'desc' ? 'desc' : 'asc',
            parameters: $parameters,
        );
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->parameters;
    }

    private static function integer(mixed $value, int $default): int
    {
        return is_numeric($value) ? (int)$value : $default;
    }
}
