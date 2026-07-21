<?php

declare(strict_types=1);

namespace Module\Actions\Services\Mail;

use Module\Actions\Models\EmailAccount;

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
