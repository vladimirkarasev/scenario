<?php

declare(strict_types=1);

namespace App\Queries\Scenario;

use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Module\Scenario\Models\Scenario;

final class ActiveCatalogCategoryIdsQuery
{
    /** @return Collection<int, string> */
    public function get(): Collection
    {
        return DB::table('model_has_categories')
            ->join('scenarios', 'scenarios.id', '=', 'model_has_categories.model_id')
            ->join('scenario_versions as active_versions', static function (JoinClause $join): void {
                $join
                    ->on('active_versions.id', '=', 'scenarios.active_version_id')
                    ->where('active_versions.status', 'active');
            })
            ->where('model_has_categories.model_type', Scenario::class)
            ->distinct()
            ->pluck('model_has_categories.category_id')
            ->map(static fn(mixed $id): string => is_scalar($id) ? (string)$id : '');
    }
}
