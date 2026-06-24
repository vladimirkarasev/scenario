<?php

declare(strict_types=1);

namespace Module\Categories\Repositories;

use App\Models\Category;
use Closure;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\Eloquent\Collection;

/**
 * Кеширующий декоратор над {@see CategoryRepository}.
 *
 * Кешируются «тяжёлые» выборки разделов (forModel / forModelByParent с
 * подзапросами и withCount), чтобы дерево/списки выбора грузились быстрее.
 * Любая запись (create/update/delete) инвалидирует кеш через bump версии —
 * это не требует taggable-стора и работает на любом драйвере кеша.
 */
final readonly class CachedCategoryRepository implements CategoryRepositoryContract
{
    private const int TTL = 300;

    private const string VERSION_KEY = 'categories:cache:version';

    public function __construct(
        private CategoryRepositoryContract $repository,
        private CacheRepository $cache,
    ) {}

    /** @return Collection<int, Category> */
    public function forModel(string $modelClass, ?string $projectId = null): Collection
    {
        return $this->remember(
            'forModel',
            [$modelClass, $projectId],
            fn (): Collection => $this->repository->forModel($modelClass, $projectId),
        );
    }

    /** @return Collection<int, Category> */
    public function forModelByParent(string $modelClass, ?string $parentId, ?string $projectId = null): Collection
    {
        return $this->remember(
            'forModelByParent',
            [$modelClass, $parentId, $projectId],
            fn (): Collection => $this->repository->forModelByParent($modelClass, $parentId, $projectId),
        );
    }

    /** @return Collection<int, Category> */
    public function all(): Collection
    {
        return $this->repository->all();
    }

    /** @return Collection<int, Category> */
    public function withRelations(): Collection
    {
        return $this->repository->withRelations();
    }

    /** @return array<int, string> */
    public function childIds(string $parentId): array
    {
        return $this->repository->childIds($parentId);
    }

    public function find(string $id): ?Category
    {
        return $this->repository->find($id);
    }

    public function loadRelations(Category $category): Category
    {
        return $this->repository->loadRelations($category);
    }

    /** @param  array<string, mixed>  $attributes */
    public function create(array $attributes, ?int $actorId): Category
    {
        $category = $this->repository->create($attributes, $actorId);
        $this->flush();

        return $category;
    }

    /** @param  array<string, mixed>  $attributes */
    public function update(Category $category, array $attributes, ?int $actorId): Category
    {
        $updated = $this->repository->update($category, $attributes, $actorId);
        $this->flush();

        return $updated;
    }

    public function delete(Category $category): void
    {
        $this->repository->delete($category);
        $this->flush();
    }

    /**
     * Кешируем «сырые» атрибуты моделей (примитивы), а не сами Eloquent-объекты —
     * это надёжно при любом драйвере кеша. На чтении регидрируем в модели.
     *
     * @param  list<string|null>                    $parts
     * @param  Closure(): Collection<int, Category> $resolver
     * @return Collection<int, Category>
     */
    private function remember(string $method, array $parts, Closure $resolver): Collection
    {
        $signature = md5(implode('|', array_map(static fn (?string $p): string => $p ?? 'null', $parts)));
        $key = sprintf('categories:%d:%s:%s', $this->version(), $method, $signature);

        /** @var list<array<string, mixed>> $rows */
        $rows = $this->cache->remember(
            $key,
            self::TTL,
            static fn (): array => $resolver()
                ->map(static fn (Category $category): array => $category->getAttributes())
                ->values()
                ->all(),
        );

        return Category::hydrate($rows);
    }

    private function version(): int
    {
        $version = $this->cache->get(self::VERSION_KEY, 1);

        return is_numeric($version) ? (int) $version : 1;
    }

    private function flush(): void
    {
        $this->cache->forever(self::VERSION_KEY, $this->version() + 1);
    }
}
