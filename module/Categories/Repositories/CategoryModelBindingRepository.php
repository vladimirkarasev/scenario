<?php

declare(strict_types=1);

namespace Module\Categories\Repositories;

use App\Models\Category;
use Illuminate\Database\DatabaseManager;

final readonly class CategoryModelBindingRepository
{
    public function __construct(private DatabaseManager $database) {}

    /** @param class-string $modelType */
    public function attach(Category $category, string $modelType, ?string $projectId): void
    {
        $this->database->table('model_has_categories')->insertOrIgnore([
            'category_id' => $category->id,
            'model_id' => $category->id,
            'model_type' => $modelType,
            'project_id' => $projectId,
            'created_at' => $category->created_at,
            'updated_at' => $category->updated_at,
        ]);
    }

    /** @param class-string $modelType */
    public function exists(Category $category, string $modelType, ?string $projectId): bool
    {
        return $this->database->table('model_has_categories')
            ->where('category_id', $category->id)
            ->where('model_type', $modelType)
            ->where('project_id', $projectId)
            ->exists();
    }
}
