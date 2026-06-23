<?php

declare(strict_types=1);

namespace Module\Actions\Services\Mail;

use Module\Actions\Models\EmailAccount;

/**
 * Резолвит конфиг отправки по адресу "От" (from). Так у каждого проекта своя
 * отправка: достаточно завести EmailAccount с нужным from_address.
 */
final readonly class EmailAccountResolver
{
    public function resolveByFrom(?string $from): ?EmailAccount
    {
        if ($from === null || $from === '') {
            return null;
        }

        return EmailAccount::query()
            ->where('from_address', $from)
            ->where('is_active', true)
            ->first();
    }
}
