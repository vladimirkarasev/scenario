<?php

declare(strict_types=1);

namespace Module\Proxy\Console\Commands;

use Illuminate\Support\Str;

final class MakeProxyCredentialCommand extends ProxyGeneratorCommand
{
    protected $signature = 'make:proxy-credential
        {name : Credential name in StudlyCase, e.g. MyCrm}
        {--gateway= : Gateway this credential belongs to, e.g. MyCrm (required)}';

    protected $description = 'Scaffold a new Proxy credential (connection driver) scoped to a Gateway';

    public function handle(): int
    {
        $gatewayOption = (string) $this->option('gateway');

        if ($gatewayOption === '') {
            $this->components->error('--gateway is required, e.g. --gateway=AutoCrm');

            return self::FAILURE;
        }

        $gateway = Str::studly($gatewayOption);
        $name = $this->studlyName((string) $this->argument('name'), 'Credential');
        $path = base_path("module/Proxy/Credentials/{$gateway}/{$name}Credential.php");

        if (file_exists($path)) {
            $this->components->error('Already exists: '.$this->relative($path));

            return self::FAILURE;
        }

        if (! is_dir(base_path("module/Proxy/Gateway/{$gateway}"))) {
            $this->components->warn("No Gateway found at module/Proxy/Gateway/{$gateway} — run make:proxy-gateway {$gateway} first if it doesn't exist yet.");
        }

        $contents = $this->render($this->stub('credential.stub'), [
            '{{ name }}' => $name,
            '{{ gateway }}' => $gateway,
        ]);

        $this->putFile($path, $contents);
        $this->components->info('Created '.$this->relative($path));

        $this->newLine();
        $this->components->warn('Register it manually in module/Proxy/Services/CredentialCatalog.php:');
        $this->line("  use Module\\Proxy\\Credentials\\{$gateway}\\{$name}Credential;");
        $this->line("  // add {$name}Credential::class to the array returned by CredentialCatalog::all()");

        return self::SUCCESS;
    }
}
