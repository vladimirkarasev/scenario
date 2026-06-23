<?php

declare(strict_types=1);

namespace Module\Actions\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Module\Actions\DTO\ActionIndexData;
use Module\Actions\Http\Resources\JsonApi\ActionResource;
use Module\Actions\Services\ActionService;

final class ActionListController extends Controller
{
    public function __construct(
        private readonly ActionService $actionService,
    ) {
    }

    public function __invoke(Request $request): AnonymousResourceCollection
    {
        return ActionResource::collection($this->actionService->paginate(ActionIndexData::fromRequest($request)));
    }
}
