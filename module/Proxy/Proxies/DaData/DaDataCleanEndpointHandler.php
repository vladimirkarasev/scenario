<?php

declare(strict_types=1);

namespace Module\Proxy\Proxies\DaData;

use Module\Proxy\Credentials\DaData\DaDataCredential;
use Module\Proxy\DTO\ProxyContext;
use Module\Proxy\DTO\ProxyFieldString;
use Module\Proxy\DTO\ProxyResponse;
use Module\Proxy\Gateway\Base\Exceptions\ApiGatewayConfigException;
use Module\Proxy\Gateway\DaData\DaDataCleanGateway;
use Module\Proxy\Gateway\DaData\DaDataCleanGatewayFactory;
use Module\Proxy\Gateway\DaData\DTO\DaDataCleanResult;
use Module\Proxy\ProxyHandler;

abstract class DaDataCleanEndpointHandler extends ProxyHandler
{
    public function __construct(private readonly DaDataCleanGatewayFactory $gateways) {}

    #[\Override]
    public function credentialType(): string
    {
        return DaDataCredential::class;
    }

    public function fields(): iterable
    {
        yield ProxyFieldString::make('value')
            ->label('Значение')
            ->required()
            ->rules(['required', 'string', 'max:1000']);
    }

    abstract protected function clean(DaDataCleanGateway $gateway, string $value): DaDataCleanResult;

    /** @throws \Throwable */
    #[\Override]
    public function handle(ProxyContext $proxyContext): ProxyResponse
    {
        $value = is_string($proxyContext->data('value')) ? $proxyContext->data('value') : '';
        $result = $this->clean($this->gateway($proxyContext), $value);

        return ProxyResponse::ok([
            'request_id' => $proxyContext->requestId(),
            'qc' => $result->qc(),
            'data' => $result->data,
        ]);
    }

    private function gateway(ProxyContext $proxyContext): DaDataCleanGateway
    {
        $connection = $proxyContext->endpoint->connection;

        if ($connection === null) {
            throw new ApiGatewayConfigException('DaData handler requires a connection with API credentials.');
        }

        return $this->gateways->forConnection($connection, $proxyContext->endpoint->is_mocked);
    }
}
