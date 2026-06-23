<?php

declare(strict_types=1);

namespace Module\Proxy\Enums;

enum ProxyRequestStatus: string
{
    case Received = 'received';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Failed = 'failed';
    case Processed = 'processed';

    public function label(): string
    {
        return match ($this) {
            self::Received => 'Получен',
            self::Accepted => 'Принят',
            self::Rejected => 'Отклонён',
            self::Failed => 'Ошибка',
            self::Processed => 'Обработан',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Received => 'slate',
            self::Accepted => 'blue',
            self::Rejected => 'amber',
            self::Failed => 'red',
            self::Processed => 'emerald',
        };
    }
}
