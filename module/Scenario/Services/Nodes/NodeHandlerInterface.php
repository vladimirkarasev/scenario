<?php

declare(strict_types=1);

namespace Module\Scenario\Services\Nodes;

use Module\Scenario\DTO\ScenarioRunContinueData;
use Module\Scenario\Models\ScenarioRun;
use Module\Scenario\Models\ScenarioVersion;

interface NodeHandlerInterface
{
    /** @param  array<string, mixed>  $node */
    public function isInteractive(array $node): bool;

    /**
     * Вызывается циклом прогресса для неинтерактивных узлов.
     * Может мутировать $run (например, ScenarioLink меняет сценарий/версию).
     *
     * @param  array<string, mixed>  $node
     */
    public function advance(ScenarioRun $run, array $node): NodeAdvanceResult;

    /**
     * Вызывается, когда пользователь явно продолжает работу с этого узла.
     * Возвращает ID следующего узла или null для завершения запуска.
     *
     * @param  array<string, mixed>  $node
     */
    public function continueFrom(ScenarioRun $run, array $node, ScenarioRunContinueData $data): ?string;

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function render(ScenarioVersion $version, array $node, array $context): array;
}
