<?php

declare(strict_types=1);

namespace Module\Directories\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Module\Directories\Http\Resources\JsonApi\DirectoryDataItemResource;
use Module\Directories\Models\Directory;
use Module\Directories\Services\DirectoryCacheService;
use Module\Directories\Services\DirectoryExternalDataService;

final class DirectoryDataController extends Controller
{
    public function __construct(
        private readonly DirectoryCacheService $cacheService,
        private readonly DirectoryExternalDataService $externalDataService,
    ) {
    }

    public function show(Request $request, string $code): AnonymousResourceCollection
    {
        $directory = Directory::query()
            ->where('slug', $code)
            ->whereHas('activeVersion')
            ->firstOrFail();

        /** @var array<string, mixed> $query */
        $query = $request->query();

        $result = $directory->source_type === 'external'
            ? $this->externalDataService->activeData($directory, $query)
            : $this->cacheService->activeData($directory, $query);

        /** @var array<int, array<string, mixed>> $data */
        $data = $result['data'];

        /** @var array<string, mixed> $paginationMeta */
        $paginationMeta = $result['meta'];

        /** @var array<string, mixed> $dictionary */
        $dictionary = $result['dictionary'];

        return DirectoryDataItemResource::collection($data)
            ->additional([
                'meta' => [
                    ...$paginationMeta,
                    'dictionary' => $dictionary,
                ],
            ]);
    }
}
