<?php

declare(strict_types=1);

namespace Module\Scenario\Enums;

enum ScenarioContextKey: string
{
    case ActionRuns = '_action_runs';
    case ActionStages = '_action_stages';
    case Call = '_call';
    case CallStack = '_call_stack';
    case Condition = '_condition';
    case Operator = '_operator';
    case Player = '_player';
    case Project = '_project';
    case Run = '_run';
    case VariableMap = '_variable_map';

    public function label(): string
    {
        return match ($this) {
            self::ActionRuns => 'Запуски действий',
            self::ActionStages => 'Этапы действий',
            self::Call => 'Звонок',
            self::CallStack => 'Стек вызовов',
            self::Condition => 'Условие',
            self::Operator => 'Оператор',
            self::Player => 'Плеер',
            self::Project => 'Проект',
            self::Run => 'Опрос',
            self::VariableMap => 'Карта переменных',
        };
    }

    public static function isSystem(string $name): bool
    {
        return str_starts_with($name, '_');
    }

    public static function isReserved(string $name): bool
    {
        return self::tryFrom($name) !== null;
    }
}
