<?php

declare(strict_types=1);

namespace Module\Proxy\Credentials;

use Module\Proxy\DTO\ProxyFieldString;
use Module\Proxy\Gateway\Base\DTO\ApiGatewayConfig;

/**
 * Универсальный доступ: URL + Basic-авторизация (логин/пароль).
 */
final class BasicCredential extends ProxyCredential
{
    public function label(): string
    {
        return 'Basic (логин/пароль)';
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

        yield ProxyFieldString::make('username')
            ->label('Логин');

        yield ProxyFieldString::make('password')
            ->label('Пароль')
            ->secret();
    }

    public function gatewayConfig(string $name, array $values, bool $mock): ApiGatewayConfig
    {
        $username = $values['username'] ?? null;

        return new ApiGatewayConfig(
            name: $name,
            baseUri: is_string($values['base_uri'] ?? null) ? $values['base_uri'] : '',
            mock: $mock,
            auth: filled($username)
                ? ['type' => 'basic', 'username' => $username, 'password' => $values['password'] ?? '']
                : ['type' => 'none'],
            headers: ['Accept' => 'application/json'],
        );
    }
}
