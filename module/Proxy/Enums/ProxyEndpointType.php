<?php

declare(strict_types=1);

namespace Module\Proxy\Enums;

enum ProxyEndpointType: string
{
    case Webhook = 'webhook';
    case Suggest = 'suggest';

    public function label(): string
    {
        return match ($this) {
            self::Webhook => 'Вебхук',
            self::Suggest => 'Подсказки',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Webhook => 'slate',
            self::Suggest => 'violet',
        };
    }
}
