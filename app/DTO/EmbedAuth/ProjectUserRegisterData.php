<?php

declare(strict_types=1);

namespace App\DTO\EmbedAuth;

use Module\Groups\DTO\GroupRegistrationData;

final readonly class ProjectUserRegisterData
{
    /**
     * @param list<string> $roles
     */
    public function __construct(
        public string $login,
        public ?string $email,
        public string $name,
        public ?string $externalId,
        public array $roles,
        public ?GroupRegistrationData $group = null,
    ) {}
}
