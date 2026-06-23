<?php

declare(strict_types=1);

namespace Module\Categories\Services;

use App\Models\Category;
use Illuminate\Support\Facades\DB;
use Module\Scenario\Models\Scenario;

/**
 * Каскадное применение групп от категории-родителя ко всем дочерним
 * объектам (как чекбокс «Заменить все записи разрешений дочерних
 * объектов на записи, наследуемые от этого объекта» в Windows ACL).
 *
 * Sync (не merge): любые ранее привязанные группы у потомков заменяются
 * на переданный набор.
 */
final readonly class CategoryGroupsCascadeService
{
    /**
     * @param list<string> $groupIds
     */
    public function applyToDescendants(Category $root, array $groupIds): void
    {
        DB::transaction(function () use ($root, $groupIds): void {
            $descendantIds = $this->collectDescendantIds($root);

            if ($descendantIds !== []) {
                Category::query()
                    ->whereIn('id', $descendantIds)
                    ->get()
                    ->each(static fn (Category $c): array => $c->groups()->sync($groupIds));
            }

            $scenarioCategoryIds = array_values(array_unique([$root->id, ...$descendantIds]));

            /** @var list<string> $scenarioIds */
            $scenarioIds = DB::table('model_has_categories')
                ->where('model_type', Scenario::class)
                ->whereIn('category_id', $scenarioCategoryIds)
                ->pluck('model_id')
                ->map(static fn (mixed $v): string => is_string($v) ? $v : '')
                ->filter(static fn (string $v): bool => $v !== '')
                ->unique()
                ->values()
                ->all();

            if ($scenarioIds !== []) {
                Scenario::query()
                    ->whereIn('id', $scenarioIds)
                    ->get()
                    ->each(static fn (Scenario $s): array => $s->groups()->sync($groupIds));
            }
        });
    }

    /**
     * BFS по parent_id — собрать id всех потомков.
     *
     * @return list<string>
     */
    private function collectDescendantIds(Category $root): array
    {
        $collected = [];
        /** @var list<string> $queue */
        $queue = [$root->id];

        while (count($queue) > 0) {
            $children = Category::query()
                ->whereIn('parent_id', $queue)
                ->pluck('id')
                ->map(static fn (mixed $v): string => is_string($v) ? $v : '')
                ->filter(static fn (string $v): bool => $v !== '')
                ->values()
                ->all();

            if (count($children) === 0) {
                break;
            }

            foreach ($children as $id) {
                if (! in_array($id, $collected, true)) {
                    $collected[] = $id;
                }
            }
            $queue = $children;
        }

        return $collected;
    }
}
