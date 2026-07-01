<?php

declare(strict_types=1);

namespace App\Services\Scenario;

use App\Models\Category;
use Module\Users\Models\User;
use App\Queries\Scenario\ActiveCatalogCategoryIdsQuery;
use App\Queries\Scenario\CatalogItemsQueryBuilder;
use Illuminate\Support\Collection;
use Module\Scenario\DTO\CatalogItemRow;
use Module\Scenario\Models\Scenario;
use Module\Scenario\Models\ScenarioVersion;
use Module\Scenario\Models\ScenarioVersionRevision;
use Module\Scenario\Repositories\CategoryRepository;
use Module\Scenario\Repositories\ScenarioRepository;

final readonly class CatalogService
{
    public function __construct(
        private CategoryRepository $categoriesRepository,
        private ScenarioRepository $scenariosRepository,
        private CatalogItemsQueryBuilder $catalogItemsQuery,
        private ActiveCatalogCategoryIdsQuery $activeCatalogCategoryIds,
    ) {
    }

    /**
     * @return Collection<int, array{
     *     type: string,
     *     id: string,
     *     name: string,
     *     is_active: bool,
     *     active_version_id: string|null,
     *     parent_id: string|null,
     *     child_count: int,
     *     scenario_count: int,
     *     version_count: int
     * }>
     */
    public function catalogItems(?string $folderId = null, bool $activeOnly = false): Collection
    {
        $activeCategoryIds = $activeOnly ? $this->activeCategoryIdsWithActiveScenarios() : null;

        return $this->catalogItemsQuery
            ->get($folderId, $activeCategoryIds)
            ->map(static fn(CatalogItemRow $item): array => [
                'type' => $item->item_type,
                'id' => $item->item_type === 'category'
                    ? (string)$item->category_id
                    : (string)$item->scenario_id,
                'name' => $item->name,
                'is_active' => $item->is_active,
                'active_version_id' => $item->active_version_id,
                'parent_id' => $item->parent_id,
                'child_count' => $item->child_count,
                'scenario_count' => $item->scenario_count,
                'version_count' => $item->version_count,
            ]);
    }

    /** @return Collection<int, string> */
    public function activeCategoryIdsWithActiveScenarios(): Collection
    {
        $categories = $this->categoriesRepository->idParentPairs();

        $directCategoryIds = $this->activeCatalogCategoryIds->get();

        /** @var Collection<int, string> $visibleIds */
        $visibleIds = collect();

        foreach ($directCategoryIds as $categoryId) {
            $id = (string)$categoryId;

            while ($id !== '' && $categories->has($id)) {
                $visibleIds->push($id);
                $parent = $categories->get($id);
                $id = $parent !== null && $parent->parent_id !== null ? $parent->parent_id : '';
            }
        }

        return $visibleIds->unique()->values();
    }

    /** @return Collection<int, array<string, mixed>> */
    public function categories(): Collection
    {
        return $this->categoriesRepository
            ->orderedForCatalog()
            ->map(fn(Category $category): array => $this->categoryPayload($category))
            ->values();
    }

    /** @return Collection<int, array<string, mixed>> */
    public function scenarios(): Collection
    {
        return $this->scenariosRepository
            ->orderedForCatalog()
            ->map(fn(Scenario $scenario): array => $this->scenarioPayload($scenario));
    }

    /** @return array<string, mixed> */
    public function categoryPayload(Category $category): array
    {
        return [
            'id' => $category->id,
            'parent_id' => $category->parent_id,
            'name' => $category->name,
            'is_active' => $category->is_active,
            'scenario_count' => $category->scenarios_count ?? $category->scenarios()->count(),
            'created_at' => $category->created_at?->toIso8601String(),
            'updated_at' => $category->updated_at?->toIso8601String(),
            'parent' => $category->parent ? [
                'id' => $category->parent->id,
                'name' => $category->parent->name,
            ] : null,
            'created_by' => $this->userPayload($category->createdBy),
            'updated_by' => $this->userPayload($category->updatedBy),
        ];
    }

    /** @return array<string, mixed> */
    public function scenarioPayload(Scenario $scenario): array
    {
        return [
            'id' => $scenario->id,
            'name' => $scenario->name,
            'description' => $scenario->description,
            'is_active' => $scenario->is_active,
            'status' => $scenario->status->value,
            'active_version_id' => $scenario->active_version_id,
            'tag' => $scenario->tag,
            'aliases' => array_values(array_filter($scenario->aliases ?? [])),
            'categories' => $scenario->categories
                ->sortBy('name')
                ->map(fn(Category $category): array => [
                    'id' => $category->id,
                    'parent_id' => $category->parent_id,
                    'name' => $category->name,
                    'is_active' => $category->is_active,
                ])
                ->values()
                ->all(),
            'groups' => $scenario->groups
                ->sortBy('name')
                ->map(static fn($group): array => [
                    'id' => $group->id,
                    'name' => $group->name,
                ])
                ->values()
                ->all(),
            'versions' => $scenario->versions
                ->map(fn(ScenarioVersion $version): array => $this->versionPayload($version))
                ->values()
                ->all(),
            'created_at' => $scenario->created_at?->toIso8601String(),
            'updated_at' => $scenario->updated_at?->toIso8601String(),
            'created_by' => $this->userPayload($scenario->createdBy),
            'updated_by' => $this->userPayload($scenario->updatedBy),
        ];
    }

    /** @return array<string, mixed> */
    public function versionPayload(ScenarioVersion $version): array
    {
        return [
            'id' => $version->id,
            'name' => $version->name,
            'status' => $version->status,
            'schema_json' => $version->latestRevision->schema_json ?? new \stdClass,
            'input_fields' => $version->latestRevision->input_fields ?? [],
            'created_at' => $version->created_at?->toIso8601String(),
            'updated_at' => $version->updated_at?->toIso8601String(),
            'revisions' => $version->relationLoaded('revisions')
                ? $version->revisions->map(fn(ScenarioVersionRevision $revision): array => [
                    'id' => $revision->id,
                    'created_at' => $revision->created_at?->toIso8601String(),
                ])->values()->all()
                : [],
        ];
    }

    /** @return array<string, mixed>|null */
    private function userPayload(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'login' => $user->login,
        ];
    }
}
