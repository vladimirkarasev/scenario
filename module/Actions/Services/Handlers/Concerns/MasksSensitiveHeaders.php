<?php

declare(strict_types=1);

namespace Module\Actions\Services\Handlers\Concerns;

trait MasksSensitiveHeaders
{
    private const SENSITIVE_KEYS = [
        'authorization', 'proxy-authorization', 'cookie', 'set-cookie',
        'x-api-key', 'api-key', 'api_key', 'apikey', 'token', 'secret', 'password',
        'access-token', 'access_token', 'refresh-token', 'refresh_token',
    ];

    /**
     * @param  array<mixed, mixed>  $items
     * @return array<string, mixed>
     */
    private function maskSensitive(array $items): array
    {
        $result = [];

        foreach ($items as $key => $value) {
            $result[(string) $key] = in_array(strtolower((string) $key), self::SENSITIVE_KEYS, true)
                ? '••••••'
                : $value;
        }

        return $result;
    }
}
