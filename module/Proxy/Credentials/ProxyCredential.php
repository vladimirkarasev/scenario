<?php

declare(strict_types=1);

namespace Module\Proxy\Credentials;

use Module\Proxy\DTO\ProxyField;
use Module\Proxy\Gateway\Base\DTO\ApiGatewayConfig;

/**
 * Драйвер доступа (connection): объявляет поля формы и базовую авторизацию для
 * конкретного провайдера. Выбирается хендлером через {@see ProxyHandler::credentialType()},
 * подгружается из {@see \Module\Proxy\Services\CredentialCatalog}. Под специфичные API
 * пишется свой класс с нужными полями и логикой авторизации.
 */
abstract class ProxyCredential
{
    abstract public function label(): string;

    abstract public function group(): string;

    /**
     * Поля доступа (builder API, ->secret() для секретов). UI рисует форму по ним.
     *
     * @return iterable<ProxyField>
     */
    abstract public function fields(): iterable;

    /**
     * Базовая авторизация: из сохранённых значений доступа собирает конфиг транспорта.
     *
     * @param  array<string, mixed>  $values  config + расшифрованные secrets
     */
    abstract public function gatewayConfig(string $name, array $values, bool $mock): ApiGatewayConfig;

    /** @return list<string> ключи секретных полей */
    public function secretKeys(): array
    {
        $keys = [];
        foreach ($this->fields() as $field) {
            if ($field->isSecret()) {
                $keys[] = $field->key();
            }
        }

        return $keys;
    }

    /** @return list<string> ключи всех полей */
    public function fieldKeys(): array
    {
        $keys = [];
        foreach ($this->fields() as $field) {
            $keys[] = $field->key();
        }

        return $keys;
    }

    /** @return list<array<string, mixed>> схема полей для фронта */
    public function fieldSchema(): array
    {
        $schema = [];
        foreach ($this->fields() as $field) {
            $schema[] = $field->toArray();
        }

        return $schema;
    }
}
