<?php

declare(strict_types=1);

namespace Module\Scenario\Services\Variables\System;

use Module\Scenario\Enums\ScenarioContextKey;

final readonly class CommonSystemVariableProvider
{
    /** @return iterable<SystemVariableGroup> */
    public function groups(): iterable
    {
        yield new SystemVariableGroup(ScenarioContextKey::Run, [
            new SystemVariableField('id', 'UUID опроса', 'Уникальный идентификатор прогона'),
            new SystemVariableField('number', 'Номер опроса', 'Порядковый номер'),
            new SystemVariableField('number_formatted', 'Номер опроса (с нулями)', 'Номер с ведущими нулями'),
            new SystemVariableField('created_at', 'Дата создания опроса', 'Когда опрос был начат'),
            new SystemVariableField('completed_at', 'Дата окончания опроса', 'Когда опрос был завершён'),
        ]);
        yield new SystemVariableGroup(ScenarioContextKey::Operator, [
            new SystemVariableField('login', 'Логин оператора', 'Логин учётной записи'),
            new SystemVariableField('name', 'Имя оператора', 'Отображаемое имя'),
            new SystemVariableField('fio', 'ФИО оператора', 'Полное имя'),
        ]);
        yield new SystemVariableGroup(ScenarioContextKey::Project, [
            new SystemVariableField('name', 'Название проекта', 'Имя проекта оператора'),
            new SystemVariableField('id', 'ID проекта', 'Идентификатор проекта'),
        ]);
    }
}
