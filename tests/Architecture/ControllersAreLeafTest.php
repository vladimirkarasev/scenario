<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPat\Selector\Selector;
use PHPat\Test\Attributes\TestRule;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

/**
 * Controllers only validate and delegate (see CLAUDE.md) — nothing below the
 * HTTP layer should ever reach back up into a Controller.
 */
final class ControllersAreLeafTest
{
    #[TestRule]
    public function modelsRepositoriesServicesAndDtoMustNotDependOnControllers(): Rule
    {
        return PHPat::rule()
            ->classes(
                Selector::inNamespace('/^Module\\\\.*\\\\Models(\\\\|$)/', true),
                Selector::inNamespace('/^Module\\\\.*\\\\Repositories(\\\\|$)/', true),
                Selector::inNamespace('/^Module\\\\.*\\\\Services(\\\\|$)/', true),
                Selector::inNamespace('/^Module\\\\.*\\\\DTO(\\\\|$)/', true),
            )
            ->shouldNot()
            ->dependOn()
            ->classes(Selector::inNamespace('/^Module\\\\.*\\\\Http\\\\Controllers(\\\\|$)/', true))
            ->because('Models/Repositories/Services/DTO must not depend back on Http\\Controllers — Controllers only validate and delegate.');
    }
}
