<?php

declare(strict_types=1);

namespace Module\Proxy\Credentials\AutoCrm;

use Module\Proxy\Credentials\BearerCredential;

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
