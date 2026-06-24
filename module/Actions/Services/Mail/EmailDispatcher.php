<?php

declare(strict_types=1);

namespace Module\Actions\Services\Mail;

use Illuminate\Contracts\Events\Dispatcher;
use Module\Actions\DTO\EmailMessage;
use Module\Actions\DTO\EmailSendResult;
use Module\Actions\Events\EmailSendFailed;
use Module\Actions\Events\EmailSent;

/**
 * Точка входа отправки: по from находит аккаунт проекта, подменяет from на
 * адрес/имя аккаунта (авторитетный источник) и отправляет нужным транспортом.
 */
final readonly class EmailDispatcher
{
    public function __construct(
        private EmailAccountResolver $resolver,
        private EmailSenderFactory $factory,
        private Dispatcher $events,
    ) {}

    public function send(EmailMessage $message): EmailSendResult
    {
        $account = $this->resolver->resolveByFrom($message->from);

        if ($account !== null) {
            $message = $message->withFrom($account->from_address, $account->from_name);
        }

        $result = $this->factory->forAccount($account)->send($message);

        if ($result->sent) {
            $this->events->dispatch(
                new EmailSent(
                    from: $message->from,
                    to: $message->to,
                    subject: $message->subject,
                    transport: $result->transport,
                    messageId: $result->messageId,
                )
            );
        } else {
            $this->events->dispatch(
                new EmailSendFailed(
                    from: $message->from,
                    to: $message->to,
                    subject: $message->subject,
                    transport: $result->transport,
                    error: $result->error ?? 'unknown error',
                )
            );
        }

        return $result;
    }
}
