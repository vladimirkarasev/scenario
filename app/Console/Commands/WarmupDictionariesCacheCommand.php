<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Module\Directories\Models\Directory;
use Module\Directories\Services\DirectoryCacheService;

final class WarmupDictionariesCacheCommand extends Command
{
    protected $signature = 'dictionaries:warmup-cache {code? : Optional dictionary code/slug}';

    protected $description = 'Warm active dictionary data cache';

    public function handle(DirectoryCacheService $cacheService): int
    {
        $query = Directory::query()->whereHas('activeVersion');

        if (filled($this->argument('code'))) {
            $query->where('slug', $this->argument('code'));
        }

        $total = 0;

        $query->each(function (Directory $directory) use ($cacheService, &$total): void {
            $total += $cacheService->warmup($directory);
        });

        $this->info("Dictionary rows warmed: {$total}");

        return self::SUCCESS;
    }
}
