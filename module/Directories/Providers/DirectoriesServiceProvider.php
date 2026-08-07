<?php

declare(strict_types=1);

namespace Module\Directories\Providers;

use App\Support\PermissionRegistry;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Module\Directories\Enums\DirectoryPermission;
use Module\Directories\Enums\DirectoryVersionPermission;
use Module\Directories\Events\DirectoryImportStatusUpdated;
use Module\Directories\Exceptions\DictionaryApiSyncException;
use Module\Directories\Exceptions\DirectoryException;
use Module\Directories\Exceptions\DirectoryExternalException;
use Module\Directories\Exceptions\DirectoryImportException;
use Module\Directories\Exceptions\DirectoryItemException;
use Module\Directories\Exceptions\DirectoryVersionException;
use Module\Directories\Listeners\LogDirectoryImportStatusUpdate;
use Module\Directories\Listeners\PublishDirectoryImportStatusUpdate;
use Module\Directories\Listeners\SyncDirectoryStatusOnImportFinished;
use Module\Directories\Temporal\RebuildDirectorySearchTextWorkflowStarter;
use Module\Directories\Temporal\RebuildDirectorySearchTextWorkflowStarterInterface;
use Module\Directories\Temporal\RunDirectoryImportWorkflowStarter;
use Module\Directories\Temporal\RunDirectoryImportWorkflowStarterInterface;

final class DirectoriesServiceProvider extends ServiceProvider
{
    #[\Override]
    public function register(): void
    {
        $this->app->bind(RunDirectoryImportWorkflowStarterInterface::class, RunDirectoryImportWorkflowStarter::class);
        $this->app->bind(
            RebuildDirectorySearchTextWorkflowStarterInterface::class,
            RebuildDirectorySearchTextWorkflowStarter::class,
        );
    }

    public function boot(): void
    {
        PermissionRegistry::register(DirectoryPermission::class, DirectoryVersionPermission::class);

        Route::middleware('web')
            ->group(dirname(__DIR__).'/routes/web.php');

        $this->loadRoutesFrom(dirname(__DIR__).'/routes/api.php');

        Event::listen(DirectoryImportStatusUpdated::class, [PublishDirectoryImportStatusUpdate::class, 'handle']);
        Event::listen(DirectoryImportStatusUpdated::class, [LogDirectoryImportStatusUpdate::class, 'handle']);
        Event::listen(DirectoryImportStatusUpdated::class, [SyncDirectoryStatusOnImportFinished::class, 'handle']);

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

        $handler->renderable(fn(DirectoryImportException $e) => new JsonResponse(
            ['message' => $e->getMessage()],
            422,
        ));
    }
}
