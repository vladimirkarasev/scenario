<?php

declare(strict_types=1);

namespace Module\Actions\Services\Mail;

use Module\Actions\DTO\EmailMessage;
use Module\Actions\DTO\EmailSendResult;
use Module\Actions\Enums\EmailDriver;

/**
 * Транспорт отправки email. Конкретный отправитель уже настроен фабрикой
 * под нужный аккаунт (SMTP сейчас, proxy-через-внешний-сервис в будущем).
 */
interface EmailSenderInterface
{
    public function driver(): EmailDriver;

    public function send(EmailMessage $message): EmailSendResult;
}
