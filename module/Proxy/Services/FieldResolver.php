<?php

declare(strict_types=1);

namespace Module\Proxy\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Module\Proxy\DTO\ProxyField;

final class FieldResolver
{
    /**
     * @param  iterable<ProxyField>                            $fields
     * @param  array<string, mixed>                            $payload
     * @param  array<string, mixed>                            $query
     * @param  array<string, mixed>                            $headers
     * @param  array<string, mixed>                            $system
     * @param  array<string, UploadedFile|array<UploadedFile>> $files
     * @return array<string, mixed>
     */
    public function resolve(
        iterable $fields,
        array $payload,
        array $query,
        array $headers,
        array $system,
        array $files = []
    ): array {
        $normalized = [];

        foreach ($fields as $field) {
            $value = $this->value($field->sourcePath(), $payload, $query, $headers, $system, $files);
            $normalized[$field->key()] = $value ?? $field->defaultValue();
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed>                            $payload
     * @param array<string, mixed>                            $query
     * @param array<string, mixed>                            $headers
     * @param array<string, mixed>                            $system
     * @param array<string, UploadedFile|array<UploadedFile>> $files
     */
    public function value(
        string $source,
        array $payload,
        array $query,
        array $headers,
        array $system,
        array $files = []
    ): mixed {
        [$scope, $path] = array_pad(explode('.', $source, 2), 2, null);

        return match ($scope) {
            'payload' => Arr::get($payload, (string) $path),
            'query' => Arr::get($query, (string) $path),
            'headers' => Arr::get($headers, strtolower((string) $path)),
            'system' => Arr::get($system, (string) $path),
            'files' => $this->fileMetadata(Arr::get($files, (string) $path)),
            default => null,
        };
    }

    /**
     * @param  UploadedFile|array<UploadedFile>|mixed                     $file
     * @return array<string, mixed>|array<int, array<string, mixed>>|null
     */
    private function fileMetadata(mixed $file): ?array
    {
        if ($file instanceof UploadedFile) {
            return $this->singleFileMeta($file);
        }

        if (is_array($file)) {
            $result = [];
            foreach ($file as $item) {
                if ($item instanceof UploadedFile) {
                    $result[] = $this->singleFileMeta($item);
                }
            }

            return $result !== [] ? $result : null;
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function singleFileMeta(UploadedFile $file): array
    {
        return [
            'original_name' => $file->getClientOriginalName(),
            'size' => $file->getSize() ?: 0,
            'mime' => $file->getMimeType() ?? $file->getClientMimeType(),
            'path' => $file->getRealPath() ?: null,
        ];
    }
}
