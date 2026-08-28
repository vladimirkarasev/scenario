<?php

declare(strict_types=1);

namespace Module\Scenario\Enums;

enum ScenarioNodeType: string
{
    case Start = 'start';
    case Block = 'block';
    case Question = 'question';
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
            self::Question => 'Вопрос',
            self::Action => 'Действие',
            self::Condition => 'Условие',
            self::End => 'Конец',
            self::ScenarioLink => 'Связной сценарий',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Start => 'play',
            self::Block => 'layout-panel-top',
            self::Question => 'message-circle-question',
            self::Action => 'zap',
            self::Condition => 'diamond',
            self::End => 'circle-stop',
            self::ScenarioLink => 'external-link',
        };
    }

    public function isContent(): bool
    {
        return $this === self::Block || $this === self::Question;
    }
}
