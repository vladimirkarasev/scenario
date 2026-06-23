<?php

declare(strict_types=1);

namespace Module\Proxy\Gateway\Base\Contracts;

use Module\Proxy\Gateway\Base\DTO\ApiGatewayResponse;

interface ApiGateway
{
    public function send(ApiMethod $method): ApiGatewayResponse;
}
