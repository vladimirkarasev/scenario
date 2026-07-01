<?php

declare(strict_types=1);

namespace Module\Users\Enums;

enum SystemRole: string
{
    case Administrator = 'administrator';
    case ProjectService = 'project-service';
}
