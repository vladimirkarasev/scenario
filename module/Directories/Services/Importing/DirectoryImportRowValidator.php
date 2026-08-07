<?php

declare(strict_types=1);

namespace Module\Directories\Services\Importing;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final readonly class DirectoryImportRowValidator
{
    /**
     * @param array<string, string|null> $row
     * @param Collection<string, array<string, mixed>> $fields
     * @param Collection<int, string> $mappedFieldKeys
     */
    public function validate(
        array $row,
        Collection $fields,
        Collection $mappedFieldKeys,
        int $rowNumber,
        ?string $matchBy,
    ): void {
        $rules = $fields
            ->mapWithKeys(static function (array $field, string $fieldKey) use ($mappedFieldKeys): array {
                $fieldRules = is_array($field['rules'] ?? null) ? $field['rules'] : ['nullable', 'string'];
                $baseRules = collect($fieldRules)
                    ->reject(static fn(mixed $rule): bool => $rule === 'required')
                    ->values()
                    ->all();

                if (!$mappedFieldKeys->contains($fieldKey)) {
                    return [$fieldKey => ['nullable', 'string']];
                }

                return [$fieldKey => $baseRules !== [] ? $baseRules : ['nullable', 'string']];
            })
            ->all();

        if ($matchBy !== null) {
            $rules[$matchBy] = [
                'required',
                ...array_values(array_filter(
                    $rules[$matchBy] ?? [],
                    static fn(mixed $rule): bool => $rule !== 'nullable',
                )),
            ];
        }

        $validator = Validator::make($row, $rules);

        if ($validator->fails()) {
            throw ValidationException::withMessages([
                "row_{$rowNumber}" => $validator->errors()->all(),
            ]);
        }
    }
}
