<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPat\Selector\Selector;
use PHPat\Test\Attributes\TestRule;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

/**
 * Services are pure and take DTOs, not the raw HTTP request (see CLAUDE.md).
 * Exception: the Proxy webhook gateway and its Action handler counterpart
 * genuinely need the raw incoming Request (headers/body) to normalize an
 * arbitrary inbound webhook — that's the module's whole purpose.
 */
final class ServicesDontUseRequestTest
{
    #[TestRule]
    public function servicesMustNotDependOnHttpRequest(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('/^Module\\\\.*\\\\Services(\\\\|$)/', true))
            ->excluding(
                Selector::inNamespace('Module\Proxy\Services'),
                Selector::inNamespace('Module\Actions\Services\Handlers'),
            )
            ->shouldNot()
            ->dependOn()
            ->classes(Selector::classname('Illuminate\Http\Request'))
            ->because('Services take DTOs, not the raw Request — build the DTO in the Controller (ProjectData::fromRequest() style).');
    }
}
