<?php

declare(strict_types=1);

namespace Module\Scenario\Services;

/**
 * Служебные ключи уровня плеера в context прогона (не относятся к отдельным нодам).
 * Ключи нод см. в {@see NodeContextKeys}.
 */
final class RunContextKeys
{
    /** context[PLAYER] = ['total_steps' => int, 'visited' => array<string,int>] — guard прогресса. */
    public const string PLAYER = '_player';

    /** context[VARIABLE_MAP] = карта плоских переменных опроса (varName => мета). */
    public const string VARIABLE_MAP = '_variable_map';

    /**
     * context[CALL_STACK] = стек кадров возврата для связных сценариев.
     * Каждый кадр: ['version_id' => string, 'revision_id' => ?string, 'return_node_id' => ?string].
     * При входе в связный сценарий (scenario_link) кадр родителя кладётся в стек;
     * по достижении «Конца» связного сценария кадр снимается и прогон возвращается
     * в родителя на return_node_id. Прогон ведёт себя как один сквозной сценарий.
     */
    public const string CALL_STACK = '_call_stack';
}
