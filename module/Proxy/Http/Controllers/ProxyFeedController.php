<?php

declare(strict_types=1);

namespace Module\Proxy\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Module\Projects\CurrentProject;
use Module\Proxy\DTO\ProxyFeedData;
use Module\Proxy\Http\Requests\ProxyFeedRequest;
use Module\Proxy\Http\Resources\JsonApi\ProxyFeedResource;
use Module\Proxy\Services\ProxyFeedService;

final class ProxyFeedController extends Controller
{
    public function __construct(
        private readonly ProxyFeedService $feed,
        private readonly CurrentProject $currentProject,
    ) {}

    public function __invoke(ProxyFeedRequest $request): AnonymousResourceCollection
    {
        $data = ProxyFeedData::fromRequest($request);
        $projectId = $this->currentProject->id()
            ?? throw new \LogicException('Proxy feed requires a current project.');
        $result = $this->feed->feed($projectId, $data);
        $pagination = $result['pagination'];

        $paginator = new LengthAwarePaginator(
            $result['data'],
            $pagination['total'],
            $pagination['per_page'],
            $pagination['current_page'],
            [
                'path' => $request->url(),
                'pageName' => 'page[number]',
            ],
        );

        return ProxyFeedResource::collection($paginator)->additional([
            'meta' => [
                'folders_total' => $pagination['folders_total'],
                'items_total' => $pagination['items_total'],
            ],
        ]);
    }
}
