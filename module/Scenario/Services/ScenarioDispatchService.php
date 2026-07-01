<?php

declare(strict_types=1);

namespace Module\Scenario\Services;

use Module\Users\Models\User;
use denis660\Centrifugo\Centrifugo;
use Module\Scenario\DTO\ScenarioDispatchData;
use Module\Scenario\DTO\ScenarioRunData;
use Module\Scenario\Enums\ScenarioStatus;
use Module\Scenario\Models\Scenario;
use Module\Scenario\Models\ScenarioRun;
use RuntimeException;

final readonly class ScenarioDispatchService
{
    public function __construct(
        private Centrifugo $centrifugo,
        private ScenarioPlayerService $player,
    ) {
    }

    /**
     * Найти сценарий по тегу в проекте сервис-юзера, найти целевого пользователя
     * по login/external_id в том же проекте, создать ScenarioRun на бэкенде,
     * опубликовать start_scenario в персональный канал целевого пользователя
     * и вернуть id созданного прогона.
     */
    public function dispatch(User $serviceUser, ScenarioDispatchData $data): string
    {
        $projectId = $serviceUser->project_id;

        if ($projectId === null) {
            throw new RuntimeException('Service user is not bound to a project.');
        }

        $target = User::query()
            ->where('project_id', $projectId)
            ->when(
                $data->login !== null,
                fn($q) => $q->where('login', $data->login),
                fn($q) => $q->where('external_id', $data->externalId),
            )
            ->first();

        if ($target === null) {
            throw new RuntimeException('Target user not found in the project.');
        }

        $scenario = Scenario::query()
            ->where('project_id', $projectId)
            ->where('is_active', true)
            ->whereNotNull('active_version_id')
            ->status(ScenarioStatus::Active)
            ->tags([$data->tag])
            ->orderByDesc('created_at')
            ->first();

        if ($scenario === null) {
            throw new RuntimeException("No active scenario found for tag '{$data->tag}'.");
        }

        $run = $this->player->createRun(
            new ScenarioRunData(
                scenarioId: $scenario->id,
                scenarioVersionId: $scenario->active_version_id,
                context: $data->context,
                userData: $data->userData,
                operatorId: $target->id,
            )
        );

        ScenarioRun::query()->where('id', $run->id)->update([
            'created_by' => $target->id,
            'updated_by' => $target->id,
        ]);

        $this->centrifugo->publish("#user:{$target->id}", [
            'type' => 'start_scenario',
            'run_id' => $run->id,
            'url' => (string)route('workspace.run', ['run' => $run->id], absolute: false),
        ]);

        return $run->id;
    }
}
