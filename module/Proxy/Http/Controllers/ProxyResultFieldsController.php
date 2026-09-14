<?php

declare(strict_types=1);

namespace Module\Proxy\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Module\Projects\CurrentProject;
use Module\Proxy\Http\Resources\JsonApi\ProxyFieldResource;
use Module\Proxy\Repositories\ProxyEndpointRepository;
use Module\Proxy\Services\ProxyResponseFieldCatalog;

final class ProxyResultFieldsController extends Controller
{
    public function __construct(
        private readonly ProxyResponseFieldCatalog $fields,
        private readonly CurrentProject $currentProject,
        private readonly ProxyEndpointRepository $endpoints,
    ) {}

    public function __invoke(string $uuid): AnonymousResourceCollection
    {
        $endpoint = $this->endpoints->findActiveByUuidForProjectOrFail($uuid, $this->currentProject->id());

        $fields = [];

        foreach ($this->fields->forEndpoint($endpoint) as $field) {
            $fields[] = $field->toArray();
        }

        return ProxyFieldResource::collection($fields);
    }
}
