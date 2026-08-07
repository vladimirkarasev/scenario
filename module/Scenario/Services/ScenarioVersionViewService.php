<?php

declare(strict_types=1);

namespace Module\Scenario\Services;

use App\Support\PaginationMeta;
use Module\Scenario\DTO\ScenarioVersionHistoryData;
use Module\Scenario\Models\ScenarioVersion;
use Module\Scenario\Models\ScenarioVersionRevision;

final readonly class ScenarioVersionViewService
{
    /** @return array<string, mixed> */
    public function editor(ScenarioVersion $version): array
    {
        $version->loadMissing('latestRevision');

        return $this->versionPayload($version, true);
    }

    /** @return array<string, mixed> */
    public function settings(ScenarioVersion $version): array
    {
        return $this->versionPayload($version, false);
    }

    /**
     * @return array{
     *     revisions: list<array{id: string, created_at: string|null}>,
     *     pagination: array{current_page: int, last_page: int, per_page: int, total: int, from: int|null, to: int|null}
     * }
     */
    public function history(ScenarioVersion $version, ScenarioVersionHistoryData $data): array
    {
        $paginator = $version->revisions()->paginate(
            perPage: $data->perPage,
            page: $data->page,
        );

        /** @var list<array{id: string, created_at: string|null}> $revisions */
        $revisions = array_values($paginator->getCollection()
                ->map(static fn(ScenarioVersionRevision $revision): array => [
                    'id' => $revision->id,
                    'created_at' => $revision->created_at?->toIso8601String(),
                ])
                ->values()
                ->all());

        return [
            'revisions' => $revisions,
            'pagination' => PaginationMeta::fromPaginator($paginator),
        ];
    }

    /** @return array<string, mixed> */
    private function versionPayload(ScenarioVersion $version, bool $withSchema): array
    {
        $latestRevision = $withSchema ? $version->getRelation('latestRevision') : null;

        return [
            'id' => $version->id,
            'scenario_id' => $version->scenario_id,
            'name' => $version->name,
            'status' => $version->status,
            'schema_json' => $withSchema && $latestRevision instanceof ScenarioVersionRevision
                ? ($latestRevision->schema_json ?? [])
                : [],
            'created_at' => $version->created_at?->toIso8601String(),
            'updated_at' => $version->updated_at?->toIso8601String(),
        ];
    }
}
