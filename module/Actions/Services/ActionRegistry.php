<?php

declare(strict_types=1);

namespace Module\Actions\Services;

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
    /** @var array<string, ActionHandlerInterface> */
    private array $handlers;

    public function __construct(
        EmailActionHandler $email,
        TemplateFileActionHandler $templateFile,
        ProxyRequestActionHandler $proxyRequest,
        ExcelReportActionHandler $excelReport,
        DirectoryImportActionHandler $directoryImport,
        ScenarioRunResultActionHandler $scenarioRunResult,
    ) {
        $this->handlers = [
            ActionType::Email->value => $email,
            ActionType::TemplateFile->value => $templateFile,
            ActionType::ProxyRequest->value => $proxyRequest,
            ActionType::ExcelReport->value => $excelReport,
            ActionType::DirectoryImport->value => $directoryImport,
            ActionType::ScenarioRunResult->value => $scenarioRunResult,
        ];
    }

    public function handlerFor(string $type): ActionHandlerInterface
    {
        return $this->handlers[ActionType::from($type)->value];
    }
}
