<?php

declare(strict_types=1);

namespace App\Logging;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;

/**
 * Sends each log record over a fresh TCP connection.
 * Avoids stale-socket failures in long-running processes (Octane, php artisan serve).
 */
final class BuggregatorHandler extends AbstractProcessingHandler
{
    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly float $timeout = 1.0,
    ) {
        parent::__construct(Level::Debug);
    }

    protected function write(LogRecord $record): void
    {
        $data = $record->formatted;

        if (!is_string($data)) {
            return;
        }

        $socket = @fsockopen('tcp://'.$this->host, $this->port, $errno, $errstr, $this->timeout);

        if (!is_resource($socket)) {
            return;
        }

        try {
            stream_set_timeout($socket, (int)$this->timeout);
            fwrite($socket, $data);
        } finally {
            fclose($socket);
        }
    }
}
