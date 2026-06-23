<?php

declare(strict_types=1);

namespace Module\Scenario\Services\Nodes;

use Module\Scenario\Models\ScenarioRun;

/**
 * Узел, чьё выполнение можно повторить с момента ошибки (action-нода с wait_for_result).
 */
interface RetryableNodeHandler
{
    /**
     * Перезапускает выполнение узла начиная с упавшей стадии.
     * Возвращает true, если повтор был запущен.
     *
     * @param  array<string, mixed>  $node
     */
    public function retry(ScenarioRun $run, array $node): bool;
}
