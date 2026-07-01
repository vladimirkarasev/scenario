<?php

declare(strict_types=1);

namespace Module\Proxy\Credentials\AutoCrm;

use Module\Proxy\Credentials\BearerCredential;

/**
 * Доступ к AutoCRM. Сейчас это URL + Bearer-токен (как у базового драйвера), но отдельный
 * класс даёт точку расширения под специфику AutoCRM (доп. поля, обмен токена и т.п.).
 * Только этот тип доступа можно привязать к AutoCRM-хендлерам (см. credentialType()).
 */
final class AutoCrmCredential extends BearerCredential
{
    #[\Override]
    public function label(): string
    {
        return 'AutoCRM';
    }

    #[\Override]
    public function group(): string
    {
        return 'AutoCRM';
    }
}
