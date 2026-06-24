<?php

declare(strict_types=1);

namespace Module\Proxy\Gateway\Base\Transports;

use Module\Proxy\Gateway\Base\Contracts\ApiMethod;
use Module\Proxy\Gateway\Base\Contracts\ApiTransport;
use Module\Proxy\Gateway\Base\DTO\ApiGatewayConfig;
use Module\Proxy\Gateway\Base\DTO\ApiGatewayResponse;
use Module\Proxy\Gateway\Base\Exceptions\ApiGatewayException;

final class MockApiTransport implements ApiTransport
{
    /** @var array<string, array<int, ApiGatewayResponse>> */
    private array $responses = [];

    public function fake(string $gatewayName, string $methodKey, ApiGatewayResponse $response): void
    {
        $this->responses[$this->responseKey($gatewayName, $methodKey)] = [$response];
    }

    /** @param  array<int, ApiGatewayResponse>  $responses */
    public function fakeSequence(string $gatewayName, string $methodKey, array $responses): void
    {
        $this->responses[$this->responseKey($gatewayName, $methodKey)] = array_values($responses);
    }

    public function send(ApiGatewayConfig $config, ApiMethod $method): ApiGatewayResponse
    {
        $key = $this->responseKey($config->name, $method->key());

        if (! array_key_exists($key, $this->responses)) {
            throw new ApiGatewayException("Mock response is not registered for [{$key}].");
        }

        if (count($this->responses[$key]) === 1) {
            return $this->responses[$key][0];
        }

        return array_shift($this->responses[$key]) ?? throw new ApiGatewayException(
            "Mock response sequence exhausted for [{$key}]."
        );
    }

    private function responseKey(string $gatewayName, string $methodKey): string
    {
        return $gatewayName.'::'.$methodKey;
    }
}
