<?php

declare(strict_types=1);

namespace Module\Groups\DTO;

final readonly class GroupRegistrationData
{
    public function __construct(
        public string $slug,
        public string $name,
        public ?string $extId = null,
    ) {}
}
