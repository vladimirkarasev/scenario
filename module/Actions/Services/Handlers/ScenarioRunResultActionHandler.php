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

final readonly class ScenarioRunResultActionHandler implements ActionHandlerInterface
{
    public function __construct(
        private ActionDataResolver $dataResolver,
        private ScenarioPlayerService $player,
    ) {}

    /** @param  array<string, mixed>  $input */
    public function handle(Action $action, array $input = []): ActionResult
    {
        $config = $this->dataResolver->resolveActionConfig($action, $input);
        $own = is_array($input[$action->code] ?? null) ? $input[$action->code] : [];

        $runId = $this->firstUuid([
            $config['scenario_uuid'] ?? null,
            $own['scenario_uuid'] ?? null,
            $input['scenario_run_id'] ?? null,
        ]);

        if ($runId === '') {
            return ActionResult::failed(
                'Scenario run result requires a valid `scenario_uuid` (UUID) in config or input.'
            );
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

    /**
     * Возвращает первое значение, похожее на UUID, иначе ''.
     *
     * @param array<int, mixed> $candidates
     */
    private function firstUuid(array $candidates): string
    {
        foreach ($candidates as $candidate) {
            $value = $this->stringValue($candidate);

            if ($this->isUuid($value)) {
                return $value;
            }
        }

        return '';
    }

    private function isUuid(string $value): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value) === 1;
    }

    private function stringValue(mixed $value): string
    {
        if (is_string($value)) {
            return trim($value);
        }

        return is_scalar($value) ? (string) $value : '';
    }
}
