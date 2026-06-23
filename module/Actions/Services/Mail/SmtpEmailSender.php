<?php

declare(strict_types=1);

namespace Module\Actions\Services\Mail;

use Illuminate\Support\Facades\Mail;
use Module\Actions\DTO\EmailMessage;
use Module\Actions\DTO\EmailSendResult;
use Module\Actions\Enums\EmailDriver;
use Throwable;

/**
 * Отправка через SMTP. Имя mailer-а задаётся фабрикой: null — дефолтный mailer
 * приложения (config/mail.php), либо runtime-mailer, собранный из настроек аккаунта.
 */
final readonly class SmtpEmailSender implements EmailSenderInterface
{
    public function __construct(private ?string $mailerName = null)
    {
    }

    public function driver(): EmailDriver
    {
        return EmailDriver::Smtp;
    }

    public function send(EmailMessage $message): EmailSendResult
    {
        try {
            $pending = Mail::mailer($this->mailerName)->to($message->to);

            if ($message->cc !== []) {
                $pending->cc($message->cc);
            }

            if ($message->bcc !== []) {
                $pending->bcc($message->bcc);
            }

            $sent = $pending->send(new ActionEmail($message));

            return EmailSendResult::sent('smtp', $sent?->getMessageId());
        } catch (Throwable $exception) {
            return EmailSendResult::failed('smtp', $exception->getMessage());
        }
    }
}
