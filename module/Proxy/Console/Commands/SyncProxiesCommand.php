<?php

declare(strict_types=1);

namespace Module\Proxy\Console\Commands;

use Illuminate\Console\Command;
use Module\Proxy\Models\ProxyEndpoint;
use Module\Proxy\Registry\ProxyRegistry;

final class SyncProxiesCommand extends Command
{
    protected $signature = 'proxies:sync';

    protected $description = 'Sync proxy integrations (endpoints) from the registry to the database';

    public function handle(): int
    {
        foreach (ProxyRegistry::all() as $definition) {
            $exists = ProxyEndpoint::query()->where('code', $definition->code)->exists();

            if (! $exists) {
                ProxyEndpoint::query()->create([
                    'uuid' => $definition->uuid,
                    'code' => $definition->code,
                    'type' => $definition->type,
                    'name' => $definition->name,
                    'description' => $definition->description,
                    'handler_class' => $definition->handlerClass,
                    'method' => $definition->method,
                    'base_uri' => $definition->baseUri,
                    'credentials' => $definition->credentials === [] ? null : $definition->credentials,
                    'is_active' => true,
                    'config' => [],
                ]);
                $this->line("  <fg=green>+</> {$definition->code}");

                continue;
            }

            // Обновляем только код-определяемые поля; доступы (base_uri/credentials)
            // не трогаем — админ мог изменить их в UI.
            ProxyEndpoint::query()
                ->where('code', $definition->code)
                ->update([
                    'type' => $definition->type,
                    'name' => $definition->name,
                    'description' => $definition->description,
                    'handler_class' => $definition->handlerClass,
                    'method' => $definition->method,
                ]);
            $this->line("  <fg=yellow>~</> {$definition->code}");
        }

        return self::SUCCESS;
    }
}
