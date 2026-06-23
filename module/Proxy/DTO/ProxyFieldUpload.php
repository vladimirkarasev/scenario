<?php

declare(strict_types=1);

namespace Module\Proxy\DTO;

final class ProxyFieldUpload extends ProxyField
{
    protected function __construct(string $key)
    {
        parent::__construct($key);
        $this->type('file');
        $this->source('files.'.$key);
        $this->rules(['nullable', 'array']);
    }
}
