<?php

declare(strict_types=1);

namespace Module\Scenario\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Module\Scenario\DTO\ScenarioDispatchData;
use Module\Scenario\Http\Requests\DispatchScenarioRequest;
use Module\Scenario\Services\ScenarioDispatchService;
use RuntimeException;

final class ScenarioDispatchController extends Controller
{
    public function __construct(
        private readonly ScenarioDispatchService $dispatcher,
    ) {
    }

    public function __invoke(DispatchScenarioRequest $request): JsonResponse
    {
        /** @var User $serviceUser */
        $serviceUser = $request->user();

        try {
            $runId = $this->dispatcher->dispatch(
                $serviceUser,
                ScenarioDispatchData::fromRequest($request),
            );
        } catch (RuntimeException $e) {
            return new JsonResponse(['message' => $e->getMessage()], 404);
        }

        return new JsonResponse(['run_id' => $runId], 202);
    }
}
