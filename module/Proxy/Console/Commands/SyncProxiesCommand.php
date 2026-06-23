<?php

declare(strict_types=1);

namespace Module\Proxy\Console\Commands;

use Illuminate\Console\Command;
use Module\Proxy\Models\ProxyEndpoint;
use Module\Proxy\Registry\ProxyRegistry;

final class SyncProxiesCommand extends Command
{
    protected $signature = 'proxies:sync';

    protected $description = 'Sync proxy endpoints from ProxyRegistry to the database';

    public function handle(): int
    {
        $inserted = 0;
        $updated = 0;

        foreach (ProxyRegistry::all() as $definition) {
            $exists = ProxyEndpoint::query()
                ->where('code', $definition->code)
                ->exists();

            if (!$exists) {
                ProxyEndpoint::query()->create([
                    'uuid' => $definition->uuid,
                    'code' => $definition->code,
                    'name' => $definition->name,
                    'description' => $definition->description,
                    'handler_class' => $definition->handlerClass,
                    'method' => $definition->method,
                    'is_active' => true,
                    'config' => [],
                ]);
                $this->line("  <fg=green>+</> {$definition->code}");
                $inserted++;
            } else {
                ProxyEndpoint::query()
                    ->where('code', $definition->code)
                    ->update([
                        'name' => $definition->name,
                        'description' => $definition->description,
                        'handler_class' => $definition->handlerClass,
                        'method' => $definition->method,
                    ]);
                $this->line("  <fg=yellow>~</> {$definition->code}");
                $updated++;
            }
        }

        $this->info("Done. Inserted: {$inserted}, updated: {$updated}.");

        return self::SUCCESS;
    }
}
