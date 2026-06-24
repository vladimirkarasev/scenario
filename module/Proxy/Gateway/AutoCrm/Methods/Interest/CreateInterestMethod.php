<?php

declare(strict_types=1);

namespace Module\Proxy\Gateway\AutoCrm\Methods\Interest;

use Module\Proxy\Gateway\AutoCrm\DTO\InterestForm;
use Module\Proxy\Gateway\Base\Methods\AbstractApiMethod;

final readonly class CreateInterestMethod extends AbstractApiMethod
{
    public function __construct(private InterestForm $form) {}

    #[\Override]
    public function key(): string
    {
        return 'autocrm.interest.create';
    }

    public function method(): string
    {
        return 'POST';
    }

    public function uri(): string
    {
        return '/api/lms/interest';
    }

    /** @return array<string, mixed> */
    public function body(): array
    {
        return $this->form->toArray();
    }
}
