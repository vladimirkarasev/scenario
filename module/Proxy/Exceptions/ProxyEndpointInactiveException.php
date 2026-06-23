<?php

declare(strict_types=1);

namespace Module\Proxy\Exceptions;

use RuntimeException;

final class ProxyEndpointInactiveException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Proxy endpoint is inactive.');
    }
}
