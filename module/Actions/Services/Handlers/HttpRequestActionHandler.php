<?php

declare(strict_types=1);

namespace Module\Actions\Services\Handlers;

use Illuminate\Support\Facades\Http;
use Module\Actions\Contracts\ActionHandlerInterface;
use Module\Actions\DTO\ActionResult;
use Module\Actions\Models\Action;
use Module\Actions\Services\ActionCredentialResolver;
use Module\Actions\Services\ActionDataResolver;
use Module\Actions\Services\Handlers\Concerns\HasNoConfigFields;

final class HttpRequestActionHandler implements ActionHandlerInterface
{
    use HasNoConfigFields;

    public function __construct(
        private readonly ActionDataResolver $dataResolver,
        private readonly ActionCredentialResolver $credentialResolver,
    ) {}

    /** @param array<string, mixed> $input */
    public function handle(Action $action, array $input = []): ActionResult
    {
        $resolvedConfig = $this->dataResolver->resolveActionConfig($action, $input);
        $credentialId = $resolvedConfig['credential_id'] ?? null;
        $resolvedCredentials = $this->credentialResolver->resolve(is_numeric($credentialId) ? (int) $credentialId : null);

        $method = strtoupper(is_string($resolvedConfig['method'] ?? null) ? (string) $resolvedConfig['method'] : 'POST');
        $url = $resolvedConfig['url'] ?? null;

        if (! is_string($url) || $url === '') {
            return ActionResult::failed('Action config url is required.');
        }

        $headers = is_array($resolvedConfig['headers'] ?? null) ? $resolvedConfig['headers'] : [];
        $configQuery = is_array($resolvedConfig['query'] ?? null) ? $resolvedConfig['query'] : [];

        $timeout = $resolvedConfig['timeout'] ?? 15;
        $retryCount = $resolvedConfig['retry_count'] ?? 0;

        $request = Http::timeout(is_numeric($timeout) ? (int) $timeout : 15)
            ->retry(is_numeric($retryCount) ? (int) $retryCount : 0, 250)
            ->withHeaders([
                ...$resolvedCredentials['headers'],
                ...$headers,
            ]);

        $query = [
            ...$resolvedCredentials['query'],
            ...$configQuery,
        ];

        $body = $resolvedConfig['body'] ?? [];
        $bodyType = is_string($resolvedConfig['body_type'] ?? null) ? (string) $resolvedConfig['body_type'] : 'json';
        $contentType = is_string($resolvedConfig['content_type'] ?? null) ? (string) $resolvedConfig['content_type'] : 'text/plain';

        $response = match ($bodyType) {
            'form' => $request->asForm()->send($method, $url, ['query' => $query, 'form_params' => $body]),
            'raw' => $request->withBody(is_scalar($body) ? (string) $body : '', $contentType)->send($method, $url, ['query' => $query]),
            default => $request->send($method, $url, ['query' => $query, 'json' => $body]),
        };

        if ($response->failed()) {
            return ActionResult::failed(
                error: sprintf('HTTP request failed with status %d.', $response->status()),
                output: [
                    'status' => $response->status(),
                    'headers' => $response->headers(),
                    'body' => $response->json() ?? $response->body(),
                ],
            );
        }

        return ActionResult::success([
            'status' => $response->status(),
            'headers' => $response->headers(),
            'body' => $response->json() ?? $response->body(),
        ]);
    }
}
