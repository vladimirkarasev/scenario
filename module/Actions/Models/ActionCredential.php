<?php

declare(strict_types=1);

namespace Module\Actions\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;

/**
 * @property int $id
 * @property string $name
 * @property string $type
 * @property array<string, mixed>|null $config
 * @property array<string, string> $encrypted_secrets
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class ActionCredential extends Model
{
    protected $fillable = [
        'name',
        'type',
        'config',
        'encrypted_secrets',
    ];

    protected $hidden = [
        'encrypted_secrets',
    ];

    /** @return array<string, string> */
    public function maskedSecrets(): array
    {
        return collect($this->encrypted_secrets)
            ->map(static fn(mixed $value): string => filled($value) ? '••••••••' : '')
            ->all();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'config' => 'array',
        ];
    }

    /** @return Attribute<array<string, string>, array<string, mixed>|null> */
    protected function encryptedSecrets(): Attribute
    {
        return Attribute::make(
            get: fn(mixed $value): array => $this->decryptSecrets(is_string($value) ? $value : null),
            set: fn(mixed $value): ?string => $this->encryptSecrets(self::stringKeyedArray($value)),
        );
    }

    /** @return array<string, mixed> */
    private static function stringKeyedArray(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $result = [];

        foreach ($value as $key => $item) {
            $result[(string)$key] = $item;
        }

        return $result;
    }

    /** @param  array<string, mixed>  $secrets */
    private function encryptSecrets(array $secrets): ?string
    {
        $payload = collect($secrets)
            ->filter(static fn(mixed $value): bool => $value !== null && $value !== '')
            ->map(
                static fn(mixed $value): string => Crypt::encryptString(
                    is_scalar($value) ? (string)$value : (string)json_encode($value)
                )
            )
            ->all();

        if ($payload === []) {
            return null;
        }

        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $encoded === false ? null : $encoded;
    }

    /** @return array<string, string> */
    private function decryptSecrets(?string $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true, flags: JSON_THROW_ON_ERROR);

        if (!is_array($decoded)) {
            return [];
        }

        $result = [];

        foreach ($decoded as $key => $secret) {
            if (!is_string($secret)) {
                continue;
            }

            $result[(string)$key] = Crypt::decryptString($secret);
        }

        return $result;
    }
}
