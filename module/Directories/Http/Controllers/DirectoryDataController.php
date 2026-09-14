<?php

declare(strict_types=1);

namespace Module\Directories\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Module\Directories\Http\Resources\JsonApi\DirectoryDataItemResource;
use Module\Directories\DTO\DirectoryQuery;
use Module\Directories\Models\DirectoryVersion;
use Module\Directories\Repositories\DirectoryRepository;
use Module\Directories\Services\DirectoryManager;
use Module\Directories\Services\RelatedDirectoryFieldResolver;

final class DirectoryDataController extends Controller
{
    public function __construct(
        private readonly DirectoryManager $directories,
        private readonly RelatedDirectoryFieldResolver $relatedFieldResolver,
        private readonly DirectoryRepository $directoryRepository,
    ) {
    }

    public function show(Request $request, string $code): AnonymousResourceCollection
    {
        $directory = $this->directoryRepository->findActiveBySlugOrFail($code);

        $page = $this->directories->paginate($directory, DirectoryQuery::fromRequest($request));

        /** @var array<int, array<string, mixed>> $data */
        $data = $page->items;

        $version = $directory->activeVersion()->first();
        $schema = $version instanceof DirectoryVersion ? $version->schema_json : [];
        $related = $this->relatedFieldResolver->resolve($data, $schema);
        foreach ($data as $index => $row) {
            $data[$index]['related'] = $related[$index] ?? [];
        }

        return DirectoryDataItemResource::collection($data)
            ->additional($page->additional());
    }
}
