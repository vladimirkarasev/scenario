<?php

declare(strict_types=1);

namespace Module\Actions\Services\Mail;

use Module\Actions\DTO\EmailMessage;
use Module\Actions\DTO\EmailSendResult;
use Module\Actions\Enums\EmailDriver;

interface EmailSenderInterface
{
    public function driver(): EmailDriver;

    public function send(EmailMessage $message): EmailSendResult;
}
