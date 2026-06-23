<?php

declare(strict_types=1);

namespace Module\Proxy\Gateway\Base\Contracts;

use Module\Proxy\Gateway\Base\DTO\ApiGatewayConfig;
use Module\Proxy\Gateway\Base\DTO\ApiGatewayResponse;

interface ApiTransport
{
    public function send(ApiGatewayConfig $config, ApiMethod $method): ApiGatewayResponse;
}
