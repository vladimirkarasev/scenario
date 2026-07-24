<?php

declare(strict_types=1);

namespace Module\Proxy\Credentials\DaData;

use Module\Proxy\Credentials\ProxyCredential;
use Module\Proxy\DTO\ProxyFieldString;
use Module\Proxy\Gateway\Base\DTO\ApiGatewayConfig;
use Module\Proxy\Gateway\DaData\DaDataGateway;

final class DaDataCredential extends ProxyCredential
{
    public function label(): string
    {
        return 'DaData';
    }

    public function group(): string
    {
        return 'DaData';
    }

    public function fields(): iterable
    {
        yield ProxyFieldString::make('api_key')
            ->label('API-ключ')
            ->required()
            ->rules(['required', 'string'])
            ->secret();

        yield ProxyFieldString::make('secret_key')
            ->label('Секретный ключ')
            ->description('Нужен для Clean API, профиля и подсказок с секретными данными (ИНН и т.п.)')
            ->nullable()
            ->rules(['nullable', 'string'])
            ->secret();
    }

    public function gatewayConfig(string $name, array $values, bool $mock): ApiGatewayConfig
    {
        $token = is_string($values['api_key'] ?? null) ? $values['api_key'] : '';
        $secret = is_string($values['secret_key'] ?? null) ? $values['secret_key'] : null;

        $headers = ['Authorization' => 'Token '.$token];
        if (filled($secret)) {
            $headers['X-Secret'] = $secret;
        }

        return new ApiGatewayConfig(
            name: $name,
            baseUri: DaDataGateway::tryFrom($name)?->baseUri() ?? '',
            mock: $mock,
            auth: ['type' => 'headers', 'headers' => $headers],
            headers: ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
        );
    }
}
