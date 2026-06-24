<?php

declare(strict_types=1);

namespace Module\Scenario\Services\Nodes;

/**
 * Служебные ключи, которые ноды хранят в context прогона.
 * Единый источник, чтобы не плодить «магические строки» по хендлерам и джобам.
 * Значения состояний см. в {@see ActionStatus}.
 */
final class NodeContextKeys
{
    /** context[ACTION_RUNS][nodeId] => состояние ноды ({@see ActionStatus}). */
    public const string ACTION_RUNS = '_action_runs';

    /** context[ACTION_STAGES][nodeId][code] => статус стадии ({@see ActionStatus}). */
    public const string ACTION_STAGES = '_action_stages';
}
