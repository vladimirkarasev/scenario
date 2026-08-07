<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\CentrifugoMessagePublished;
use App\Listeners\LogCentrifugoMessage;
use App\Listeners\PublishCentrifugoMessage;
use App\Queue\RoadRunnerConnector;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Module\Users\Models\User;
use Psr\Log\LoggerInterface;
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
        $this->registerExpressionRateLimiters();

        $this->overrideRoadRunnerLogger();
    }

    /**
     * roadrunner-php/laravel-bridge rebinds Psr\Log\LoggerInterface to its own RPC logger
     * (writes to the RoadRunner process/stdout, bypassing storage/logs and Buggregator).
     * Restore the normal Laravel logger here so DI-injected LoggerInterface behaves like Log::.
     */
    private function overrideRoadRunnerLogger(): void
    {
        $this->app->singleton(
            LoggerInterface::class,
            static fn (Application $app): LoggerInterface => $app->make('log'),
        );
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

    private function registerExpressionRateLimiters(): void
    {
        RateLimiter::for('expression-render', function (Request $request): array {
            $key = $this->expressionRateLimitKey($request);

            return [
                Limit::perSecond($this->positiveConfigInt('expression.rate_limits.single_per_second', 20))
                    ->by("expression-render:second:{$key}"),
                Limit::perMinute($this->positiveConfigInt('expression.rate_limits.single_per_minute', 600))
                    ->by("expression-render:minute:{$key}"),
            ];
        });

        RateLimiter::for('expression-render-batch', function (Request $request): array {
            $key = $this->expressionRateLimitKey($request);

            return [
                Limit::perSecond($this->positiveConfigInt('expression.rate_limits.batch_per_second', 5))
                    ->by("expression-render-batch:second:{$key}"),
                Limit::perMinute($this->positiveConfigInt('expression.rate_limits.batch_per_minute', 60))
                    ->by("expression-render-batch:minute:{$key}"),
            ];
        });
    }

    private function expressionRateLimitKey(Request $request): string
    {
        $userId = $request->user()?->getAuthIdentifier();
        if (is_string($userId) || is_int($userId)) {
            return "user:{$userId}";
        }

        return 'ip:'.$request->ip();
    }

    private function positiveConfigInt(string $key, int $default): int
    {
        $value = config($key);

        return is_int($value) && $value > 0 ? $value : $default;
    }
}
