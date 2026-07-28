<?php

declare(strict_types=1);

namespace Module\Proxy\Services;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Module\Proxy\DTO\ProxyContext;
use Module\Proxy\DTO\ProxyField;
use Module\Proxy\DTO\ProxyResponse;
use Module\Proxy\Events\ProxyRequestAccepted;
use Module\Proxy\Events\ProxyRequestFailed;
use Module\Proxy\Events\ProxyRequestProcessed;
use Module\Proxy\Events\ProxyRequestRejected;
use Module\Proxy\Exceptions\ProxyEndpointInactiveException;
use Module\Proxy\Exceptions\ProxyMethodNotAllowedException;
use Module\Proxy\Exceptions\ProxyPayloadTooLargeException;
use Module\Proxy\Models\ProxyEndpoint;
use Module\Proxy\Models\ProxyRequest;
use Psr\Log\LoggerInterface;
use Throwable;

final readonly class ProxyReceiverService
{
    public function __construct(
        private HandlerResolver $handlerResolver,
        private FieldResolver $fieldResolver,
        private ValidationRulesBuilder $rulesBuilder,
        private ProxyContextFactory $proxyContextFactory,
        private ProxyRequestLoggerService $requestLogger,
        private ProxyExecutor $executor,
        private LoggerInterface $logger,
    ) {}

    public function receiveHttp(Request $request, string $uuid): ProxyResponse
    {
        $endpoint = ProxyEndpoint::query()->where('uuid', $uuid)->firstOrFail();

        if (! $endpoint->is_active) {
            throw new ProxyEndpointInactiveException;
        }

        if (strtoupper($request->method()) !== strtoupper($endpoint->method ?? 'POST')) {
            throw new ProxyMethodNotAllowedException;
        }

        $this->assertPayloadSize($request);

        $handler = $this->handlerResolver->resolve($endpoint);
        $requestId = $this->newRequestId();
        $log = (new ContextualLogger($this->logger))->with(['request_id' => $requestId]);
        $payload = $this->payloadFromRequest($request, $endpoint);
        $query = $request->query->all();
        $headers = $this->normalizeHeaders($request->headers->all());
        $files = $request->allFiles();
        $system = $this->system($requestId, $request->ip());
        $fields = $this->fields($handler->requestFields());
        $normalizedData = $this->fieldResolver->resolve($fields, $payload, $query, $headers, $system, $files);

        $proxyRequest = $this->requestLogger->createFromHttp(
            $request,
            $endpoint,
            $requestId,
            $payload,
            $query,
            $headers,
            $normalizedData,
        );

        if ($files !== []) {
            $log->info(
                'Proxy received files',
                $this->logContext($proxyRequest) + [
                    'files' => $this->fileSummary($files),
                ]
            );
        }

        $box = $this->proxyContextFactory->fromHttp(
            $request,
            $endpoint,
            $proxyRequest,
            $payload,
            $query,
            $headers,
            $normalizedData,
            $system,
            ['external_request_id' => $headers['x-request-id'] ?? null],
        );

        return $this->process($log, $proxyRequest, $box, $fields, $normalizedData);
    }

    /**
     * @param array<int, ProxyField> $fields
     * @param array<string, mixed>   $normalizedData
     */
    private function process(
        ContextualLogger $log,
        ProxyRequest $proxyRequest,
        ProxyContext $box,
        array $fields,
        array $normalizedData
    ): ProxyResponse {
        $context = $this->logContext($proxyRequest);

        try {
            Validator::make(
                $normalizedData,
                $this->rulesBuilder->build($fields),
            )->validate();

            Event::dispatch(new ProxyRequestAccepted($proxyRequest, $box));
            $log->info('Proxy accepted', $context);

            $result = $this->executor->execute($box->endpoint, $box, $normalizedData);
            $response = $result->response->withRequestId($box->requestId());
            $box = $box->withResponse($response);

            Event::dispatch(new ProxyRequestProcessed($proxyRequest, $box, $result->mocked));
            $log->info(
                $result->mocked ? 'Proxy mocked' : 'Proxy processed',
                $context + ['response_code' => $response->statusCode],
            );

            return $response;
        } catch (ValidationException $exception) {
            Event::dispatch(new ProxyRequestRejected($proxyRequest, $box, $exception));
            $log->warning('Proxy rejected', $context + ['errors' => $exception->errors()]);

            return ProxyResponse::error('Proxy validation failed', 422)->withRequestId($box->requestId());
        } catch (Throwable $exception) {
            $log->error(
                'Proxy handler failed',
                $context + [
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]
            );

            Event::dispatch(new ProxyRequestFailed($proxyRequest, $box, $exception));

            return ProxyResponse::error('Proxy processing failed', 500)->withRequestId($box->requestId());
        }
    }

    /**
     * @param  iterable<mixed>        $fields
     * @return array<int, ProxyField>
     */
    private function fields(iterable $fields): array
    {
        $items = [];

        foreach ($fields as $field) {
            if (! $field instanceof ProxyField) {
                continue;
            }

            $items[] = $field;
        }

        return $items;
    }

    /** @return array<string, mixed> */
    private function payloadFromRequest(Request $request, ProxyEndpoint $endpoint): array
    {
        if (strtoupper($endpoint->method ?? 'POST') === 'GET') {
            $filter = $request->query('filter');
            if (! is_array($filter)) {
                return [];
            }

            return $this->toStringKeyed($filter);
        }

        $payload = $request->json()->all();

        if ($payload === []) {
            $payload = $request->request->all();
        }

        return $this->toStringKeyed($payload);
    }

    /**
     * @param  array<mixed, mixed>  $array
     * @return array<string, mixed>
     */
    private function toStringKeyed(array $array): array
    {
        $result = [];
        foreach ($array as $k => $v) {
            $result[(string) $k] = $v;
        }

        return $result;
    }

    /**
     * @param  array<string, array<string|null>> $headers
     * @return array<string, mixed>
     */
    private function normalizeHeaders(array $headers): array
    {
        $normalized = [];

        foreach ($headers as $key => $values) {
            $normalized[strtolower((string) $key)] = count($values) === 1 ? $values[0] : $values;
        }

        return $normalized;
    }

    /** @return array<string, mixed> */
    private function system(string $requestId, ?string $ip): array
    {
        return [
            'request_id' => $requestId,
            'received_at' => now()->toISOString(),
            'ip' => $ip,
        ];
    }

    /** @return array<string, mixed> */
    private function logContext(ProxyRequest $request): array
    {
        return [
            'endpoint_id' => $request->proxy_endpoint_id,
            'proxy_request_id' => $request->id,
        ];
    }

    /**
     * @param  array<string, UploadedFile|array<UploadedFile>> $files
     * @return array<string, mixed>
     */
    private function fileSummary(array $files): array
    {
        $summary = [];

        foreach ($files as $field => $file) {
            if ($file instanceof UploadedFile) {
                $summary[$field] = [
                    'name' => $file->getClientOriginalName(),
                    'size' => $file->getSize() ?: 0,
                    'mime' => $file->getClientMimeType(),
                ];
            } elseif (is_array($file)) {
                $summary[$field] = array_map(fn (UploadedFile $f) => [
                    'name' => $f->getClientOriginalName(),
                    'size' => $f->getSize() ?: 0,
                    'mime' => $f->getClientMimeType(),
                ], $file);
            }
        }

        return $summary;
    }

    private function assertPayloadSize(Request $request): void
    {
        $maxBytesRaw = config('proxy.max_payload_bytes', 1024 * 1024);
        $maxBytes = is_scalar($maxBytesRaw) ? intval($maxBytesRaw) : 1024 * 1024;
        $lengthRaw = $request->headers->get('content-length', '0');
        $length = is_scalar($lengthRaw) ? intval($lengthRaw) : 0;

        if ($length > $maxBytes) {
            throw new ProxyPayloadTooLargeException;
        }
    }

    private function newRequestId(): string
    {
        return (string) Str::uuid();
    }
}
