<?php

declare(strict_types=1);

namespace Module\Scenario\Enums;

enum ScenarioRunHistoryEventType: string
{
    case Transition = 'transition';
    case FieldFilled = 'field_filled';
    case FieldChanged = 'field_changed';
    case ConditionEvaluated = 'condition_evaluated';
    case ActionCompleted = 'action_completed';
    case ActionFailed = 'action_failed';
    case ScenarioLinkFollowed = 'scenario_link_followed';
    case RunStarted = 'run_started';
    case RunCompleted = 'run_completed';
    case RunFailed = 'run_failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Transition => 'Переход по узлу',
            self::FieldFilled => 'Поле заполнено',
            self::FieldChanged => 'Поле изменено',
            self::ConditionEvaluated => 'Выбор условия',
            self::ActionCompleted => 'Действие выполнено',
            self::ActionFailed => 'Действие завершилось ошибкой',
            self::ScenarioLinkFollowed => 'Переход в связанный сценарий',
            self::RunStarted => 'Старт сценария',
            self::RunCompleted => 'Сценарий завершён',
            self::RunFailed => 'Сценарий завершился ошибкой',
            self::Cancelled => 'Отмена',
        };
    }
}
