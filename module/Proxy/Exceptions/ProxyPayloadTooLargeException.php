<?php

declare(strict_types=1);

namespace Module\Proxy\Exceptions;

use RuntimeException;

final class ProxyPayloadTooLargeException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Proxy payload is too large.');
    }
}
