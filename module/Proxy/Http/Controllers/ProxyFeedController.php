<?php

declare(strict_types=1);

namespace Module\Proxy\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Module\Projects\CurrentProject;
use Module\Proxy\DTO\ProxyFeedData;
use Module\Proxy\Http\Requests\ProxyFeedRequest;
use Module\Proxy\Services\ProxyFeedService;

final class ProxyFeedController extends Controller
{
    public function __construct(
        private readonly ProxyFeedService $feed,
        private readonly CurrentProject $currentProject,
    ) {}

    public function __invoke(ProxyFeedRequest $request): JsonResponse
    {
        return new JsonResponse(
            $this->feed->feed(
                $this->currentProject->id(),
                ProxyFeedData::fromRequest($request),
            ),
        );
    }
}
