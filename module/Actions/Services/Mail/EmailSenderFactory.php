<?php

declare(strict_types=1);

namespace Module\Actions\Services\Mail;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\Mail;
use Module\Actions\Enums\EmailDriver;
use Module\Actions\Models\EmailAccount;

/**
 * Подбирает настроенный отправитель под аккаунт. Для SMTP собирает runtime-mailer
 * из настроек аккаунта; без аккаунта — дефолтный mailer приложения.
 */
final readonly class EmailSenderFactory
{
    private const RUNTIME_MAILER = 'email_account_runtime';

    public function __construct(private Repository $config) {}

    public function forAccount(?EmailAccount $account): EmailSenderInterface
    {
        if ($account === null) {
            return new SmtpEmailSender;
        }

        return match (EmailDriver::tryFrom($account->driver) ?? EmailDriver::Smtp) {
            EmailDriver::Smtp => new SmtpEmailSender($this->configureSmtpMailer($account)),
            EmailDriver::Proxy => new ProxyEmailSender($account),
        };
    }

    private function configureSmtpMailer(EmailAccount $account): string
    {
        $settings = $account->settings ?? [];

        $this->config->set('mail.mailers.'.self::RUNTIME_MAILER, [
            'transport' => 'smtp',
            'host' => $this->str($settings, 'host', '127.0.0.1'),
            'port' => $this->int($settings, 'port', 587),
            'username' => $this->nullableStr($settings, 'username'),
            'password' => $this->nullableStr($settings, 'password'),
            'scheme' => $this->nullableStr($settings, 'encryption') === 'ssl' ? 'smtps' : null,
        ]);

        // Сбрасываем закешированный mailer, чтобы пересобрался с новыми настройками.
        Mail::purge(self::RUNTIME_MAILER);

        return self::RUNTIME_MAILER;
    }

    /** @param array<string, mixed> $settings */
    private function str(array $settings, string $key, string $default): string
    {
        $value = $settings[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : $default;
    }

    /** @param array<string, mixed> $settings */
    private function nullableStr(array $settings, string $key): ?string
    {
        $value = $settings[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /** @param array<string, mixed> $settings */
    private function int(array $settings, string $key, int $default): int
    {
        $value = $settings[$key] ?? null;

        return is_numeric($value) ? (int) $value : $default;
    }
}
