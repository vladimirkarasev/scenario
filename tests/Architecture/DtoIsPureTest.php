<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPat\Selector\Selector;
use PHPat\Test\Attributes\TestRule;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

/**
 * DTOs are plain, readonly data carriers (see CLAUDE.md) — they must not
 * pull in business logic from Services or Repositories.
 */
final class DtoIsPureTest
{
    #[TestRule]
    public function dtoMustNotDependOnServicesOrRepositories(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('/^Module\\\\.*\\\\DTO(\\\\|$)/', true))
            ->shouldNot()
            ->dependOn()
            ->classes(
                Selector::inNamespace('/^Module\\\\.*\\\\Services(\\\\|$)/', true),
                Selector::inNamespace('/^Module\\\\.*\\\\Repositories(\\\\|$)/', true),
            )
            ->because('DTOs are pure data objects — business logic belongs in Services, not in DTO.');
    }
}
