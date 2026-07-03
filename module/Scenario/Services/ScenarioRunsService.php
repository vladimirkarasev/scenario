<?php

declare(strict_types=1);

namespace Module\Scenario\Services;

use App\Support\PaginationMeta;
use Module\Users\Models\User;
use denis660\Centrifugo\Centrifugo;
use Module\Scenario\DTO\ScenarioRunContinueData;
use Module\Scenario\DTO\ScenarioRunData;
use Module\Scenario\DTO\ScenarioRunIndexData;
use Module\Scenario\DTO\ScenarioRunJumpData;
use Module\Scenario\Enums\ScenarioRunStatus;
use Module\Scenario\Models\ScenarioRun;
use Module\Scenario\QueryBuilders\ScenarioRunBuilder;
use Module\Scenario\Repositories\ScenarioRunRepository;
use Module\Scenario\Repositories\ScenarioRunUserRepository;
use Module\Projects\CurrentProject;
use Module\Scenario\DTO\ScenarioStartData;

final readonly class ScenarioRunsService
{
    public function __construct(
        private ScenarioPlayerService $player,
        private ScenarioRunHistoryService $history,
        private ScenarioRunRepository $runs,
        private ScenarioRunUserRepository $users,
        private Centrifugo $centrifugo,
        private CurrentProject $currentProject,
    ) {
    }

    /**
     * Список прогонов с пагинацией и статистикой по статусам.
     *
     * @return array{
     *     runs: list<array<string, mixed>>,
     *     pagination: array{current_page: int, last_page: int, per_page: int, total: int, from: int|null, to: int|null},
     *     stats: array{total: int, active: int, completed: int, failed: int}
     * }
     */
    public function list(ScenarioRunIndexData $data): array
    {
        $runs = $this->filtered($data)
            ->status($data->status)
            ->finished($data->status === null && $data->finished)
            ->withListRelations()
            ->latest()
            ->paginate($data->perPage, ['*'], 'page', $data->page);

        return [
            'runs' => array_values($runs->map(fn(ScenarioRun $run): array => $this->summarize($run))->all()),
            'pagination' => PaginationMeta::fromPaginator($runs),
            'stats' => $this->stats($data),
        ];
    }

    /**
     * Пользователи для фильтра «создал»: по явным id или строке поиска.
     *
     * @param  list<int>  $ids
     * @return list<array<string, mixed>>
     */
    public function lookupUsers(array $ids, string $search): array
    {
        return array_values($this->users->lookup($ids, $search, $this->projectId())
            ->map(static fn(User $user): array => [
                'id' => $user->id,
                'name' => $user->name ?? $user->login ?? "User #{$user->id}",
                'fio' => $user->fio,
            ])
            ->all());
    }

    /**
     * Создать прогон, вернуть payload плеера и разослать его в канал прогона.
     *
     * @return array<string, mixed>
     */
    public function store(ScenarioRunData $data): array
    {
        return $this->publish($this->player->payload($this->player->createRun($data, $this->projectId())));
    }

    public function start(ScenarioStartData $data): string
    {
        return $this->player->start($data, $this->projectId());
    }

    /** @return array<string, mixed> */
    public function show(string $runId): array
    {
        return $this->player->payload($this->player->getRun($this->run($runId)));
    }

    /** @return array<string, mixed> */
    public function continue(string $runId, ScenarioRunContinueData $data, ?int $actorId): array
    {
        $run = $this->player->continueRun($this->run($runId), $data);
        $this->markUpdatedBy($run, $actorId);

        return $this->publish($this->player->payload($run));
    }

    /** @return array<string, mixed> */
    public function retryAction(string $runId): array
    {
        $run = $this->player->retryActionNode($this->run($runId));

        return $this->publish($this->player->payload($run));
    }

    /** @return array<string, mixed> */
    public function jump(string $runId, ScenarioRunJumpData $data, ?int $actorId): array
    {
        $run = $this->player->jumpRun($this->run($runId), $data);
        $this->markUpdatedBy($run, $actorId);

        return $this->publish($this->player->payload($run));
    }

    /** @return list<array<string, mixed>> */
    public function historyFor(string $runId): array
    {
        return $this->history->buildHistory($this->run($runId));
    }

    private function filtered(ScenarioRunIndexData $data): ScenarioRunBuilder
    {
        return ScenarioRun::query()
            ->forProject($this->projectId())
            ->forScenario($data->scenarioId)
            ->createdByAny($data->createdBy)
            ->search($data->search)
            ->createdFrom($data->createdFrom)
            ->createdTo($data->createdTo);
    }

    /**
     * Статистика по статусам считается по тем же фильтрам, но без учёта status.
     *
     * @return array{total: int, active: int, completed: int, failed: int}
     */
    private function stats(ScenarioRunIndexData $data): array
    {
        $base = fn(): ScenarioRunBuilder => $this->filtered($data)->finished($data->finished);

        return [
            'total' => $base()->count(),
            'active' => $base()->status(ScenarioRunStatus::Active)->count(),
            'completed' => $base()->status(ScenarioRunStatus::Completed)->count(),
            'failed' => $base()->status(ScenarioRunStatus::Failed)->count(),
        ];
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
            'scenario_version_id' => $run->scenario_version_id,
            'scenario_version_name' => $run->version?->name,
            'scenario_version_created_at' => $run->version?->created_at?->toIso8601String(),
            'current_node_id' => $run->current_node_id,
            'status' => $run->status->value,
            'created_by' => $this->userRef($run->createdBy),
            'updated_by' => $this->userRef($run->updatedBy),
            'created_at' => $run->created_at?->toIso8601String(),
            'updated_at' => $run->updated_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed>|null */
    private function userRef(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        return [
            'id' => $user->id,
            'fio' => $user->fio,
            'login' => $user->login,
            'name' => $user->name ?? $user->login ?? null,
        ];
    }

    private function markUpdatedBy(ScenarioRun $run, ?int $actorId): void
    {
        if ($actorId !== null) {
            $this->runs->markUpdatedBy($run, $actorId);
        }
    }

    private function run(string $runId): ScenarioRun
    {
        return $this->runs->getByIdInProject($runId, $this->projectId());
    }

    private function projectId(): string
    {
        return $this->currentProject->id()
            ?? throw new \LogicException('Scenario run operations require a current project.');
    }

    /**
     * Публикует payload в канал scenario-run:{id} и возвращает его же.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function publish(array $payload): array
    {
        $run = $payload['run'] ?? null;
        $runId = is_array($run) ? ($run['id'] ?? null) : null;

        if (is_string($runId)) {
            $this->centrifugo->publish("scenario-run:{$runId}", ['type' => 'run_updated', ...$payload]);
        }

        return $payload;
    }
}
