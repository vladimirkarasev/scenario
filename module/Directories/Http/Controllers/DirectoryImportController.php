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
use Module\Directories\Services\DirectoryService;
use Module\Directories\Services\DirectoryManager;

final class DirectoryImportController extends Controller
{
    public function __construct(
        private readonly DirectoryManager $directories,
        private readonly DirectoryService $directoryService,
    ) {
    }

    public function index(Request $request, Directory $directory): AnonymousResourceCollection
    {
        $this->directoryService->ensureProjectAccess(
            $directory,
            $this->directoryService->currentProjectForUser($request->user()),
        );

        $query = DirectoryImport::query()
            ->where('directory_id', $directory->id)
            ->latest();

        if ($versionId = $request->query('version_id')) {
            $query->where('directory_version_id', (int)$versionId);
        }

        $imports = $query->paginate(50);

        return DirectoryImportResource::collection(
            $imports->map(fn(DirectoryImport $import): array => $this->directoryService->importPayload($import),
            )->values()->all(),
        )->additional([
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
