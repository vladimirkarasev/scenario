<?php

declare(strict_types=1);

namespace Module\Proxy\Services;

use Module\Proxy\DTO\ProxyResponse;
use Module\Proxy\Gateway\Base\Exceptions\ApiGatewayTimeoutException;
use Throwable;

final readonly class ProxyFailureResponseFactory
{
    public function make(Throwable $exception): ProxyResponse
    {
        if ($exception instanceof ApiGatewayTimeoutException) {
            return ProxyResponse::error('Внешний сервис не ответил вовремя. Повторите попытку.', 504);
        }

        return ProxyResponse::error('Proxy processing failed', 500);
    }
}
