<?php

declare(strict_types=1);

namespace Module\Directories\DTO;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Module\Directories\Exceptions\DirectoryException;
use Module\Directories\Models\Directory;
use Module\Projects\Models\Project;

final readonly class DirectoryData
{
    /**
     * @param  string[]  $categoryIds
     * @param  array<array-key, mixed>|null  $apiConfig
     * @param  array<int, array<string, mixed>>  $fields
     */
    public function __construct(
        public string $projectId,
        public array $categoryIds,
        public string $name,
        public string $slug,
        public ?string $description,
        public string $sourceType,
        public ?string $matchBy,
        public ?array $apiConfig,
        public array $fields,
        public bool $canManageDirectories,
    ) {
    }

    public static function fromRequest(Request $request, ?Directory $directory = null): self
    {
        $projectId = $directory !== null
            ? (string)$directory->project_id
            : self::projectIdFromRequest($request);

        $rawApiConfig = $request->input('api_config');
        $rawFields = $request->array('fields');

        return new self(
            projectId: $projectId,
            categoryIds: array_values(
                array_filter(
                    $request->array('category_ids'),
                    static fn(mixed $id): bool => is_string($id) && $id !== '',
                )
            ),
            name: $request->str('name')->toString(),
            slug: $request->str('slug')->toString() ?: Str::slug($request->str('name')->toString()),
            description: $request->filled('description') ? $request->str('description')->toString() : null,
            sourceType: $request->str('source_type')->toString(
            ) ?: ($directory !== null ? ($directory->source_type ?? 'manual') : 'manual'),
            matchBy: $request->filled('match_by') ? $request->str('match_by')->toString() : null,
            apiConfig: is_array($rawApiConfig) ? $rawApiConfig : null,
            fields: collect($rawFields)
                ->map(static function (mixed $field): array {
                    $f = is_array($field) ? $field : [];

                    return [
                        'key' => is_string($f['key'] ?? null) ? $f['key'] : '',
                        'name' => is_string($f['name'] ?? null) ? $f['name'] : '',
                        'type' => is_string($f['type'] ?? null) ? $f['type'] : 'string',
                        'nullable' => (bool)($f['nullable'] ?? true),
                        'default' => $f['default'] ?? null,
                        'sort_order' => is_int($f['sort_order'] ?? null) ? $f['sort_order'] : 0,
                        'rules' => is_array($f['rules'] ?? null) ? $f['rules'] : self::rulesForField($f),
                    ];
                })
                ->sortBy('sort_order')
                ->values()
                ->all(),
            canManageDirectories: (bool)$request->user()?->hasPermissionTo('directory_create'),
        );
    }

    /** @return array<int, string> */
    private static function rulesForField(mixed $field): array
    {
        $f = is_array($field) ? $field : [];
        $type = is_string($f['type'] ?? null) ? $f['type'] : 'string';

        $rules = [(bool)($f['nullable'] ?? true) ? 'nullable' : 'required'];

        $rules[] = match ($type) {
            'integer' => 'integer',
            'float' => 'numeric',
            'boolean' => 'boolean',
            'date', 'datetime' => 'date',
            'json' => 'json',
            default => 'string',
        };

        return $rules;
    }

    private static function projectIdFromRequest(Request $request): string
    {
        $user = $request->user();

        /** @var string|null $projectId */
        $projectId = Project::query()
            ->where('sitekey', $user?->sitekey)
            ->where('host', $user?->host)
            ->where('is_active', true)
            ->value('id');

        if ($projectId === null) {
            throw DirectoryException::projectNotFound();
        }

        return $projectId;
    }
}
