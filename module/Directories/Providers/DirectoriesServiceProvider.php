<?php

declare(strict_types=1);

namespace Module\Directories\Providers;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Module\Directories\Events\DirectoryImportStatusUpdated;
use Module\Directories\Exceptions\DictionaryApiSyncException;
use Module\Directories\Exceptions\DirectoryException;
use Module\Directories\Exceptions\DirectoryExternalException;
use Module\Directories\Exceptions\DirectoryItemException;
use Module\Directories\Exceptions\DirectoryVersionException;
use Module\Directories\Listeners\LogDirectoryImportStatusUpdate;
use Module\Directories\Listeners\PublishDirectoryImportStatusUpdate;

final class DirectoriesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Route::middleware('web')
            ->group(dirname(__DIR__).'/routes/web.php');

        $this->loadRoutesFrom(dirname(__DIR__).'/routes/api.php');

        Event::listen(DirectoryImportStatusUpdated::class, [PublishDirectoryImportStatusUpdate::class, 'handle']);
        Event::listen(DirectoryImportStatusUpdated::class, [LogDirectoryImportStatusUpdate::class, 'handle']);

        $this->registerExceptionHandlers();
    }

    private function registerExceptionHandlers(): void
    {
        $handler = $this->app->make(ExceptionHandler::class);

        $handler->renderable(fn(DirectoryException $e) => new JsonResponse(
            $e->getMessage() ? ['message' => $e->getMessage()] : null,
            $e->statusCode(),
        ));

        $handler->renderable(fn(DirectoryVersionException $e) => new JsonResponse(
            $e->getMessage() ? ['message' => $e->getMessage()] : null,
            $e->statusCode(),
        ));

        $handler->renderable(fn(DirectoryItemException $e) => new JsonResponse(
            $e->getMessage() ? ['message' => $e->getMessage()] : null,
            $e->statusCode(),
        ));

        $handler->renderable(fn(DictionaryApiSyncException $e) => new JsonResponse(
            $e->getMessage() ? ['message' => $e->getMessage()] : null,
            $e->statusCode(),
        ));

        $handler->renderable(fn(DirectoryExternalException $e) => new JsonResponse(
            $e->getMessage() ? ['message' => $e->getMessage()] : null,
            $e->statusCode(),
        ));
    }
}
