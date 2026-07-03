<?php

declare(strict_types=1);

namespace Module\Scenario\Enums;

enum ScenarioNodeType: string
{
    case Start = 'start';
    case Block = 'block';
    case Action = 'action';
    case Condition = 'condition';
    case End = 'end';
    case ScenarioLink = 'scenario_link';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Start => 'Старт',
            self::Block => 'Блок',
            self::Action => 'Действие',
            self::Condition => 'Условие',
            self::End => 'Конец',
            self::ScenarioLink => 'Связной сценарий',
        };
    }
}
