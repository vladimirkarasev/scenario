<?php

declare(strict_types=1);

namespace Module\Actions\Services\Mail;

use Module\Actions\DTO\EmailMessage;
use Module\Actions\DTO\EmailSendResult;
use Module\Actions\Enums\EmailDriver;
use Module\Actions\Models\EmailAccount;

/**
 * Задел под отправку через внешний сервис (proxy). Параметры эндпоинта/токена
 * лежат в EmailAccount::$settings. Реальная интеграция будет добавлена позже —
 * сейчас возвращает явный отказ, чтобы driver=proxy не падал молча.
 */
final readonly class ProxyEmailSender implements EmailSenderInterface
{
    public function __construct(private EmailAccount $account)
    {
    }

    public function driver(): EmailDriver
    {
        return EmailDriver::Proxy;
    }

    public function send(EmailMessage $message): EmailSendResult
    {
        return EmailSendResult::failed(
            'proxy',
            "Proxy email driver для аккаунта «{$this->account->from_address}» ещё не реализован.",
        );
    }
}
