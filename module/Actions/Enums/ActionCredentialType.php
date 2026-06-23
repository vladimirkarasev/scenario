<?php

declare(strict_types=1);

namespace Module\Actions\Enums;

enum ActionCredentialType: string
{
    case None = 'none';
    case Bearer = 'bearer';
    case Basic = 'basic';
    case ApiKey = 'api_key';
    case Oauth2Placeholder = 'oauth2_placeholder';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
