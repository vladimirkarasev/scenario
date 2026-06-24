<?php

declare(strict_types=1);

namespace Module\Proxy\Gateway\Base\Transports;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Arr;
use JsonException;
use Module\Proxy\Gateway\Base\Contracts\ApiMethod;
use Module\Proxy\Gateway\Base\Contracts\ApiTransport;
use Module\Proxy\Gateway\Base\DTO\ApiGatewayConfig;
use Module\Proxy\Gateway\Base\DTO\ApiGatewayResponse;
use Module\Proxy\Gateway\Base\Exceptions\ApiGatewayException;

final readonly class GuzzleApiTransport implements ApiTransport
{
    public function __construct(private ClientInterface $client) {}

    public function send(ApiGatewayConfig $config, ApiMethod $method): ApiGatewayResponse
    {
        try {
            $response = $this->client->request($method->method(), $method->uri(), $this->options($config, $method));
        } catch (GuzzleException $exception) {
            throw new ApiGatewayException($exception->getMessage(), (int) $exception->getCode(), $exception);
        }

        $contents = (string) $response->getBody();

        return new ApiGatewayResponse(
            statusCode: $response->getStatusCode(),
            body: $this->decodeBody($contents),
            headers: $this->normalizeHeaders($response->getHeaders()),
        );
    }

    /** @return array<string, mixed> */
    private function options(ApiGatewayConfig $config, ApiMethod $method): array
    {
        $options = [
            'base_uri' => $config->baseUri,
            'timeout' => $config->timeout,
            'connect_timeout' => $config->connectTimeout,
            'http_errors' => false,
            'headers' => [
                ...$config->headers,
                ...$this->authHeaders($config),
                ...$method->headers(),
            ],
            'query' => $method->query(),
        ];

        if ($method->body() !== null) {
            $options['json'] = $method->body();
        }

        $basicAuth = $this->basicAuth($config);

        if ($basicAuth !== null) {
            $options['auth'] = $basicAuth;
        }

        $merged = [];
        foreach (array_replace_recursive($options, $method->options()) as $k => $v) {
            $merged[(string) $k] = $v;
        }

        return $merged;
    }

    /** @return array<int, string>|null */
    private function basicAuth(ApiGatewayConfig $config): ?array
    {
        if (($config->auth['type'] ?? 'none') !== 'basic') {
            return null;
        }

        $username = $config->auth['username'] ?? '';
        $password = $config->auth['password'] ?? '';

        return [
            is_scalar($username) ? (string) $username : '',
            is_scalar($password) ? (string) $password : '',
        ];
    }

    /** @return array<string, mixed> */
    private function authHeaders(ApiGatewayConfig $config): array
    {
        $rawType = $config->auth['type'] ?? 'none';
        $type = is_scalar($rawType) ? (string) $rawType : 'none';

        if ($type === 'bearer' && filled($config->auth['token'] ?? null)) {
            $token = $config->auth['token'];

            return ['Authorization' => 'Bearer '.(is_scalar($token) ? (string) $token : '')];
        }

        if ($type === 'headers') {
            $headers = Arr::get($config->auth, 'headers', []);
            if (! is_array($headers)) {
                return [];
            }
            $result = [];
            foreach ($headers as $k => $v) {
                $result[(string) $k] = $v;
            }

            return $result;
        }

        return [];
    }

    private function decodeBody(string $body): mixed
    {
        if ($body === '') {
            return null;
        }

        try {
            return json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $body;
        }
    }

    /**
     * @param  array<int|string, array<string|null>> $headers
     * @return array<string, mixed>
     */
    private function normalizeHeaders(array $headers): array
    {
        $normalized = [];

        foreach ($headers as $key => $values) {
            $normalized[(string) $key] = count($values) === 1 ? $values[0] : $values;
        }

        return $normalized;
    }
}
