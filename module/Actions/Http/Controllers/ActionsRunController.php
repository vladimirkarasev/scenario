<?php

declare(strict_types=1);

namespace Module\Actions\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Module\Actions\DTO\RunActionsData;
use Module\Actions\Http\Requests\RunActionsRequest;
use Module\Actions\Services\ActionOrchestratorService;

final class ActionsRunController extends Controller
{
    public function __construct(
        private readonly ActionOrchestratorService $orchestrator,
    ) {}

    public function __invoke(RunActionsRequest $request): ApiResponse
    {
        $data = RunActionsData::fromRequest($request);

        $result = $this->orchestrator->runFromData($data);

        $statusCode = match ($result['status'] ?? null) {
            'scheduled' => 200,
            'error' => 422,
            default => 202,
        };

        return new ApiResponse($result, $statusCode);
    }
}
