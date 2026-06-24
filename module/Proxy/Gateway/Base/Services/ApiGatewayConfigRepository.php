<?php

declare(strict_types=1);

namespace Module\Proxy\Gateway\Base\Services;

use Module\Proxy\Gateway\Base\DTO\ApiGatewayConfig;
use Module\Proxy\Gateway\Base\Exceptions\ApiGatewayConfigException;

final class ApiGatewayConfigRepository
{
    public function get(string $name): ApiGatewayConfig
    {
        $config = config("proxy.gateways.{$name}");

        if (! is_array($config)) {
            throw new ApiGatewayConfigException("Proxy gateway [{$name}] is not configured.");
        }

        $typed = [];
        foreach ($config as $k => $v) {
            $typed[(string) $k] = $v;
        }

        return ApiGatewayConfig::fromArray($name, $typed);
    }
}
