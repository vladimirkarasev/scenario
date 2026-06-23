<?php

declare(strict_types=1);

namespace Module\Proxy\Proxies\Belgee\AutoCrm;

use Module\Proxy\DTO\ProxyContext;
use Module\Proxy\DTO\ProxyFieldInteger;
use Module\Proxy\DTO\ProxyFieldString;
use Module\Proxy\DTO\ProxyResponse;
use Module\Proxy\Gateway\AutoCrm\BelgeeAutoCrmGateway;
use Module\Proxy\Proxies\Base\AutoCrm\AutoCrmProxyHandler;
use Module\Proxy\Proxies\Belgee\AutoCrm\Services\BelgeeAutoCrmInterestBuilder;

final class ModelProxyHandler extends AutoCrmProxyHandler
{
    public function __construct(
        private readonly BelgeeAutoCrmGateway $autoCrm,
        private readonly BelgeeAutoCrmInterestBuilder $interestBuilder,
    ) {
    }

    public function fields(): iterable
    {
        yield from $this->leadFields();

        yield ProxyFieldInteger::make('model_id')
            ->label('ID модели')
            ->nullable()
            ->rules(['nullable', 'integer']);

        yield ProxyFieldString::make('model_name')
            ->label('Модель')
            ->source('payload.model')
            ->nullable()
            ->rules(['nullable', 'string', 'max:255']);
    }

    public function handle(ProxyContext $proxyContext): ProxyResponse
    {
        $interest = $this->autoCrm->createInterest(
            $this->interestBuilder->build($proxyContext),
        );

        return ProxyResponse::accepted([
            'message' => 'Belgee model interest sent to AutoCRM',
            'request_id' => $proxyContext->requestId(),
            'autocrm_interest_id' => $interest->id(),
        ]);
    }
}
