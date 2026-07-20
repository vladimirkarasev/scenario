<?php

declare(strict_types=1);

namespace Module\Proxy\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

abstract class ProxyGeneratorCommand extends Command
{
    protected function studlyName(string $raw, string $stripSuffix): string
    {
        $name = Str::studly($raw);

        return Str::endsWith($name, $stripSuffix) ? Str::substr($name, 0, -Str::length($stripSuffix)) : $name;
    }

    protected function stub(string $relativePath): string
    {
        return (string) file_get_contents(__DIR__.'/../stubs/'.$relativePath);
    }

    /** @param  array<string, string>  $replacements */
    protected function render(string $stub, array $replacements): string
    {
        return strtr($stub, $replacements);
    }

    /**
     * @param  array<string, string>  $files
     * @return list<string>
     */
    protected function existingFiles(array $files): array
    {
        return array_values(array_filter(array_keys($files), file_exists(...)));
    }

    protected function putFile(string $path, string $contents): void
    {
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, recursive: true);
        }

        file_put_contents($path, $contents);
    }

    protected function relative(string $path): string
    {
        return Str::after($path, base_path().DIRECTORY_SEPARATOR);
    }
}
