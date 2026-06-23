<?php

declare(strict_types=1);

namespace Module\Actions\Services\Handlers;

use Illuminate\Http\Request;
use Module\Actions\Contracts\ActionHandlerInterface;
use Module\Actions\DTO\ActionResult;
use Module\Actions\Models\Action;
use Module\Actions\Services\ActionDataResolver;
use Module\Actions\Services\Handlers\Concerns\HasNoConfigFields;
use Module\Proxy\DTO\ProxyResponse;
use Module\Proxy\Services\ProxyReceiverService;

final class ProxyRequestActionHandler implements ActionHandlerInterface
{
    use HasNoConfigFields;

    public function __construct(
        private readonly ActionDataResolver $dataResolver,
        private readonly ProxyReceiverService $proxyReceiver,
    ) {}

    /** @param array<string, mixed> $input */
    public function handle(Action $action, array $input = []): ActionResult
    {
        $resolvedConfig = $this->dataResolver->resolve($action->config ?? [], $this->dataResolver->contextForAction($action, $input));
        $config = is_array($resolvedConfig) ? $this->stringKeyed($resolvedConfig) : [];
        $endpointUuid = $config['endpoint_uuid'] ?? null;

        if (! is_string($endpointUuid) || $endpointUuid === '') {
            return ActionResult::failed('Proxy action requires `endpoint_uuid` in config.');
        }

        $response = $this->proxyReceiver->receiveHttp(
            $this->requestFromConfig($endpointUuid, $config, $input),
            $endpointUuid,
        );

        return $this->resultFromProxyResponse($response);
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $input
     */
    private function requestFromConfig(string $endpointUuid, array $config, array $input): Request
    {
        $method = strtoupper($this->stringValue($config['method'] ?? null, 'POST'));
        $payload = $config['payload'] ?? $input;
        $query = $config['query'] ?? [];
        $headers = $config['headers'] ?? [];
        $request = Request::create(
            uri: "/proxy/{$endpointUuid}",
            method: $method,
            parameters: is_array($payload) ? $payload : ['value' => $payload],
            server: $this->serverHeaders(is_array($headers) ? $headers : []),
            content: $method === 'GET'
                ? null
                : (json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: null),
        );

        if (is_array($query)) {
            $request->query->replace($this->stringKeyed($query));
        }

        if ($method !== 'GET') {
            $request->headers->set('content-type', 'application/json');
        }

        return $request;
    }

    private function resultFromProxyResponse(ProxyResponse $response): ActionResult
    {
        if ($response->statusCode >= 400) {
            return ActionResult::failed('Proxy request failed.', [
                'status' => $response->statusCode,
                'headers' => $response->headers,
                'body' => $response->body,
            ]);
        }

        return ActionResult::success([
            'status' => $response->statusCode,
            'headers' => $response->headers,
            'body' => $response->body,
        ]);
    }

    /**
     * @param  array<mixed, mixed>   $headers
     * @return array<string, string>
     */
    private function serverHeaders(array $headers): array
    {
        $server = [];

        foreach ($headers as $name => $value) {
            if (! is_scalar($value)) {
                continue;
            }

            $key = 'HTTP_'.strtoupper(str_replace('-', '_', (string) $name));
            $server[$key] = (string) $value;
        }

        return $server;
    }

    /**
     * @param  array<mixed, mixed>  $items
     * @return array<string, mixed>
     */
    private function stringKeyed(array $items): array
    {
        $result = [];

        foreach ($items as $key => $value) {
            $result[(string) $key] = $value;
        }

        return $result;
    }

    private function stringValue(mixed $value, string $default): string
    {
        return is_scalar($value) ? (string) $value : $default;
    }
}
