<?php

declare(strict_types=1);

namespace Module\Proxy\Exceptions;

use RuntimeException;

final class ProxyMethodNotAllowedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Proxy method not allowed.');
    }
}
