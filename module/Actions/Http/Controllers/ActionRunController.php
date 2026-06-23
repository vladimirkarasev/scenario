<?php

declare(strict_types=1);

namespace Module\Actions\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Module\Actions\DTO\ActionRunIndexData;
use Module\Actions\Enums\ActionRunStatus;
use Module\Actions\Services\ActionRunService;

final class ActionRunController extends Controller
{
    public function __construct(
        private readonly ActionRunService $actionRunService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $items = $this->actionRunService->items(ActionRunIndexData::fromRequest($request));

        return new JsonResponse([
            'items' => $items,
            'meta' => ['total' => count($items)],
        ]);
    }

    public function completed(Request $request): JsonResponse
    {
        $items = $this->actionRunService->items(
            ActionRunIndexData::fromRequest($request, ActionRunStatus::Success->value),
        );

        return new JsonResponse([
            'items' => $items,
            'meta' => ['total' => count($items)],
        ]);
    }

    public function failed(Request $request): JsonResponse
    {
        $items = $this->actionRunService->items(
            ActionRunIndexData::fromRequest($request, ActionRunStatus::Failed->value),
        );

        return new JsonResponse([
            'items' => $items,
            'meta' => ['total' => count($items)],
        ]);
    }
}
