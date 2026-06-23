<?php

declare(strict_types=1);

namespace Module\Directories\Services\ImportSources;

use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use Module\Directories\DTO\DirectoryImportData;
use Module\Directories\Enums\DirectoryImportSourceType;
use Module\Directories\Imports\DirectoryExcelImport;
use Module\Directories\Models\DirectoryImport;

final class FileDirectoryImportSource implements DirectoryImportSource
{
    public function type(): DirectoryImportSourceType
    {
        return DirectoryImportSourceType::File;
    }

    public function runsInline(): bool
    {
        return false;
    }

    /** @return array<string, mixed> */
    public function buildPayload(DirectoryImportData $data): array
    {
        if ($data->file === null) {
            $disk = $data->remote['file_disk'] ?? 'local';
            $path = $data->remote['file_path'] ?? null;

            if (! is_string($disk) || $disk === '' || ! is_string($path) || $path === '') {
                throw new \RuntimeException('Import file path is required.');
            }

            return [
                'file_disk' => $disk,
                'file_path' => $path,
                'remote_config_json' => null,
            ];
        }

        $path = $this->storeFile($data);

        return [
            'file_disk' => 'local',
            'file_path' => $path,
            'remote_config_json' => null,
        ];
    }

    public function start(DirectoryImport $import, callable $importChunk): void
    {
        Excel::queueImport(
            new DirectoryExcelImport($import->id, $import->chunk_size, app()),
            $import->file_path,
            $import->file_disk,
        )->onQueue('imports');
    }

    private function storeFile(DirectoryImportData $data): string
    {
        if ($data->file === null) {
            throw new \RuntimeException('Import file is required.');
        }

        $datePath = Carbon::now()->format('Y/m/d');
        $path = $data->file->store("directory-imports/{$datePath}");

        if ($path === false) {
            throw new \RuntimeException('Failed to store import file.');
        }

        return $path;
    }
}
