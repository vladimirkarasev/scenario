<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class PruneEmbedAuthTokensCommand extends Command
{
    protected $signature = 'embed-auth:prune-tokens';

    protected $description = 'Delete expired and consumed launch/refresh tokens';

    public function handle(): int
    {
        $launch = DB::table('launch_tokens')
            ->where(function (Builder $q): void {
                $q->where('expires_at', '<', now())
                    ->orWhereNotNull('used_at');
            })
            ->delete();

        $refresh = DB::table('personal_refresh_tokens')
            ->where(function (Builder $q): void {
                $q->where('expires_at', '<', now())
                    ->orWhereNotNull('revoked_at');
            })
            ->delete();

        $this->line("Pruned {$launch} launch token(s) and {$refresh} refresh token(s).");

        return self::SUCCESS;
    }
}
