<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\CentrifugoMessagePublished;
use App\Listeners\LogCentrifugoMessage;
use App\Listeners\PublishCentrifugoMessage;
use App\Queue\RoadRunnerConnector;
use Illuminate\Contracts\Foundation\Application;
use Module\Users\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use RoadRunner\Centrifugo\CentrifugoApiInterface;
use RoadRunner\Centrifugo\RPCCentrifugoApi;
use Spiral\Goridge\RPC\RPC;
use Spiral\Goridge\RPC\RPCInterface;
use Spiral\RoadRunner\Environment;

final class AppServiceProvider extends ServiceProvider
{
    #[\Override]
    public function register(): void
    {
        $this->app->singleton(RPCInterface::class, static function (): RPCInterface {
            $address = Environment::fromGlobals()->getRPCAddress();

            if ($address === '') {
                throw new \RuntimeException('RoadRunner RPC address (RR_RPC env) must not be empty.');
            }

            return RPC::create($address);
        });

        $this->app->singleton(
            CentrifugoApiInterface::class,
            static fn (Application $app): CentrifugoApiInterface => new RPCCentrifugoApi($app->make(RPCInterface::class)),
        );

        $this->registerCentrifugoListeners();
    }

    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        Gate::before(static fn(User $user): ?bool => $user->hasRole('administrator') ? true : null);

        $this->registerRoadRunnerQueueConnector();
    }

    private function registerRoadRunnerQueueConnector(): void
    {
        Queue::extend('roadrunner', static fn() => new RoadRunnerConnector());
    }

    private function registerCentrifugoListeners(): void
    {
        Event::listen(CentrifugoMessagePublished::class, [PublishCentrifugoMessage::class, 'handle']);
        Event::listen(CentrifugoMessagePublished::class, [LogCentrifugoMessage::class, 'handle']);
    }
}
