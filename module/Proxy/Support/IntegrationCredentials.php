<?php

declare(strict_types=1);

namespace Module\Proxy\Support;

use Module\Proxy\DTO\ProxyField;
use Module\Proxy\DTO\ProxyFieldString;

/**
 * Фиксированная схема доступов интеграции (base_uri + токен) и маскирование секретов.
 * Единое место: UI рендерит форму по fields(), контроллер сохраняет/мёржит, ресурс маскирует.
 */
final class IntegrationCredentials
{
    private function __construct() {}

    /** @return array<int, ProxyField> */
    public static function fields(): array
    {
        return [
            ProxyFieldString::make('base_uri')
                ->label('URL сервиса')
                ->example('https://crm.example.com/api/v1'),
            ProxyFieldString::make('bearer_token')
                ->label('Токен доступа')
                ->secret(),
        ];
    }

    /** @return array<string> */
    public static function secretKeys(): array
    {
        $keys = [];
        foreach (self::fields() as $field) {
            if ($field->isSecret()) {
                $keys[] = $field->key();
            }
        }

        return $keys;
    }

    /**
     * Маскирует секреты для отдачи в API: значение секрета → null, плюс карта secret_filled.
     *
     * @param  array<string, mixed>  $credentials
     * @return array{credentials: array<string, mixed>, secret_filled: array<string, bool>}
     */
    public static function mask(array $credentials): array
    {
        $secretKeys = array_flip(self::secretKeys());
        $masked = [];
        $secretFilled = [];

        foreach ($credentials as $key => $value) {
            if (isset($secretKeys[$key])) {
                $masked[$key] = null;
                $secretFilled[$key] = filled($value);

                continue;
            }
            $masked[$key] = $value;
        }

        return ['credentials' => $masked, 'secret_filled' => $secretFilled];
    }
}
