<?php

declare(strict_types=1);

namespace Module\Directories\Services\Items;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Module\Directories\Models\DirectoryItem;
use Module\Directories\Models\DirectoryVersion;
use Module\Directories\Repositories\DirectoryItemRepository;

final readonly class DirectoryItemValues
{
    public function __construct(private DirectoryItemRepository $items)
    {
    }

    /**
     * @param array<string, mixed> $values
     * @param Collection<string, array<string, mixed>> $fields
     * @return array<string, mixed>
     */
    public function normalize(array $values, Collection $fields): array
    {
        $normalized = [];

        foreach ($fields as $key => $field) {
            $value = $values[$key] ?? null;

            if ($value === null) {
                $normalized[$key] = null;
            } elseif (is_scalar($value)) {
                $normalized[$key] = trim((string)$value) ?: null;
            } else {
                $normalized[$key] = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: null;
            }
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $values
     * @param Collection<string, array<string, mixed>> $fields
     */
    public function validate(
        array $values,
        Collection $fields,
        DirectoryVersion $version,
        ?string $matchBy,
        ?DirectoryItem $ignore = null,
    ): void {
        $rules = $fields->mapWithKeys(static fn(array $field, string $key): array => [
            $key => is_array($field['rules'] ?? null) ? $field['rules'] : ['nullable', 'string'],
        ])->all();
        $validator = Validator::make($values, $rules);

        if ($matchBy !== null) {
            $validator->after(function (\Illuminate\Validation\Validator $validator) use ($values, $version, $matchBy, $ignore): void {
                $key = $values[$matchBy] ?? null;

                if (is_string($key) && $this->items->externalKeyExists($version, $key, $ignore)) {
                    $validator->errors()->add($matchBy, 'Item with this match key already exists in current version.');
                }
            });
        }

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }
    }

    /**
     * @param array<string, mixed> $values
     * @param Collection<string, array<string, mixed>> $fields
     */
    public function searchText(array $values, Collection $fields): string
    {
        $keys = $fields
            ->filter(static fn(array $field): bool => ($field['searchable'] ?? false) === true)
            ->keys()
            ->all();

        if ($keys !== []) {
            $values = array_intersect_key($values, array_fill_keys($keys, true));
        }

        return collect($values)
            ->filter(static fn(mixed $value): bool => is_scalar($value) && filled((string)$value))
            ->map(static fn(mixed $value): string => Str::lower(trim((string)$value)))
            ->implode(' ');
    }
}
