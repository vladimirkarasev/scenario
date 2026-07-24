<?php

declare(strict_types=1);

namespace Module\Proxy\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Module\Projects\CurrentProject;
use Module\Proxy\DTO\ProxyField;
use Module\Proxy\Http\Resources\JsonApi\ProxyFieldResource;
use Module\Proxy\Models\ProxyEndpoint;
use Module\Proxy\Services\HandlerResolver;

final class ProxyResultFieldsController extends Controller
{
    public function __construct(
        private readonly HandlerResolver $handlers,
        private readonly CurrentProject $currentProject,
    ) {}

    public function __invoke(string $uuid): AnonymousResourceCollection
    {
        $endpoint = ProxyEndpoint::query()
            ->where('uuid', $uuid)
            ->where('project_id', $this->currentProject->id())
            ->where('is_active', true)
            ->firstOrFail();

        $handler = $this->handlers->resolve($endpoint);
        $fields = [];

        foreach ($handler->resultFields() as $field) {
            if ($field instanceof ProxyField) {
                $fields[] = $field->toArray();
            }
        }

        return ProxyFieldResource::collection($fields);
    }
}
