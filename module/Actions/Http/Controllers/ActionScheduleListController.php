<?php

declare(strict_types=1);

namespace Module\Actions\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Module\Actions\Services\ActionScheduleService;

final class ActionScheduleListController extends Controller
{
    public function __construct(private readonly ActionScheduleService $schedules) {}

    public function __invoke(): JsonResponse
    {
        $items = $this->schedules->listPayload();

        return new JsonResponse(['items' => $items, 'meta' => ['total' => count($items)]]);
    }
}
