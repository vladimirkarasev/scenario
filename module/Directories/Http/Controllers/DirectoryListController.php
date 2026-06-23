<?php

declare(strict_types=1);

namespace Module\Directories\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Module\Directories\Http\Resources\JsonApi\DirectoryResource;
use Module\Directories\Services\DirectoryService;

final class DirectoryListController extends Controller
{
    public function __construct(
        private readonly DirectoryService $directoryService,
    ) {}

    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $project = $this->directoryService->currentProjectForUser($request->user());

        $page = max(1, $request->integer('page.number', 1));
        $perPage = min(100, max(1, $request->integer('page.size', 20)));

        /** @var string[] $categoryIds */
        $categoryIds = array_values(array_filter(
            (array) $request->input('filter.category_ids', []),
            static fn (mixed $id): bool => is_string($id) && $id !== '',
        ));
        $uncategorized = $request->boolean('filter.uncategorized');

        $result = $this->directoryService->paginate($project, $page, $perPage, $categoryIds, $uncategorized);

        return DirectoryResource::collection($result['items'])
            ->additional(['meta' => $result['meta']]);
    }
}
