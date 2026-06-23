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
}
