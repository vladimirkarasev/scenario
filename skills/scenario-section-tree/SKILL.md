---
name: scenario-section-tree
description: useScenarioSectionTree / useDirectorySectionTree — lazy section tree for categorized entities. Loads children level-by-level, exposes categoryFilterIds (stable ref, updates only after children load) to trigger entity reload once. Apply when adding section-based navigation or porting tree behaviour to another module.
---

# Section Tree (lazy tree для категорий)

Паттерн lazy-загрузки дерева категорий уровень за уровнем. Реализация — `useDirectorySectionTree` (эталон) и `useScenarioSectionTree` (порт для сценариев). Применяй при добавлении section-based навигации в новый модуль.

## Файлы-эталоны

- `resources/js/modules/directories/composables/useDirectorySectionTree.ts` — directories.
- `resources/js/composables/useScenarioSectionTree.ts` — scenarios.
- `Pages/Directories/Index.vue` / `Pages/Scenario/Scenarios.vue` — потребители.

## Ключевые детали (запомнить, чтобы не наступать)

1. **`categoryFilterIds` — стабильный ref**, обновляется ТОЛЬКО после загрузки детей. Иначе сущности перезагружаются дважды на каждый клик по папке.
2. **`children_count`** на категории нужно считать **только релевантными детьми**:
   - Backend: `withCount(['children' => function (Builder $q) use ($relevantIds) { $q->whereIn('id', $relevantIds); }])`.
   - Сервис: `relevantCategories()` использует **ancestor-walking algorithm** — поднимается от потомка к корню по `parent_id`, собирая всех предков; потом фильтрует исходный список по этому множеству.
3. **`activeFolder`** — локальный ref на странице, не в композабле (потому что может включать спецзначения вроде `'fav'`, `'all'`).
4. **Триггер загрузки сущностей** — `watch(categoryFilterIds, loadEntities, { immediate: true })` или `onMounted(loadEntities)` + watch.

## API эндпоинта категорий

Контроллер наследует `CategoryController` (см. [[categories-pattern]]) и переопределяет `index()`:

- Принимает `filter[parent_id]` для запроса детей конкретного узла.
- Возвращает JSON:API c `relationships.children.meta.count` — это и есть `children_count`.

```php
// module/Scenario/Http/Controllers/ScenarioCategoryController.php
public function index(Request $request)
{
    $parentId = $request->input('filter.parent_id');
    $categories = $this->service->relevantCategories(Scenario::class, $parentId);
    return new CategoryCollection($categories);
}
```

## Frontend нормализация

В репозитории:

```ts
function normalize(raw: RawCategory): Category {
  return {
    id: raw.id,
    name: raw.attributes.name,
    parent_id: raw.attributes.parent_id,
    children_count: raw.relationships?.children?.meta?.count ?? 0,
  }
}
```

`ScenarioCategory` (`types/scenario.ts`) обязательно с `children_count: number`.

## Структура composable

```ts
export function useScenarioSectionTree() {
  const tree              = ref<TreeNode[]>([])
  const expanded          = ref<Set<string>>(new Set())
  const activeSection     = ref<string | 'all'>('all')
  const categoryFilterIds = ref<string[]>([])      // СТАБИЛЬНЫЙ — apply после загрузки детей
  const allSectionsFlat   = computed(() => flatten(tree.value))

  async function expandNode(id: string) {
    if (expanded.value.has(id)) return
    const children = await scenarioRepository.categoriesByParent(id)
    attachChildren(tree.value, id, children)
    expanded.value.add(id)
  }

  function selectSection(id: string | 'all') {
    activeSection.value = id
    // обновляем categoryFilterIds ПОСЛЕ того, как дети известны
    categoryFilterIds.value = id === 'all' ? [] : collectDescendantIds(tree.value, id)
  }

  return { tree, expanded, activeSection, categoryFilterIds, allSectionsFlat, expandNode, selectSection, ... }
}
```

## Backend `relevantCategories()` — ancestor walking

Алгоритм:

1. Получить все категории текущего модуля (через `model_has_categories.model_type`).
2. Собрать `ancestorIds`: для каждой релевантной категории подняться по `parent_id` до корня, добавляя предков.
3. Вернуть категории, чьи id есть в `ancestorIds ∪ relevantIds`.
4. `withCount(['children' => fn (Builder $q) => $q->whereIn('id', $allIds)])` — чтобы папка показывала только релевантных детей.

Это нужно потому, что без подъёма пользователь увидит лист, но не родительские папки, в которых он лежит.

## Существующие потребители

- **Directories** — оригинальный паттерн. `module/Directories/Services/DirectoryCategoryService.php::relevantCategories`.
- **Scenarios** — порт. `module/Scenario/Services/ScenarioCategoryService.php`.

## Связанные

- [[categories-pattern]] — общая база `Module\Categories`.
- [[jsonapi-conventions]] — формат ответа.
- [[composables-pattern]] / `.claude/commands/composable.md` — общий паттерн List + Modal + Filters.
