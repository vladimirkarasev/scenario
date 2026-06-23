<?php

declare(strict_types=1);

namespace Module\Scenario\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Module\Scenario\DTO\SurveyIndexData;
use Module\Scenario\Enums\ScenarioRunStatus;
use Module\Scenario\Models\ScenarioRun;

final readonly class SurveysService
{
    public function __construct(
        private ScenarioPlayerService $player,
    ) {
    }

    /**
     * Список прогонов опроса с пагинацией.
     *
     * @return array<string, mixed>
     */
    public function list(SurveyIndexData $data): array
    {
        $query = ScenarioRun::query()->with(['scenario'])->latest();

        if ($data->status !== null) {
            $query->where('status', ScenarioRunStatus::from($data->status));
        }

        if ($data->scenarioId !== null) {
            $query->where('scenario_id', $data->scenarioId);
        }

        /** @var LengthAwarePaginator<int, ScenarioRun> $runs */
        $runs = $query->paginate($data->perPage);

        return [
            'surveys' => $runs->map(fn(ScenarioRun $run) => $this->summarize($run))->values()->all(),
            'pagination' => [
                'current_page' => $runs->currentPage(),
                'last_page' => $runs->lastPage(),
                'per_page' => $runs->perPage(),
                'total' => $runs->total(),
            ],
        ];
    }

    /**
     * Полный payload прогона, продвинутый до ближайшего интерактивного узла.
     *
     * @return array<string, mixed>
     */
    public function get(string $runId): array
    {
        $run = $this->player->getRun(ScenarioRun::query()->findOrFail($runId));

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
}
