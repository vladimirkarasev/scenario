<?php

declare(strict_types=1);

namespace Module\Directories\Services\Importing;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Module\Directories\DTO\DirectoryImportChunkResult;
use Module\Directories\Models\DirectoryImport;

final readonly class DirectoryImportRowProcessor
{
    public function __construct(
        private DirectoryImport $directoryImportModel,
        private DirectoryImportPayloadNormalizer $normalizer,
        private DirectoryImportRowValidator $validator,
        private DirectoryImportRowWriter $writer,
    ) {
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @throws \Throwable
     */
    public function importRows(DirectoryImport $import, Collection $rows, int $baseRowNumber): DirectoryImportChunkResult
    {
        /** @var Collection<string, string> $mapping */
        $mapping = collect($import->mapping_json);
        /** @var Collection<string, array<string, mixed>> $fields */
        $fields = collect($import->fields_json)->keyBy('key');
        /** @var Collection<int, string> $mappedFieldKeys */
        $mappedFieldKeys = $mapping->values()->filter()->unique()->values();
        $failedRows = 0;
        $addedCount = 0;
        $updatedCount = 0;
        $rowErrors = [];
        $processedKeys = [];

        foreach ($rows->values() as $index => $row) {
            $rowNumber = $baseRowNumber + $index;
            $incoming = $this->normalizer->normalizeIncomingRow($row);
            $prepared = $this->normalizer->prepareRow($incoming, $mapping, $fields);

            if ($import->external_key_field !== null) {
                $prepared[DirectoryImportPayloadNormalizer::EXTERNAL_KEY] = $this->normalizer->externalKey(
                    $incoming,
                    $import->external_key_field,
                );
            }

            try {
                $result = $this->validateAndPersistRow($prepared, $fields, $mappedFieldKeys, $rowNumber, $import);

                if ($result['externalKey'] !== null) {
                    $processedKeys[] = $result['externalKey'];
                }

                if ($result['written']) {
                    $result['created'] ? $addedCount++ : $updatedCount++;
                }
            } catch (ValidationException $exception) {
                $failedRows++;
                $rowErrors[] = collect($exception->errors())
                    ->flatten()
                    ->implode(' ');
            } catch (UniqueConstraintViolationException) {
            }
        }

        return new DirectoryImportChunkResult(
            failedRows: $failedRows,
            rowErrors: $rowErrors,
            processedKeys: array_values(array_unique($processedKeys)),
            addedCount: $addedCount,
            updatedCount: $updatedCount,
        );
    }

    /**
     * @param  array<string, string|null>  $prepared
     * @param  Collection<string, array<string, mixed>>  $fields
     * @param  Collection<int, string>  $mappedFieldKeys
     * @return array{externalKey: ?string, written: bool, created: bool}
     *
     * @throws \Throwable
     */
    private function validateAndPersistRow(
        array $prepared,
        Collection $fields,
        Collection $mappedFieldKeys,
        int $rowNumber,
        DirectoryImport $import,
    ): array {
        return $this->directoryImportModel->getConnection()->transaction(
            function () use ($prepared, $fields, $mappedFieldKeys, $rowNumber, $import): array {
                $requiredKey = $import->external_key_field !== null
                    ? DirectoryImportPayloadNormalizer::EXTERNAL_KEY
                    : $import->match_by;
                $this->validator->validate($prepared, $fields, $mappedFieldKeys, $rowNumber, $requiredKey);

                return $this->writer->write($import, $prepared, $fields, $mappedFieldKeys, $rowNumber);
            },
        );
    }
}
