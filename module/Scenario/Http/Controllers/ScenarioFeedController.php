<?php

declare(strict_types=1);

namespace Module\Scenario\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Module\Projects\CurrentProject;
use Module\Scenario\DTO\ScenarioFeedData;
use Module\Scenario\Http\Requests\ScenarioFeedRequest;
use Module\Scenario\Services\ScenarioFeedService;

final class ScenarioFeedController extends Controller
{
    public function __construct(
        private readonly ScenarioFeedService $feed,
        private readonly CurrentProject $currentProject,
    ) {
    }

    public function __invoke(ScenarioFeedRequest $request): JsonResponse
    {
        return new JsonResponse(
            $this->feed->feed($this->resolveProjectId($request), ScenarioFeedData::fromRequest($request)),
        );
    }

    /**
     * Проект берём из запроса (фронт получает его из /api/user), иначе — из токена.
     */
    private function resolveProjectId(ScenarioFeedRequest $request): ?string
    {
        $raw = $request->input('filter.project_id');

        return is_string($raw) && $raw !== '' ? $raw : $this->currentProject->id();
    }
}
