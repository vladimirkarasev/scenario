<?php

declare(strict_types=1);

namespace Module\Scenario\Services\Runtime;

use App\Support\PaginationMeta;
use Illuminate\Pagination\LengthAwarePaginator;
use Module\Scenario\DTO\SurveyIndexData;
use Module\Scenario\Enums\ScenarioRunStatus;
use Module\Scenario\Models\ScenarioRun;
use Module\Scenario\Repositories\ScenarioRunRepository;
use Module\Projects\CurrentProject;

final readonly class SurveysService
{
    public function __construct(
        private ScenarioPlayerService $player,
        private ScenarioRunRepository $runs,
        private CurrentProject $currentProject,
    ) {
    }

    /**
     * @return array{
     *     surveys: list<array<string, mixed>>,
     *     pagination: array{current_page: int, last_page: int, per_page: int, total: int, from: int|null, to: int|null}
     * }
     */
    public function list(SurveyIndexData $data): array
    {
        /** @var LengthAwarePaginator<int, ScenarioRun> $runs */
        $runs = ScenarioRun::query()
            ->forProject($this->projectId())
            ->with(['scenario'])
            ->latest()
            ->status($data->status !== null ? ScenarioRunStatus::from($data->status) : null)
            ->forScenario($data->scenarioId)
            ->paginate($data->perPage);

        return [
            'surveys' => array_values($runs->map(fn(ScenarioRun $run): array => $this->summarize($run))->all()),
            'pagination' => PaginationMeta::fromPaginator($runs),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $runId): array
    {
        $run = $this->player->getRun(
            $this->runs->getByIdInProject($runId, $this->projectId()),
        );

        return $this->player->payload($run);
    }

    /** @return array<string, mixed> */
    private function summarize(ScenarioRun $run): array
    {
        return [
            'id' => $run->id,
            'number' => $run->number,
            'number_formatted' => $run->formattedNumber(),
            'scenario_id' => $run->scenario_id,
            'scenario_name' => $run->scenario?->name,
            'current_node_id' => $run->current_node_id,
            'status' => $run->status->value,
            'created_at' => $run->created_at?->toIso8601String(),
            'updated_at' => $run->updated_at?->toIso8601String(),
        ];
    }

    private function projectId(): string
    {
        return $this->currentProject->id()
            ?? throw new \LogicException('Survey operations require a current project.');
    }
}
