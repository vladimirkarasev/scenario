<?php

declare(strict_types=1);

namespace Module\Scenario\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Module\Users\Models\User;
use Module\Scenario\DTO\ScenarioDispatchData;
use Module\Scenario\Http\Requests\DispatchScenarioRequest;
use Module\Scenario\Services\Runtime\ScenarioDispatchService;

final class ScenarioDispatchController extends Controller
{
    public function __construct(
        private readonly ScenarioDispatchService $dispatcher,
    ) {
    }

    public function __invoke(DispatchScenarioRequest $request): ApiResponse
    {
        /** @var User $serviceUser */
        $serviceUser = $request->user();

        $runId = $this->dispatcher->dispatch(
            $serviceUser,
            ScenarioDispatchData::fromRequest($request),
        );

        return new ApiResponse(['run_id' => $runId], 202);
    }
}
