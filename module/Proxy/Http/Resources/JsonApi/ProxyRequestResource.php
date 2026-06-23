<?php

declare(strict_types=1);

namespace Module\Proxy\Http\Resources\JsonApi;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Module\Proxy\Models\ProxyRequest;

/**
 * @mixin ProxyRequest
 */
final class ProxyRequestResource extends JsonApiResource
{
    protected bool $usesRequestQueryString = false;

    public function toId(Request $request): string
    {
        return (string) $this->id;
    }

    public function toType(Request $request): string
    {
        return 'proxy-requests';
    }

    /** @return array<string, mixed> */
    public function toAttributes(Request $request): array
    {
        $req = $this->request ?? [];
        $resp = $this->response ?? [];

        $attributes = [
            'request_id' => $this->request_id,
            'endpoint' => $this->endpoint?->name,
            'endpoint_id' => $this->proxy_endpoint_id,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),
            'is_mocked' => $this->is_mocked,
            'ip' => $req['ip'] ?? null,
            'error' => $resp['error'] ?? null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];

        if (! $request->routeIs('api.proxy.requests.show')) {
            return $attributes;
        }

        return $attributes + [
            'method' => $req['method'] ?? null,
            'path' => $req['path'] ?? null,
            'user_agent' => $req['user_agent'] ?? null,
            'received_at' => $this->received_at?->toIso8601String(),
            'processed_at' => $this->processed_at?->toIso8601String(),
            'payload' => $req['payload'] ?? null,
            'query_params' => $req['query'] ?? null,
            'headers_masked' => $req['headers'] ?? null,
            'normalized_data' => $this->normalized_data,
            'message_box' => $this->message_box,
            'response_code' => $resp['status_code'] ?? null,
            'response_headers' => $resp['headers'] ?? null,
            'response_body' => $resp['body'] ?? null,
        ];
    }
}
