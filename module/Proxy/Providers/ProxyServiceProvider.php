<?php

declare(strict_types=1);

namespace Module\Proxy\Providers;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Module\Proxy\Console\Commands\SyncProxiesCommand;
use Module\Proxy\Events\ProxyRequestAccepted;
use Module\Proxy\Events\ProxyRequestFailed;
use Module\Proxy\Events\ProxyRequestProcessed;
use Module\Proxy\Events\ProxyRequestRejected;
use Module\Proxy\Exceptions\ProxyEndpointInactiveException;
use Module\Proxy\Exceptions\ProxyMethodNotAllowedException;
use Module\Proxy\Exceptions\ProxyPayloadTooLargeException;
use Module\Proxy\Gateway\Base\Transports\MockApiTransport;
use Module\Proxy\Listeners\LogProxyRequestStatus;
use Module\Proxy\Listeners\PersistProxyContext;

final class ProxyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ClientInterface::class, fn (): ClientInterface => new Client);
        $this->app->singleton(MockApiTransport::class);
        $this->commands([SyncProxiesCommand::class]);
    }

    public function boot(): void
    {
        $this->registerExceptionHandlers();

        Route::middleware('web')
            ->group(dirname(__DIR__).'/routes/web.php');

        Route::middleware(['api', 'auth:sanctum'])
            ->prefix('api')
            ->group(dirname(__DIR__).'/routes/api.php');

        Event::listen(ProxyRequestAccepted::class, [LogProxyRequestStatus::class, 'handleAccepted']);
        Event::listen(ProxyRequestProcessed::class, [LogProxyRequestStatus::class, 'handleProcessed']);
        Event::listen(ProxyRequestRejected::class, [LogProxyRequestStatus::class, 'handleRejected']);
        Event::listen(ProxyRequestFailed::class, [LogProxyRequestStatus::class, 'handleFailed']);

        Event::listen(ProxyRequestAccepted::class, [PersistProxyContext::class, 'handleAccepted']);
        Event::listen(ProxyRequestProcessed::class, [PersistProxyContext::class, 'handleProcessed']);
        Event::listen(ProxyRequestRejected::class, [PersistProxyContext::class, 'handleRejected']);
        Event::listen(ProxyRequestFailed::class, [PersistProxyContext::class, 'handleFailed']);
    }

    private function registerExceptionHandlers(): void
    {
        /** @var \Illuminate\Foundation\Exceptions\Handler $handler */
        $handler = $this->app->make(ExceptionHandler::class);

        $handler->renderable(fn (ProxyEndpointInactiveException $e) => new JsonResponse(status: 404));
        $handler->renderable(fn (ProxyMethodNotAllowedException $e) => new JsonResponse(['message' => 'Method Not Allowed'], 405));
        $handler->renderable(fn (ProxyPayloadTooLargeException $e) => new JsonResponse(['message' => $e->getMessage()], 413));
    }
}
