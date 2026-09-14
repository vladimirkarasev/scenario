<?php

declare(strict_types=1);

namespace Module\Scenario\Services\Runtime;

use App\Events\CentrifugoMessagePublished;
use App\Exceptions\NotFoundException;
use Illuminate\Contracts\Events\Dispatcher;
use Module\Users\Models\User;
use Module\Scenario\DTO\ScenarioDispatchData;
use Module\Scenario\DTO\ScenarioRunData;
use Module\Scenario\Enums\ScenarioErrorCode;
use Module\Scenario\Enums\ScenarioStatus;
use Module\Scenario\Models\Scenario;
use Module\Scenario\Repositories\ScenarioRunRepository;

final readonly class ScenarioDispatchService
{
    public function __construct(
        private ScenarioPlayerService $player,
        private ScenarioRunRepository $runs,
        private Dispatcher $events,
    ) {
    }

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
            ),
            $projectId,
        );

        $this->runs->assignActor($run, $target->id);

        $this->events->dispatch(new CentrifugoMessagePublished("#user:{$target->id}", [
            'type' => 'start_scenario',
            'run_id' => $run->id,
            'url' => (string)route('workspace.run', ['run' => $run->id], absolute: false),
        ]));

        return $run->id;
    }
}
