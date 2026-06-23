<?php

declare(strict_types=1);

namespace Module\Proxy\Services;

use Module\Proxy\DTO\ProxyContext;
use Module\Proxy\DTO\ProxyExecutionResult;
use Module\Proxy\DTO\ProxyResponse;
use Module\Proxy\Enums\ProxyRequestStatus;
use Module\Proxy\Models\ProxyEndpoint;
use Module\Proxy\Models\ProxyRequest;
use Throwable;

/**
 * Единая точка вызова proxy для любых потребителей (приёмник вебхуков, контроллер,
 * фоновая синхронизация справочника). Сам решает: вернуть mock или дёрнуть handler.
 */
final readonly class ProxyExecutor
{
    public function __construct(
        private HandlerResolver $handlerResolver,
        private MockResponseResolver $mockResolver,
        private ProxyRequestLoggerService $requestLogger,
    ) {
    }

    /**
     * Выполнить вызов прокси. Возвращает результат с флагом был ли использован mock.
     *
     * @param  array<string, mixed>  $normalizedData  Используется для match-логики мока.
     */
    public function execute(
        ProxyEndpoint $endpoint,
        ProxyContext $context,
        array $normalizedData = []
    ): ProxyExecutionResult {
        if ($endpoint->is_mocked) {
            $mock = $this->mockResolver->resolve($endpoint, $normalizedData);
            if ($mock !== null) {
                return new ProxyExecutionResult($mock, true);
            }
        }

        $response = $this->handlerResolver->resolve($endpoint)->handle($context);

        return new ProxyExecutionResult($response, false);
    }

    /**
     * Программный вызов прокси с логированием в `proxy_requests`. Используется
     * там, где нет реального HTTP-запроса (фоновая синхронизация справочника,
     * запросы из других модулей).
     *
     * @param  array<string, mixed>  $normalizedData
     * @param  array<string, mixed>  $meta  Произвольная мета для лога (caller и т.п.)
     *
     * @throws Throwable
     */
    public function executeLogged(
        ProxyEndpoint $endpoint,
        ProxyContext $context,
        array $normalizedData = [],
        array $meta = [],
    ): ProxyResponse {
        $request = $this->createInternalRequest($endpoint, $context, $normalizedData, $meta);

        try {
            $result = $this->execute($endpoint, $context, $normalizedData);
            $response = $result->response->withRequestId($context->requestId());

            $this->requestLogger->markProcessed($request, $response, $result->mocked);

            return $response;
        } catch (Throwable $exception) {
            $this->requestLogger->markFailed($request, $exception);

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $normalizedData
     * @param  array<string, mixed>  $meta
     */
    private function createInternalRequest(
        ProxyEndpoint $endpoint,
        ProxyContext $context,
        array $normalizedData,
        array $meta,
    ): ProxyRequest {
        return ProxyRequest::query()->create([
            'proxy_endpoint_id' => $endpoint->id,
            'request_id' => $context->requestId(),
            'status' => ProxyRequestStatus::Received,
            'request' => [
                'method' => 'INTERNAL',
                'path' => $endpoint->code,
                'ip' => null,
                'user_agent' => is_string($meta['caller'] ?? null) ? $meta['caller'] : 'internal',
                'headers' => [],
                'query' => $context->query(),
                'payload' => $context->payload(),
                'meta' => $meta,
            ],
            'normalized_data' => $normalizedData,
            'received_at' => now(),
        ]);
    }
}
