<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPat\Selector\Selector;
use PHPat\Test\Attributes\TestRule;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;
use Module\Scenario\Services\Nodes\NodeHandlerInterface;

/**
 * Adding new node behavior means adding a handler class (see CLAUDE.md) —
 * every *NodeHandler in module/Scenario/Services/Nodes must implement the
 * shared contract so ScenarioPlayerService can drive it generically.
 */
final class ScenarioNodeHandlersTest
{
    #[TestRule]
    public function nodeHandlersMustImplementNodeHandlerInterface(): Rule
    {
        return PHPat::rule()
            ->classes(
                Selector::AllOf(
                    Selector::inNamespace('Module\Scenario\Services\Nodes'),
                    Selector::classname('/NodeHandler$/', true),
                ),
            )
            ->excluding(Selector::isInterface())
            ->should()
            ->implement()
            ->classes(Selector::classname(NodeHandlerInterface::class))
            ->because('PlayerService dispatches to handlers only through NodeHandlerInterface — do not add conditionals to PlayerService instead.');
    }
}
