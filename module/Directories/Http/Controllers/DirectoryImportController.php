<?php

declare(strict_types=1);

namespace Module\Directories\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Module\Directories\DTO\ExcelImportData;
use Module\Directories\DTO\ExcelDirectoryImportCommand;
use Module\Directories\Http\Requests\StoreDirectoryImportRequest;
use Module\Directories\Http\Resources\JsonApi\DirectoryImportResource;
use Module\Directories\Models\Directory;
use Module\Directories\Models\DirectoryImport;
use Module\Directories\Repositories\DirectoryImportRepository;
use Module\Directories\Services\DirectoryService;
use Module\Directories\Services\DirectoryManager;

final class DirectoryImportController extends Controller
{
    public function __construct(
        private readonly DirectoryManager $directories,
        private readonly DirectoryService $directoryService,
        private readonly DirectoryImportRepository $imports,
    ) {
    }

    public function index(Request $request, Directory $directory): AnonymousResourceCollection
    {
        $this->directoryService->ensureProjectAccess(
            $directory,
            $this->directoryService->currentProjectForUser($request->user()),
        );

        $rawVersionId = $request->query('version_id');
        $versionId = is_numeric($rawVersionId) ? (int) $rawVersionId : null;
        $imports = $this->imports->paginateForDirectory($directory, $versionId, 50);
        $payloads = $imports->getCollection()
            ->map(fn(DirectoryImport $import): array => $this->directoryService->importPayload($import))
            ->values();

        return DirectoryImportResource::collection($payloads)->additional([
            'meta' => [
                'current_page' => $imports->currentPage(),
                'last_page' => $imports->lastPage(),
                'total' => $imports->total(),
            ],
        ]);
    }

    public function store(StoreDirectoryImportRequest $request, Directory $directory): JsonResponse
    {
        $this->directoryService->ensureProjectAccess(
            $directory,
            $this->directoryService->currentProjectForUser($request->user()),
        );

        $import = $this->directories->import(
            new ExcelDirectoryImportCommand(
                ExcelImportData::fromRequest($request, $directory),
            ),
        );

        return new DirectoryImportResource($this->directoryService->importPayload($import))
            ->response()
            ->setStatusCode(202);
    }
}
