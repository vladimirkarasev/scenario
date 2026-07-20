<?php

declare(strict_types=1);

namespace Module\Proxy\Console\Commands;

use Illuminate\Support\Str;

final class MakeProxyGatewayCommand extends ProxyGeneratorCommand
{
    protected $signature = 'make:proxy-gateway
        {name : Service name in StudlyCase, e.g. MyCrm}
        {--force : Overwrite existing files}';

    protected $description = 'Scaffold a new Proxy gateway integration: Gateway, Factory, DTO and example Methods';

    public function handle(): int
    {
        $name = $this->studlyName((string) $this->argument('name'), 'Gateway');
        $basePath = base_path("module/Proxy/Gateway/{$name}");

        $replacements = [
            '{{ name }}' => $name,
            '{{ snake }}' => Str::snake($name),
        ];

        $files = [
            "{$basePath}/{$name}Gateway.php" => $this->render($this->stub('gateway/gateway.stub'), $replacements),
            "{$basePath}/{$name}GatewayFactory.php" => $this->render($this->stub('gateway/factory.stub'), $replacements),
            "{$basePath}/DTO/{$name}Data.php" => $this->render($this->stub('gateway/data.stub'), $replacements),
            "{$basePath}/Methods/{$name}ListMethod.php" => $this->render($this->stub('gateway/list-method.stub'), $replacements),
            "{$basePath}/Methods/{$name}ShowMethod.php" => $this->render($this->stub('gateway/show-method.stub'), $replacements),
            "{$basePath}/Methods/Create{$name}Method.php" => $this->render($this->stub('gateway/create-method.stub'), $replacements),
        ];

        $existing = $this->existingFiles($files);

        if (! $this->option('force') && $existing !== []) {
            $this->components->error('Already exists (use --force to overwrite): '.implode(', ', array_map($this->relative(...), $existing)));

            return self::FAILURE;
        }

        foreach ($files as $path => $contents) {
            $this->putFile($path, $contents);
            $this->components->info('Created '.$this->relative($path));
        }

        $this->newLine();
        $this->components->info("Next steps for {$name}Gateway:");
        $this->line("  - Replace example endpoints in module/Proxy/Gateway/{$name}/Methods/ with the real API");
        $this->line("  - Create a matching credential: php artisan make:proxy-credential {$name} --gateway={$name}");
        $this->line("  - Inject {$name}GatewayFactory into a ProxyHandler (see AutoCrmEndpointHandler for the pattern)");

        return self::SUCCESS;
    }
}
