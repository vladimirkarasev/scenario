<?php

declare(strict_types=1);

namespace Module\Directories\DTO;

interface ImportSourceConfig
{
    /** @return array<string, mixed> */
    public function toArray(): array;
}
