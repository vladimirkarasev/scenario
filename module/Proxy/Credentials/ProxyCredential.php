<?php

declare(strict_types=1);

namespace Module\Proxy\Credentials;

use Module\Proxy\DTO\ProxyField;
use Module\Proxy\Gateway\Base\DTO\ApiGatewayConfig;

abstract class ProxyCredential
{
    abstract public function label(): string;

    abstract public function group(): string;

    /**
     * @return iterable<ProxyField>
     */
    abstract public function fields(): iterable;

    /**
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
