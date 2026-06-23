<?php

declare(strict_types=1);

namespace Module\Actions\Enums;

enum ActionType: string
{
    case Email = 'email';
    case TemplateFile = 'template_file';
    case ProxyRequest = 'proxy_request';
    case ExcelReport = 'excel_report';
    case DirectoryImport = 'directory_import';
    case ScenarioRunResult = 'scenario_run_result';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Email => 'Отправка email',
            self::TemplateFile => 'Файл по шаблону',
            self::ProxyRequest => 'Proxy-запрос',
            self::ExcelReport => 'Excel-отчёт',
            self::DirectoryImport => 'Импорт справочника',
            self::ScenarioRunResult => 'Результаты опроса',
        };
    }

    public function defaultCode(): string
    {
        return match ($this) {
            self::Email => 'email',
            self::TemplateFile => 'template_file',
            self::ProxyRequest => 'proxy_request',
            self::ExcelReport => 'excel_report',
            self::DirectoryImport => 'directory_import',
            self::ScenarioRunResult => 'scenario',
        };
    }
}
