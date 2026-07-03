<?php

declare(strict_types=1);

namespace Module\Scenario\Services;

use App\Exceptions\NotFoundException;
use Module\Users\Models\User;
use denis660\Centrifugo\Centrifugo;
use Module\Scenario\DTO\ScenarioDispatchData;
use Module\Scenario\DTO\ScenarioRunData;
use Module\Scenario\Enums\ScenarioErrorCode;
use Module\Scenario\Enums\ScenarioStatus;
use Module\Scenario\Models\Scenario;
use Module\Scenario\Repositories\ScenarioRunRepository;

final readonly class ScenarioDispatchService
{
    public function __construct(
        private Centrifugo $centrifugo,
        private ScenarioPlayerService $player,
        private ScenarioRunRepository $runs,
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
        $projectId = $serviceUser->project_id
            ?? throw NotFoundException::from(ScenarioErrorCode::DispatchNoProjectContext);

        $target = User::query()
            ->forProject($projectId)
            ->when(
                $data->login !== null,
                fn($q) => $q->where('login', $data->login),
                fn($q) => $q->where('external_id', $data->externalId),
            )
            ->first()
            ?? throw NotFoundException::from(ScenarioErrorCode::DispatchTargetUserNotFound);

        $scenario = Scenario::query()
            ->forProject($projectId)
            ->isActive(true)
            ->hasActiveVersion()
            ->status(ScenarioStatus::Active)
            ->tags([$data->tag])
            ->orderByDesc('created_at')
            ->first()
            ?? throw NotFoundException::make(
                sprintf('Активный сценарий с тегом «%s» не найден.', $data->tag),
                ScenarioErrorCode::DispatchScenarioNotFound,
            );

        $run = $this->player->createRun(
            new ScenarioRunData(
                scenarioId: $scenario->id,
                scenarioVersionId: $scenario->active_version_id,
                context: $data->context,
                userData: $data->userData,
                operatorId: $target->id,
            )
        );

        $this->runs->assignActor($run, $target->id);

        $this->centrifugo->publish("#user:{$target->id}", [
            'type' => 'start_scenario',
            'run_id' => $run->id,
            'url' => (string)route('workspace.run', ['run' => $run->id], absolute: false),
        ]);

        return $run->id;
    }
}
