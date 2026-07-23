<?php

declare(strict_types=1);

namespace Module\Directories\Services\ImportSources;

use Illuminate\Support\Carbon;
use Module\Directories\DTO\DirectoryImportData;
use Module\Directories\Enums\DirectoryImportSourceType;

final class FileDirectoryImportSource implements DirectoryImportSource
{
    public function type(): DirectoryImportSourceType
    {
        return DirectoryImportSourceType::File;
    }

    /** @return array<string, mixed> */
    public function buildPayload(DirectoryImportData $data): array
    {
        if ($data->file === null) {
            $disk = $data->remote['file_disk'] ?? 'local';
            $path = $data->remote['file_path'] ?? null;

            if (!is_string($disk) || $disk === '' || !is_string($path) || $path === '') {
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
