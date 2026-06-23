<?php

declare(strict_types=1);

namespace Module\Scenario\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use denis660\Centrifugo\Centrifugo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Module\Scenario\DTO\ScenarioRunContinueData;
use Module\Scenario\DTO\ScenarioRunData;
use Module\Scenario\DTO\ScenarioRunJumpData;
use Module\Scenario\Enums\ScenarioRunStatus;
use Module\Scenario\Http\Requests\ContinueScenarioRunRequest;
use Module\Scenario\Http\Requests\JumpScenarioRunRequest;
use Module\Scenario\Http\Requests\StoreScenarioRunRequest;
use Module\Scenario\Models\ScenarioRun;
use Module\Scenario\Services\ScenarioPlayerService;

final class ScenarioRunController extends Controller
{
    public function __construct(
        private readonly ScenarioPlayerService $scenarioPlayerService,
        private readonly Centrifugo $centrifugo,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var array<string, mixed> $filter */
        $filter = is_array($request->input('filter')) ? $request->array('filter') : [];

        $query = ScenarioRun::query()
            ->with(['scenario', 'version', 'createdBy', 'updatedBy'])
            ->latest();

        $this->applyFilters($query, $filter);

        $perPage = max(1, min(100, $request->integer('page.size', 20)));
        $page    = max(1, $request->integer('page.number', 1));
        $runs    = $query->paginate($perPage, ['*'], 'page', $page);

        // Статистика по статусам — считаем по тем же фильтрам, но без учёта status
        $statsBase = ScenarioRun::query();
        $this->applyFilters($statsBase, array_diff_key($filter, ['status' => true]));
        $stats = [
            'total'     => (clone $statsBase)->count(),
            'active'    => (clone $statsBase)->where('status', ScenarioRunStatus::Active)->count(),
            'completed' => (clone $statsBase)->where('status', ScenarioRunStatus::Completed)->count(),
            'failed'    => (clone $statsBase)->where('status', ScenarioRunStatus::Failed)->count(),
        ];

        return new JsonResponse([
            'runs' => $runs->map(fn (ScenarioRun $run) => [
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
                'created_by' => $run->createdBy ? [
                    'id' => $run->createdBy->id,
                    'fio' => $run->createdBy->fio,
                    'login' => $run->createdBy->login,
                    'name' => $run->createdBy->name ?? $run->createdBy->login ?? null,
                ] : null,
                'updated_by' => $run->updatedBy ? [
                    'id' => $run->updatedBy->id,
                    'fio' => $run->updatedBy->fio,
                    'login' => $run->updatedBy->login,
                    'name' => $run->updatedBy->name ?? $run->updatedBy->login ?? null,
                ] : null,
                'created_at' => $run->created_at?->toIso8601String(),
                'updated_at' => $run->updated_at?->toIso8601String(),
            ]),
            'pagination' => [
                'current_page' => $runs->currentPage(),
                'last_page' => $runs->lastPage(),
                'per_page' => $runs->perPage(),
                'total' => $runs->total(),
                'from' => $runs->firstItem(),
                'to' => $runs->lastItem(),
            ],
            'stats' => $stats,
            'total' => $runs->total(),
        ]);
    }

    /**
     * @param  Builder<ScenarioRun>   $query
     * @param  array<string, mixed>   $filter
     */
    private function applyFilters(Builder $query, array $filter): void
    {
        if (! empty($filter['scenario_id']) && is_string($filter['scenario_id'])) {
            $query->where('scenario_id', $filter['scenario_id']);
        }

        if (! empty($filter['status']) && is_string($filter['status'])) {
            $query->where('status', ScenarioRunStatus::from($filter['status']));
        } elseif (! empty($filter['finished'])) {
            $query->whereIn('status', [ScenarioRunStatus::Completed, ScenarioRunStatus::Failed]);
        }

        if (! empty($filter['created_by'])) {
            $raw = $filter['created_by'];
            $ids = array_filter(is_array($raw) ? $raw : [$raw], 'is_numeric');
            if ($ids !== []) {
                $query->whereIn('created_by', $ids);
            }
        }

        if (! empty($filter['search']) && is_string($filter['search'])) {
            $search = trim($filter['search']);
            if ($search !== '') {
                $query->where(static function ($q) use ($search): void {
                    $q->whereHas('scenario', static fn ($sq) => $sq->where('name', 'like', "%{$search}%"))
                        ->orWhere('id', 'like', "%{$search}%");
                });
            }
        }

        if (! empty($filter['created_from']) && is_string($filter['created_from'])) {
            $query->whereDate('created_at', '>=', $filter['created_from']);
        }

        if (! empty($filter['created_to']) && is_string($filter['created_to'])) {
            $query->whereDate('created_at', '<=', $filter['created_to']);
        }
    }

    public function users(Request $request): JsonResponse
    {
        $filter = is_array($request->input('filter')) ? $request->array('filter') : [];
        $search = is_string($filter['search'] ?? null) ? trim($filter['search']) : '';
        $rawIds = $filter['ids'] ?? null;
        $ids    = is_array($rawIds) ? array_filter($rawIds, 'is_numeric') : [];

        $query = User::query()->orderBy('name')->limit(30);

        if ($ids !== []) {
            $query->whereIn('id', $ids);
        } elseif ($search !== '') {
            $query->where(static function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('fio', 'like', "%{$search}%")
                    ->orWhere('login', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        } else {
            $query->limit(0);
        }

        return new JsonResponse([
            'users' => $query->get()->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name ?? $user->login ?? "User #{$user->id}",
                'fio' => $user->fio,
            ]),
        ]);
    }

    public function store(StoreScenarioRunRequest $request): JsonResponse
    {
        $run = $this->scenarioPlayerService->createRun(ScenarioRunData::fromRequest($request));
        $payload = $this->scenarioPlayerService->payload($run);
        $this->publishRun($payload);

        return new JsonResponse($payload, 201);
    }

    public function show(string $runId): JsonResponse
    {
        $run = $this->scenarioPlayerService->getRun(ScenarioRun::query()->findOrFail($runId));

        return new JsonResponse($this->scenarioPlayerService->payload($run));
    }

    public function continue(ContinueScenarioRunRequest $request, string $runId): JsonResponse
    {
        $run = $this->scenarioPlayerService->continueRun(
            ScenarioRun::query()->findOrFail($runId),
            ScenarioRunContinueData::fromRequest($request),
        );

        if ($userId = $request->user()?->id) {
            ScenarioRun::query()->where('id', $runId)->update(['updated_by' => $userId]);
        }

        $payload = $this->scenarioPlayerService->payload($run);
        $this->publishRun($payload);

        return new JsonResponse($payload);
    }

    public function jump(JumpScenarioRunRequest $request, string $runId): JsonResponse
    {
        $run = $this->scenarioPlayerService->jumpRun(
            ScenarioRun::query()->findOrFail($runId),
            ScenarioRunJumpData::fromRequest($request),
        );

        if ($userId = $request->user()?->id) {
            ScenarioRun::query()->where('id', $runId)->update(['updated_by' => $userId]);
        }

        $payload = $this->scenarioPlayerService->payload($run);
        $this->publishRun($payload);

        return new JsonResponse($payload);
    }

    /** @param array<string, mixed> $payload */
    private function publishRun(array $payload): void
    {
        $run = $payload['run'] ?? null;
        $runId = is_array($run) ? ($run['id'] ?? null) : null;

        if (is_string($runId)) {
            $this->centrifugo->publish("scenario-run:{$runId}", $payload);
        }
    }
}
