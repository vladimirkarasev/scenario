<?php

declare(strict_types=1);

namespace Module\Proxy\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Module\Proxy\DTO\ProxyField;
use Module\Proxy\Models\ProxyEndpoint;
use Module\Proxy\Services\HandlerResolver;

final class ProxyFieldsController extends Controller
{
    public function __construct(private readonly HandlerResolver $handlers) {}

    public function __invoke(string $uuid): JsonResponse
    {
        $endpoint = ProxyEndpoint::query()
            ->where('uuid', $uuid)
            ->where('is_active', true)
            ->firstOrFail();

        $handler = $this->handlers->resolve($endpoint);
        $fields = [];

        foreach ($handler->fields() as $field) {
            if ($field instanceof ProxyField) {
                $fields[] = $field->toArray();
            }
        }

        return new JsonResponse(['items' => $fields]);
    }
}
