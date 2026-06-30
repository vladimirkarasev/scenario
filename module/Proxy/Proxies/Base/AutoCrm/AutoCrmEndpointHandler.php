<?php

declare(strict_types=1);

namespace Module\Proxy\Proxies\Base\AutoCrm;

use Module\Proxy\Credentials\AutoCrm\AutoCrmCredential;
use Module\Proxy\DTO\ProxyContext;
use Module\Proxy\Gateway\AutoCrm\AutoCrmGateway;
use Module\Proxy\Gateway\AutoCrm\AutoCrmGatewayFactory;
use Module\Proxy\ProxyHandler;

/**
 * Базовый handler для эндпоинтов AutoCRM: строит gateway из привязанного доступа
 * (connection типа {@see AutoCrmCredential}), который админ выбирает в UI при создании интеграции.
 */
abstract class AutoCrmEndpointHandler extends ProxyHandler
{
    public function __construct(protected readonly AutoCrmGatewayFactory $gateways) {}

    #[\Override]
    public function credentialType(): string
    {
        return AutoCrmCredential::class;
    }

    protected function autoCrm(ProxyContext $proxyContext): AutoCrmGateway
    {
        return $this->gateways->forEndpoint($proxyContext->endpoint);
    }
}
