<?php

declare(strict_types=1);

namespace App\Providers;

use Module\Users\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    #[\Override]
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        Gate::before(static fn(User $user): ?bool => $user->hasRole('administrator') ? true : null);
    }
}
