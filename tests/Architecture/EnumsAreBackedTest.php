<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPat\Selector\Selector;
use PHPat\Test\Attributes\TestRule;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

/**
 * Enum — backed с label()/color() (see CLAUDE.md) — anything living in an
 * Enums/ namespace must actually be a PHP enum, not a plain class/interface.
 */
final class EnumsAreBackedTest
{
    #[TestRule]
    public function classesInEnumsNamespaceMustBeEnums(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('/^Module\\\\.*\\\\Enums(\\\\|$)/', true))
            ->should()
            ->beEnum()
            ->because('Enum classes must be backed enums per project convention.');
    }
}
