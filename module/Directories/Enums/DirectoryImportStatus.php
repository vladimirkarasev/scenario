<?php

declare(strict_types=1);

namespace Module\Directories\Enums;

enum DirectoryImportStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending    => 'Ожидание',
            self::Processing => 'Обработка',
            self::Completed  => 'Завершён',
            self::Failed     => 'Ошибка',
        };
    }
}
