---
name: add-crud-jsonapi
description: Add a full JSON:API CRUD endpoint set to a Laravel module — resource, controller(s), form request, DTO, service, repository, and routes with permission middleware. Use when asked to create a new CRUD API, add list/show/store/update/destroy endpoints, or wire up a new resource following project conventions.
---

# Add CRUD with JSON:API

## Overview

Every CRUD resource follows the same layered structure:

```
Request → FormRequest (validate) → Controller (delegate) → Service (orchestrate)
       → Repository (DB) → Model → JsonApiResource (shape) → JSON response
```

Response shape (JSON:API) — конверт и ошибки см. [[api-response-contract]]:
- **List**: `{ data: [...], meta: { current_page, last_page, per_page, total, timestamp, requestId } }`
- **Single**: `{ data: { id, type, attributes: {...} }, meta: { timestamp, requestId } }`
- **Create**: same as single, HTTP 201
- **Delete**: HTTP 204, empty body
- **Errors**: `{ errors: [ { status, code, title, detail, source? } ], meta }` — бросать доменные исключения (`ForbiddenException`/`NotFoundException`/`ConflictException`), не `abort()`/`HttpException`

Key paths:
- `module/<Module>/Http/Controllers/`
- `module/<Module>/Http/Requests/`
- `module/<Module>/Http/Resources/JsonApi/`
- `module/<Module>/DTO/`
- `module/<Module>/Services/`
- `module/<Module>/Repositories/`
- `module/<Module>/routes/api.php`

## Step-by-Step

### 1. Model

Ensure `$fillable` is set. Add scopes for common filters.

```php
final class Widget extends Model
{
    use HasUuids;

    protected $fillable = ['name', 'slug', 'description', 'is_active', 'project_id'];

    /** @param Builder<Widget> $query */
    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        return $search ? $query->where('name', 'like', "%{$search}%") : $query;
    }

    /** @param Builder<Widget> $query */
    public function scopeActive(Builder $query, ?bool $active): Builder
    {
        return $active !== null ? $query->where('is_active', $active) : $query;
    }
}
```

### 2. Repository

Encapsulates all Eloquent queries. Returns typed models or paginators.

```php
final class WidgetRepository
{
    /** @return LengthAwarePaginator<int, Widget> */
    public function paginate(WidgetIndexData $filters): LengthAwarePaginator
    {
        return Widget::query()
            ->search($filters->search)
            ->active($filters->isActive)
            ->orderBy('name')
            ->paginate($filters->perPage, ['*'], 'page[number]');
    }

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): Widget
    {
        return Widget::query()->create($attributes);
    }

    /** @param array<string, mixed> $attributes */
    public function update(Widget $widget, array $attributes): Widget
    {
        $widget->update($attributes);
        return $widget;
    }

    public function delete(Widget $widget): void
    {
        $widget->delete();
    }
}
```

### 3. DTOs

**Index DTO** — reads `filter[...]` and `page[size]` from request:

```php
final readonly class WidgetIndexData
{
    public function __construct(
        public ?string $search,
        public ?bool $isActive,
        public int $perPage,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $filter = $request->array('filter');

        return new self(
            search: isset($filter['search']) && is_string($filter['search']) && $filter['search'] !== ''
                ? $filter['search']
                : null,
            isActive: isset($filter['is_active']) && $filter['is_active'] !== ''
                ? filter_var($filter['is_active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                : null,
            perPage: max(1, min(100, (int) $request->input('page.size', 20))),
        );
    }
}
```

**Write DTO** — reads from FormRequest:

```php
final readonly class WidgetData
{
    public function __construct(
        public string $name,
        public string $slug,
        public ?string $description,
        public bool $isActive,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            name:        $request->string('name')->toString(),
            slug:        $request->string('slug')->toString(),
            description: $request->filled('description') ? $request->string('description')->toString() : null,
            isActive:    $request->boolean('is_active', true),
        );
    }
}
```

### 4. Form Request

```php
final class WidgetRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Widget|null $widget */
        $widget = $this->route('widget');

        return [
            'name'        => ['required', 'string', 'max:255'],
            'slug'        => ['required', 'string', 'max:255', 'alpha_dash',
                Rule::unique('widgets', 'slug')->ignore($widget?->id),
            ],
            'description' => ['nullable', 'string'],
            'is_active'   => ['boolean'],
        ];
    }
}
```

### 5. Service

```php
final class WidgetService
{
    public function __construct(
        private readonly WidgetRepository $widgets,
    ) {}

    /** @return LengthAwarePaginator<int, Widget> */
    public function paginate(WidgetIndexData $filters): LengthAwarePaginator
    {
        return $this->widgets->paginate($filters);
    }

    public function find(Widget $widget): Widget
    {
        return $widget; // load relations here if needed
    }

    public function create(WidgetData $data): Widget
    {
        return $this->widgets->create([
            'name'        => $data->name,
            'slug'        => $data->slug,
            'description' => $data->description,
            'is_active'   => $data->isActive,
        ]);
    }

    public function update(WidgetData $data, Widget $widget): Widget
    {
        return $this->widgets->update($widget, [
            'name'        => $data->name,
            'slug'        => $data->slug,
            'description' => $data->description,
            'is_active'   => $data->isActive,
        ]);
    }

    public function delete(Widget $widget): void
    {
        $this->widgets->delete($widget);
    }
}
```

### 6. JSON:API Resource

Extends `Illuminate\Http\Resources\JsonApi\JsonApiResource`.

**From a Model (`@mixin`):**

```php
/**
 * @mixin Widget
 */
final class WidgetResource extends JsonApiResource
{
    protected bool $usesRequestQueryString = false;

    public function toId(Request $request): string
    {
        return (string) $this->id;
    }

    public function toType(Request $request): string
    {
        return 'widgets';
    }

    /** @return array<string, mixed> */
    public function toAttributes(Request $request): array
    {
        return [
            'name'        => $this->name,
            'slug'        => $this->slug,
            'description' => $this->description,
            'is_active'   => $this->is_active,
            'created_at'  => $this->created_at?->toIso8601String(),
            'updated_at'  => $this->updated_at?->toIso8601String(),
        ];
    }
}
```

**From an array (service returns shaped array, not Model):**

```php
/**
 * @property array<string, mixed> $resource
 */
final class WidgetResource extends JsonApiResource
{
    protected bool $usesRequestQueryString = false;

    public function toId(Request $request): string
    {
        $id = $this->resource['id'];
        return is_scalar($id) ? (string) $id : '';
    }

    public function toType(Request $request): string { return 'widgets'; }

    /** @return array<string, mixed> */
    public function toAttributes(Request $request): array
    {
        return array_diff_key($this->resource, ['id' => null]);
    }
}
```

**With relationships:**

```php
/** @return array<string, mixed> */
public function toRelationships(Request $request): array
{
    return [
        'created_by' => fn () => $this->createdBy ? [
            'data' => ['id' => $this->createdBy->id, 'name' => $this->createdBy->name],
        ] : null,
    ];
}
```

Always set `protected bool $usesRequestQueryString = false;` — prevents Laravel from appending the query string to `links` in the response.

### 7. Controller(s)

**Single controller for simple resources:**

```php
final class WidgetController extends Controller
{
    public function __construct(private readonly WidgetService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $paginator = $this->service->paginate(WidgetIndexData::fromRequest($request));
        return WidgetResource::collection($paginator);
    }

    public function show(Widget $widget): WidgetResource
    {
        return new WidgetResource($this->service->find($widget));
    }

    public function store(WidgetRequest $request): JsonResponse
    {
        $widget = $this->service->create(WidgetData::fromRequest($request));
        return new JsonResponse(new WidgetResource($widget), 201);
    }

    public function update(WidgetRequest $request, Widget $widget): JsonResponse
    {
        $widget = $this->service->update(WidgetData::fromRequest($request), $widget);
        return new JsonResponse(new WidgetResource($widget));
    }

    public function destroy(Widget $widget): JsonResponse
    {
        $this->service->delete($widget);
        return new JsonResponse(status: 204);
    }
}
```

**Split controllers** (preferred when list/detail have different deps):
- `WidgetListController` — `__invoke`, handles `index`
- `WidgetDetailController` — `show`
- `WidgetController` — `store`, `update`, `destroy`

Split when: the list controller needs different eager loads, filters, or a separate service method than the detail view.

### 8. Routes

File: `module/<Module>/routes/api.php`

```php
Route::middleware('can:widget_view')->group(static function (): void {
    Route::get('widgets',          [WidgetController::class, 'index']);
    Route::get('widgets/{widget}', [WidgetController::class, 'show']);
});

Route::middleware('can:widget_create')->group(static function (): void {
    Route::post('widgets',              [WidgetController::class, 'store']);
    Route::put('widgets/{widget}',      [WidgetController::class, 'update']);
    Route::patch('widgets/{widget}',    [WidgetController::class, 'update']);
});

Route::middleware('can:widget_delete')->group(static function (): void {
    Route::delete('widgets/{widget}', [WidgetController::class, 'destroy']);
});
```

Routes are picked up automatically via `$this->loadRoutesFrom(...)` in the module's `ServiceProvider`. The api middleware group (`auth:sanctum`, rate limiting) is applied at the provider level.

## Response Shape Reference

| Operation | Method | Status | Body |
|---|---|---|---|
| List | GET `/api/widgets` | 200 | `{ data: [...], meta: {...} }` |
| Show | GET `/api/widgets/{id}` | 200 | `{ data: { id, type, attributes } }` |
| Create | POST `/api/widgets` | 201 | `{ data: { id, type, attributes } }` |
| Update | PUT `/api/widgets/{id}` | 200 | `{ data: { id, type, attributes } }` |
| Delete | DELETE `/api/widgets/{id}` | 204 | _(empty)_ |

## Filter and Pagination Conventions

```
GET /api/widgets?filter[search]=foo&filter[is_active]=1&page[size]=20&page[number]=2
```

- Filter params: `filter[key]=value` — never flat `key=value`
- Pagination: `page[number]` and `page[size]` — never `page=` or `per_page=`
- Read in DTO: `$request->array('filter')`, `$request->input('page.size')`
- Pass `'page[number]'` as the page name to `->paginate()` so Laravel reads the right param

## Verification

```bash
./vendor/bin/phpstan analyse module/<Module> --memory-limit=512M
./vendor/bin/pint module/<Module>
php artisan route:list | grep widget
```

## References

See [references/add-crud-jsonapi-checklist.md](references/add-crud-jsonapi-checklist.md) for the quick checklist.
