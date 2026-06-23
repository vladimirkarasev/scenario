<?php

declare(strict_types=1);

namespace Module\Scenario\Http\Resources\JsonApi;

use App\Models\Category;
use Illuminate\Http\Resources\JsonApi\AnonymousResourceCollection;

final class ScenariosResourceCollection extends AnonymousResourceCollection
{
    /** @return array<string, mixed> */
    public function with($request): array
    {
        /** @var array<string, mixed> $base */
        $base = parent::with($request);

        /** @var list<mixed> $rawIncluded */
        $rawIncluded = is_array($base['included'] ?? null) ? array_values((array) $base['included']) : [];

        $knownIds   = [];
        $parentIds  = [];

        foreach ($rawIncluded as $item) {
            if (! is_array($item) || ($item['type'] ?? '') !== 'category') {
                continue;
            }
            /** @var array<string, mixed> $item */
            $id = is_string($item['id'] ?? null) ? $item['id'] : '';
            if ($id !== '') {
                $knownIds[] = $id;
            }

            $attrs    = $item['attributes'] ?? null;
            $parentId = $attrs instanceof \stdClass ? ($attrs->parent_id ?? null) : null;
            if (is_string($parentId) && $parentId !== '') {
                $parentIds[] = $parentId;
            }
        }

        $needed = array_values(array_diff($parentIds, $knownIds));

        if (empty($needed)) {
            return $base;
        }

        /** @var array<string, Category> $ancestors */
        $ancestors = [];

        while (! empty($needed)) {
            $rows = Category::query()
                ->whereIn('id', $needed)
                ->get(['id', 'parent_id', 'name', 'is_active']);

            foreach ($rows as $cat) {
                $ancestors[$cat->id] = $cat;
            }

            $newParents = [];
            foreach ($rows as $cat) {
                if ($cat->parent_id !== null) {
                    $newParents[] = $cat->parent_id;
                }
            }

            $known  = array_merge($knownIds, array_keys($ancestors));
            $needed = array_values(array_diff($newParents, $known));
        }

        if (empty($ancestors)) {
            return $base;
        }

        $existingIds   = array_column(array_filter($rawIncluded, 'is_array'), 'id');
        $ancestorItems = [];

        foreach ($ancestors as $id => $cat) {
            if (in_array($id, $existingIds, strict: true)) {
                continue;
            }
            $ancestorItems[] = [
                'id'            => $id,
                'type'          => 'category',
                'attributes'    => (object) [
                    'parent_id' => $cat->parent_id,
                    'name'      => $cat->name,
                    'is_active' => $cat->is_active,
                ],
                'relationships' => (object) [
                    'children' => ['meta' => ['count' => 0]],
                ],
            ];
        }

        $base['included'] = array_merge($rawIncluded, $ancestorItems);

        return $base;
    }
}
