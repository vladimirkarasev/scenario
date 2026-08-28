<?php

declare(strict_types=1);

namespace Module\Scenario\Enums;

enum ScenarioType: string
{
    case Colls = 'colls';
    case Telegram = 'telegram';
    case Watsapp = 'watsapp';
    case CallBots = 'call_bots';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Colls => 'Звонки',
            self::Telegram => 'Телеграм',
            self::Watsapp => 'Ватсап',
            self::CallBots => 'Боты звонков',
        };
    }

    /** @return iterable<ScenarioNodeType> */
    public function allowedNodeTypes(): iterable
    {
        yield ScenarioNodeType::Start;
        yield $this === self::Telegram ? ScenarioNodeType::Question : ScenarioNodeType::Block;
        yield ScenarioNodeType::Action;
        yield ScenarioNodeType::Condition;
        yield ScenarioNodeType::End;
        yield ScenarioNodeType::ScenarioLink;
    }

    /** @return list<string> */
    public function allowedNodeTypeValues(): array
    {
        return iterator_to_array($this->allowedNodeTypeValueIterator(), false);
    }

    /** @return iterable<string> */
    private function allowedNodeTypeValueIterator(): iterable
    {
        foreach ($this->allowedNodeTypes() as $type) {
            yield $type->value;
        }
    }
}
