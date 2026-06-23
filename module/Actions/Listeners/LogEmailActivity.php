<?php

declare(strict_types=1);

namespace Module\Actions\Listeners;

use Illuminate\Support\Facades\Log;
use Module\Actions\Events\EmailSendFailed;
use Module\Actions\Events\EmailSent;

final readonly class LogEmailActivity
{
    public function handleSent(EmailSent $event): void
    {
        Log::info('Email sent.', $event->logContext());
    }

    public function handleFailed(EmailSendFailed $event): void
    {
        Log::error('Email send failed.', $event->logContext());
    }
}
