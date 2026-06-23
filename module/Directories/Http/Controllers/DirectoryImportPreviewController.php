<?php

declare(strict_types=1);

namespace Module\Directories\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Maatwebsite\Excel\Imports\HeadingRowFormatter;
use Module\Directories\Models\Directory;
use Module\Directories\Services\DirectoryService;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

final class DirectoryImportPreviewController extends Controller
{
    public function __construct(
        private readonly DirectoryService $directoryService,
    ) {}

    public function store(Request $request, Directory $directory): JsonResponse
    {
        $this->directoryService->ensureProjectAccess(
            $directory,
            $this->directoryService->currentProjectForUser($request->user()),
        );

        /** @var array<string, mixed> $validated */
        $validated = $request->validate([
            'source_type' => ['required', 'in:file,remote'],
            'file' => ['nullable', 'file', 'mimes:xlsx,csv,ods,xls'],
            'remote' => ['nullable', 'array'],
            'remote.url' => ['nullable', 'url'],
            'remote.items_path' => ['nullable', 'string'],
            'remote.page_param' => ['nullable', 'string'],
            'remote.per_page_param' => ['nullable', 'string'],
            'remote.per_page' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'remote.per_page_path' => ['nullable', 'string'],
            'remote.start_page' => ['nullable', 'integer', 'min:1'],
            'remote.headers' => ['nullable', 'array'],
            'remote.headers.*' => ['nullable', 'string'],
            'remote.query' => ['nullable', 'array'],
        ]);

        $sourceType = is_string($validated['source_type'] ?? null) ? $validated['source_type'] : 'file';

        if ($sourceType === 'remote') {
            /** @var array<string, mixed> $remote */
            $remote = is_array($validated['remote'] ?? null) ? $validated['remote'] : [];

            if (blank($remote['url'] ?? null)) {
                return $this->jsonApiError('URL обязателен для удалённого импорта.', 422);
            }

            try {
                $headers = is_array($remote['headers'] ?? null) ? $remote['headers'] : [];
                $configQuery = is_array($remote['query'] ?? null) ? $remote['query'] : [];
                $pageParam = is_string($remote['page_param'] ?? null) ? $remote['page_param'] : 'page';
                $perPageParam = is_string($remote['per_page_param'] ?? null) ? $remote['per_page_param'] : 'per_page';
                $startPage = is_int($remote['start_page'] ?? null) ? $remote['start_page'] : 1;
                $perPage = is_int($remote['per_page'] ?? null) ? $remote['per_page'] : 100;
                $url = is_string($remote['url']) ? $remote['url'] : '';
                /** @var array<string, mixed> $queryParams */
                $queryParams = array_filter([
                    ...$configQuery,
                    $pageParam => $startPage,
                    $perPageParam => $perPage,
                ], static fn (mixed $value): bool => $value !== null);

                $payload = Http::acceptJson()
                    ->withHeaders($headers)
                    ->timeout(30)
                    ->get($url, $queryParams)
                    ->throw()
                    ->json();

                $itemsPath = is_string($remote['items_path'] ?? null) ? $remote['items_path'] : 'data';
                $items = data_get($payload, $itemsPath, $payload);

                if (! is_array($items) || $items === []) {
                    return $this->jsonApiMeta(['headers' => []]);
                }

                $first = collect($items)->first();

                if (! is_array($first)) {
                    return $this->jsonApiError('Удалённый API должен возвращать массив объектов.', 422);
                }

                return $this->jsonApiMeta(['headers' => $this->formatHeaders(array_keys($first))]);
            } catch (Throwable $e) {
                return $this->jsonApiError('Cannot read remote source: '.$e->getMessage(), 422);
            }
        }

        $file = $request->file('file');

        if ($file === null) {
            return $this->jsonApiError('Файл обязателен для импорта из Excel.', 422);
        }

        try {
            $reader = IOFactory::createReaderForFile($file->getRealPath());
            $reader->setReadDataOnly(true);
            $reader->setReadEmptyCells(false);

            $spreadsheet = $reader->load($file->getRealPath());
            $sheet = $spreadsheet->getActiveSheet();
            $highestColumn = $sheet->getHighestColumn();

            $rangeData = $sheet->rangeToArray(
                "A1:{$highestColumn}1",
                null,
                true,
                true,
                false,
            );
            /** @var array<int, mixed> $firstRow */
            $firstRow = is_array($rangeData[0] ?? null) ? $rangeData[0] : [];

            $headers = $this->formatHeaders(
                collect($firstRow)
                    ->map(static fn (mixed $header): string => trim(is_scalar($header) ? (string) $header : ''))
                    ->filter(static fn (string $header): bool => $header !== '')
                    ->values()
                    ->all(),
            );
        } catch (Throwable $e) {
            return $this->jsonApiError('Cannot read file: '.$e->getMessage(), 422);
        }

        return $this->jsonApiMeta(['headers' => $headers]);
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function jsonApiMeta(array $meta, int $status = 200): JsonResponse
    {
        return new JsonResponse(
            ['meta' => $meta],
            $status,
            ['Content-Type' => 'application/vnd.api+json'],
        );
    }

    private function jsonApiError(string $detail, int $status): JsonResponse
    {
        return new JsonResponse(
            ['errors' => [['status' => (string) $status, 'detail' => $detail]]],
            $status,
            ['Content-Type' => 'application/vnd.api+json'],
        );
    }

    /**
     * @param  array<int, string>                            $headers
     * @return array<int, array{label: string, key: string}>
     */
    private function formatHeaders(array $headers): array
    {
        $formatted = HeadingRowFormatter::format($headers);

        return collect($headers)
            ->map(static fn (string $label, int $index): array => [
                'label' => $label,
                'key' => is_string($formatted[$index] ?? null) ? $formatted[$index] : $label,
            ])
            ->values()
            ->all();
    }
}
