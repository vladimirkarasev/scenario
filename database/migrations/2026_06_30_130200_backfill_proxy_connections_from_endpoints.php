<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Module\Proxy\Models\ProxyConnection;
use Module\Proxy\Models\ProxyEndpoint;
use Module\Proxy\Services\CredentialCatalog;
use Module\Proxy\Services\HandlerResolver;

/**
 * Переносит legacy-доступы (`base_uri` + `credentials` на эндпоинте) в отдельные connections.
 * Эндпоинты с одинаковыми доступами (проект + тип + значения) делят один connection.
 * Колонки base_uri/credentials пока остаются — их дропнет отдельная миграция позже.
 */
return new class extends Migration
{
    public function up(): void
    {
        $resolver = app(HandlerResolver::class);

        /** @var array<string, ProxyConnection> $byKey */
        $byKey = [];

        ProxyEndpoint::query()
            ->whereNull('connection_id')
            ->each(function (ProxyEndpoint $endpoint) use ($resolver, &$byKey): void {
                try {
                    $type = $resolver->resolveClass($endpoint->handler_class)->credentialType();
                } catch (\Throwable) {
                    return; // неизвестный/невалидный обработчик — пропускаем
                }

                // Обработчик без доступа, либо у эндпоинта вообще нет доступов — нечего переносить.
                if ($type === null || ! CredentialCatalog::has($type)) {
                    return;
                }
                if (! filled($endpoint->base_uri) && ($endpoint->credentials ?? []) === []) {
                    return;
                }

                $driver = CredentialCatalog::make($type);
                $values = array_merge(['base_uri' => $endpoint->base_uri], $endpoint->credentials ?? []);

                $secretKeys = array_flip($driver->secretKeys());
                $config = [];
                $secrets = [];
                foreach ($driver->fieldKeys() as $field) {
                    $value = $values[$field] ?? null;
                    if (isset($secretKeys[$field])) {
                        if (filled($value)) {
                            $secrets[$field] = $value;
                        }

                        continue;
                    }
                    $config[$field] = $value;
                }

                $key = md5(serialize([$endpoint->project_id, $type, $config, $secrets]));

                $connection = $byKey[$key] ?? null;
                if ($connection === null) {
                    $connection = ProxyConnection::query()->create([
                        'project_id' => $endpoint->project_id,
                        'name' => $this->connectionName($driver->label(), $config['base_uri'] ?? null),
                        'credential_type' => $type,
                        'config' => $config === [] ? null : $config,
                        'secrets' => $secrets === [] ? null : $secrets,
                    ]);
                    $byKey[$key] = $connection;
                }

                $endpoint->connection_id = $connection->id;
                $endpoint->saveQuietly();
            });
    }

    public function down(): void
    {
        ProxyEndpoint::query()->whereNotNull('connection_id')->update(['connection_id' => null]);
        ProxyConnection::query()->delete();
    }

    private function connectionName(string $label, mixed $baseUri): string
    {
        $host = is_string($baseUri) ? parse_url($baseUri, PHP_URL_HOST) : null;

        return is_string($host) && $host !== '' ? $label.' ('.$host.')' : $label;
    }
};
