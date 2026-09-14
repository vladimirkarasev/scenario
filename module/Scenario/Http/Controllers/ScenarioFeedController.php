<?php

declare(strict_types=1);

namespace Module\Scenario\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Module\Projects\CurrentProject;
use Module\Scenario\DTO\ScenarioFeedData;
use Module\Scenario\Http\Requests\ScenarioFeedRequest;
use Module\Scenario\Services\Catalog\ScenarioFeedService;

final class ScenarioFeedController extends Controller
{
    public function __construct(
        private readonly ScenarioFeedService $feed,
        private readonly CurrentProject $currentProject,
    ) {
    }

    public function __invoke(ScenarioFeedRequest $request): ApiResponse
    {
        $feed = $this->feed->feed($this->resolveProjectId($request), ScenarioFeedData::fromRequest($request));

        return new ApiResponse(
            $feed['rows'],
            meta: [...$feed['pagination'], 'counts_by_status' => $feed['counts_by_status']],
        );
    }

    private function resolveProjectId(ScenarioFeedRequest $request): ?string
    {
        $raw = $request->input('filter.project_id');

        return is_string($raw) && $raw !== '' ? $raw : $this->currentProject->id();
    }
}
