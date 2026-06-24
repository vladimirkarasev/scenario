<?php

declare(strict_types=1);

namespace Module\Proxy\Gateway\AutoCrm\Methods\Dealer;

use Module\Proxy\Gateway\AutoCrm\Methods\AutoCrmListPageMethod;

final readonly class GetDealerListPageMethod extends AutoCrmListPageMethod
{
    #[\Override]
    public function key(): string
    {
        return 'autocrm.dealer.list.page';
    }

    public function uri(): string
    {
        return '/api/dealer';
    }
}
