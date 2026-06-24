<?php

declare(strict_types=1);

namespace Module\Actions\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Module\Actions\DTO\ActionFeedData;
use Module\Actions\Http\Requests\ActionFeedRequest;
use Module\Actions\Services\ActionFeedService;
use Module\Projects\CurrentProject;

final class ActionFeedController extends Controller
{
    public function __construct(
        private readonly ActionFeedService $feed,
        private readonly CurrentProject $currentProject,
    ) {}

    public function __invoke(ActionFeedRequest $request): JsonResponse
    {
        return new JsonResponse(
            $this->feed->feed(
                $this->currentProject->id(),
                ActionFeedData::fromRequest($request),
            ),
        );
    }
}
