<?php

declare(strict_types=1);

namespace Module\Proxy\Proxies\Belgee\AutoCrm\DTO;

use Module\Proxy\DTO\ProxyContext;

final readonly class BelgeeAutoCrmSettings
{
    /** @param array<string, mixed> $values */
    public function __construct(private array $values) {}

    public static function fromProxyContext(ProxyContext $proxyContext): self
    {
        $settings = $proxyContext->config('belgee.autocrm');

        if (! is_array($settings)) {
            $settings = $proxyContext->config('autocrm');
        }

        $values = [];
        foreach (is_array($settings) ? $settings : [] as $key => $value) {
            $values[(string) $key] = $value;
        }

        return new self($values);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->values[$key] ?? $default;
    }

    public function id(string $key): int|string|null
    {
        $value = $this->get($key);

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            return ctype_digit($value) ? (int) $value : $value;
        }

        return null;
    }

    public function requiredId(string $key): int|string
    {
        $id = $this->id($key);

        if ($id === null) {
            throw new \RuntimeException("Belgee AutoCRM {$key} is not configured.");
        }

        return $id;
    }
}
