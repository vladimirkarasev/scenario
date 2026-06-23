<?php

declare(strict_types=1);

namespace Module\Directories\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Module\Directories\DTO\DirectoryFeedData;
use Module\Directories\Http\Requests\DirectoryFeedRequest;
use Module\Directories\Services\DirectoryFeedService;
use Module\Projects\CurrentProject;

final class DirectoryFeedController extends Controller
{
    public function __construct(
        private readonly DirectoryFeedService $feed,
        private readonly CurrentProject $currentProject,
    ) {
    }

    public function __invoke(DirectoryFeedRequest $request): JsonResponse
    {
        return new JsonResponse(
            $this->feed->feed(
                $this->currentProject->id(),
                DirectoryFeedData::fromRequest($request),
            ),
        );
    }
}
