<?php

declare(strict_types=1);

namespace Module\Proxy\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Module\Projects\CurrentProject;
use Module\Proxy\DTO\ProxyField;
use Module\Proxy\Http\Resources\JsonApi\ProxyFieldResource;
use Module\Proxy\Repositories\ProxyEndpointRepository;
use Module\Proxy\Services\HandlerResolver;

final class ProxyFieldsController extends Controller
{
    public function __construct(
        private readonly HandlerResolver $handlers,
        private readonly CurrentProject $currentProject,
        private readonly ProxyEndpointRepository $endpoints,
    ) {}

    public function __invoke(string $uuid): AnonymousResourceCollection
    {
        $endpoint = $this->endpoints->findActiveByUuidForProjectOrFail($uuid, $this->currentProject->id());

        $handler = $this->handlers->resolve($endpoint);
        $fields = [];

        foreach ($handler->requestFields() as $field) {
            if ($field instanceof ProxyField) {
                $fields[] = $field->toArray();
            }
        }

        return ProxyFieldResource::collection($fields);
    }
}
