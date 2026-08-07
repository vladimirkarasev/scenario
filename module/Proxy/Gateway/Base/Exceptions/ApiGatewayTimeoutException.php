<?php

declare(strict_types=1);

namespace Module\Proxy\Gateway\Base\Exceptions;

use RuntimeException;
use Throwable;

final class ApiGatewayTimeoutException extends RuntimeException
{
    public function __construct(Throwable $previous)
    {
        parent::__construct('Upstream gateway timed out.', 0, $previous);
    }
}
