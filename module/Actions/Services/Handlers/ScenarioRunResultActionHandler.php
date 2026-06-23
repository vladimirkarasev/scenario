<?php

declare(strict_types=1);

namespace Module\Actions\Services\Handlers;

use Module\Actions\Contracts\ActionHandlerInterface;
use Module\Actions\DTO\ActionConfigField;
use Module\Actions\DTO\ActionResult;
use Module\Actions\Models\Action;
use Module\Actions\Services\ActionDataResolver;
use Module\Scenario\Models\ScenarioRun;
use Module\Scenario\Services\ScenarioPlayerService;

final class ScenarioRunResultActionHandler implements ActionHandlerInterface
{
    public function __construct(
        private readonly ActionDataResolver $dataResolver,
        private readonly ScenarioPlayerService $player,
    ) {}

    /** @param array<string, mixed> $input */
    public function handle(Action $action, array $input = []): ActionResult
    {
        $config = $this->dataResolver->resolveActionConfig($action, $input);
        $own = is_array($input[$action->code] ?? null) ? $input[$action->code] : [];

        $runId = $this->stringValue($config['scenario_uuid'] ?? null);

        if ($runId === '') {
            $runId = $this->stringValue($own['scenario_uuid'] ?? null);
        }

        if ($runId === '') {
            return ActionResult::failed('Scenario run result requires `scenario_uuid` in config or input.');
        }

        $run = ScenarioRun::query()->find($runId);

        if ($run === null) {
            return ActionResult::failed("Scenario run {$runId} not found.");
        }

        return ActionResult::success($this->player->surveyData($run));
    }

    public function configFields(): iterable
    {
        yield ActionConfigField::make('scenario_uuid')
            ->label('UUID прогона сценария')
            ->type('string')
            ->placeholder('{{ scenario_uuid }}')
            ->description('Если не указано, берётся из input.scenario_uuid.');
    }

    private function stringValue(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        return is_scalar($value) ? (string) $value : '';
    }
}
