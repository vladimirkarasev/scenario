<?php

declare(strict_types=1);

namespace Module\Actions\Listeners;

use Illuminate\Support\Facades\Log;
use Module\Actions\Events\EmailSendFailed;
use Module\Actions\Events\EmailSent;
use Psr\Log\LoggerInterface;

final readonly class LogEmailActivity
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function handleSent(EmailSent $event): void
    {
        $this->logger->info('Email sent.', $event->logContext());
    }

    public function handleFailed(EmailSendFailed $event): void
    {
        $this->logger->error('Email send failed.', $event->logContext());
    }
}
