<?php

declare(strict_types=1);

namespace Module\Directories\Enums;

enum DirectorySourceType: string
{
    case Manual = 'manual';
    case Excel = 'excel';
    case Api = 'api';
    case External = 'external';

}
