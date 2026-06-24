<?php

declare(strict_types=1);

namespace Module\Actions\Services;

use Illuminate\Contracts\Container\Container;
use Module\Actions\Contracts\ActionHandlerInterface;
use Module\Actions\Enums\ActionType;
use Module\Actions\Services\Handlers\DirectoryImportActionHandler;
use Module\Actions\Services\Handlers\EmailActionHandler;
use Module\Actions\Services\Handlers\ExcelReportActionHandler;
use Module\Actions\Services\Handlers\ProxyRequestActionHandler;
use Module\Actions\Services\Handlers\ScenarioRunResultActionHandler;
use Module\Actions\Services\Handlers\TemplateFileActionHandler;

final readonly class ActionRegistry
{
    public function __construct(private Container $container) {}

    public function handlerFor(string $type): ActionHandlerInterface
    {
        $class = match (ActionType::from($type)) {
            ActionType::Email => EmailActionHandler::class,
            ActionType::TemplateFile => TemplateFileActionHandler::class,
            ActionType::ProxyRequest => ProxyRequestActionHandler::class,
            ActionType::ExcelReport => ExcelReportActionHandler::class,
            ActionType::DirectoryImport => DirectoryImportActionHandler::class,
            ActionType::ScenarioRunResult => ScenarioRunResultActionHandler::class,
        };

        return $this->container->make($class);
    }
}
