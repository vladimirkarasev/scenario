<?php

declare(strict_types=1);

namespace Module\Proxy\Services;

use Module\Proxy\DTO\ProxyField;
use Module\Proxy\Models\ProxyEndpoint;

final readonly class ProxyResponseFieldCatalog
{
    public function __construct(private HandlerResolver $handlers)
    {
    }

    /** @return array<string, ProxyField> */
    public function forEndpoint(ProxyEndpoint $endpoint): array
    {
        $fields = [];

        foreach ($this->handlers->resolve($endpoint)->responseFields() as $field) {
            if ($field instanceof ProxyField) {
                $fields[$field->key()] = $field;
            }
        }

        return $fields;
    }

    /** @return list<string> */
    public function keys(ProxyEndpoint $endpoint): array
    {
        return array_keys($this->forEndpoint($endpoint));
    }
}
