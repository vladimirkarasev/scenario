<?php

declare(strict_types=1);

namespace Module\Proxy\Credentials;

use Module\Proxy\DTO\ProxyFieldString;
use Module\Proxy\Gateway\Base\DTO\ApiGatewayConfig;

/**
 * Универсальный доступ: URL + Bearer-токен. Подходит большинству REST-API.
 */
class BearerCredential extends ProxyCredential
{
    public function label(): string
    {
        return 'Bearer-токен';
    }

    public function group(): string
    {
        return 'Общие';
    }

    public function fields(): iterable
    {
        yield ProxyFieldString::make('base_uri')
            ->label('URL сервиса')
            ->example('https://api.example.com');

        yield ProxyFieldString::make('bearer_token')
            ->label('Bearer-токен')
            ->secret();
    }

    public function gatewayConfig(string $name, array $values, bool $mock): ApiGatewayConfig
    {
        $token = $values['bearer_token'] ?? null;

        return new ApiGatewayConfig(
            name: $name,
            baseUri: is_string($values['base_uri'] ?? null) ? $values['base_uri'] : '',
            mock: $mock,
            auth: filled($token) ? ['type' => 'bearer', 'token' => $token] : ['type' => 'none'],
            headers: ['Accept' => 'application/json'],
        );
    }
}
