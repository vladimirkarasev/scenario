# Add CRUD JSON:API — Quick Checklist

Replace `Widget`/`widget` with the actual resource name throughout.

## Model (`module/<Module>/Models/Widget.php`)

- [ ] `$fillable` or `$guarded` set explicitly
- [ ] Scopes for filters: `scopeSearch`, `scopeActive`, etc.

## Repository (`module/<Module>/Repositories/WidgetRepository.php`)

- [ ] `paginate(WidgetIndexData $filters): LengthAwarePaginator<int, Widget>`
  - [ ] Passes `'page[number]'` as page name to `->paginate()`
- [ ] `create(array $attributes): Widget`
- [ ] `update(Widget $widget, array $attributes): Widget`
- [ ] `delete(Widget $widget): void`

## DTOs (`module/<Module>/DTO/`)

- [ ] `WidgetIndexData` — reads `filter[...]` and `page[size]`; static `fromRequest(Request)`
- [ ] `WidgetData` — reads write fields; static `fromRequest(Request)`
- [ ] Both are `final readonly` classes

## Form Request (`module/<Module>/Http/Requests/WidgetRequest.php`)

- [ ] `authorize(): bool { return true; }`
- [ ] `rules(): array<string, mixed>` — annotated return type
- [ ] Unique rules use `->ignore($widget?->id)` for update
- [ ] `prepareForValidation()` if normalization needed (slugify, trim)

## Service (`module/<Module>/Services/WidgetService.php`)

- [ ] `final class`, all deps `private readonly`
- [ ] `paginate(WidgetIndexData): LengthAwarePaginator`
- [ ] `find(Widget): Widget` (loads relations)
- [ ] `create(WidgetData): Widget`
- [ ] `update(WidgetData, Widget): Widget`
- [ ] `delete(Widget): void`
- [ ] Wraps multi-step mutations in `DB::transaction()`

## JSON:API Resource (`module/<Module>/Http/Resources/JsonApi/WidgetResource.php`)

- [ ] `extends JsonApiResource`
- [ ] `protected bool $usesRequestQueryString = false;`
- [ ] `toId(Request): string`
- [ ] `toType(Request): string` — kebab-case plural noun
- [ ] `toAttributes(Request): array<string, mixed>`
- [ ] `@mixin Widget` (if backed by Model) or `@property array<string, mixed> $resource` (if backed by array)
- [ ] Dates formatted with `->toIso8601String()`

## Controller (`module/<Module>/Http/Controllers/WidgetController.php`)

- [ ] `final class`, `private readonly` service injected via constructor
- [ ] `index` → `WidgetResource::collection($paginator)` — returns `AnonymousResourceCollection`
- [ ] `show` → `new WidgetResource($widget)` — returns `WidgetResource`
- [ ] `store` → `new JsonResponse(new WidgetResource($widget), 201)`
- [ ] `update` → `new JsonResponse(new WidgetResource($widget))` — 200
- [ ] `destroy` → `new JsonResponse(status: 204)`

## Routes (`module/<Module>/routes/api.php`)

- [ ] `can:widget_view` middleware on GET routes
- [ ] `can:widget_create` middleware on POST/PUT/PATCH routes
- [ ] `can:widget_delete` middleware on DELETE routes
- [ ] Both `put` and `patch` registered for update

## Decision Table

| Situation | What to do |
|---|---|
| Resource has many filter options | Create `WidgetIndexData` DTO with `fromRequest()` reading `$request->array('filter')` |
| List and detail have different eager loads | Split into `WidgetListController` + `WidgetDetailController` |
| Service returns shaped array, not Model | Use `@property array<string, mixed> $resource` and read via `$this->resource['key']` |
| Resource has relationships | Add `toRelationships()` to the resource; load relations in service's `find()` |
| Need `meta` on list response | `.additional(['meta' => $result['meta']])` on the collection |
| Paginator already has meta | Pass `LengthAwarePaginator` directly — `JsonApiResource::collection()` handles it |
| No permissions yet | See `add-new-permission` skill first |

## Anti-Patterns

Do not:
- put `$request->input(...)` calls inside the service — use DTOs
- use flat filter params `search=foo` — always `filter[search]=foo`
- use `per_page=` — always `page[size]=`
- forget `$usesRequestQueryString = false` on the resource — it pollutes `links`
- skip the repository and query Eloquent directly from the service
