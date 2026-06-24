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
}
