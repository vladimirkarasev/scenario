<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Schedule\Listeners;

use Mockery;
use Module\Schedule\Events\ScheduledArtisanCommandFinished;
use Module\Schedule\Listeners\LogScheduledArtisanCommandFinished;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

final class LogScheduledArtisanCommandFinishedTest extends TestCase
{
    public function test_handle_logs_command_exit_code_and_output(): void
    {
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('info')
            ->once()
            ->with('Scheduled artisan command finished', [
                'command' => 'dictionaries:sync',
                'exit_code' => 0,
                'output' => 'API dictionaries queued: 0',
            ]);

        $listener = new LogScheduledArtisanCommandFinished($logger);
        $listener->handle(new ScheduledArtisanCommandFinished('dictionaries:sync', 0, 'API dictionaries queued: 0'));
    }

    public function test_handle_swallows_logger_failures(): void
    {
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('info')->andThrow(new \RuntimeException('log sink down'));

        $listener = new LogScheduledArtisanCommandFinished($logger);
        $listener->handle(new ScheduledArtisanCommandFinished('dictionaries:sync', 1, 'boom'));

        $this->assertTrue(true);
    }
}
