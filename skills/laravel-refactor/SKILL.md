---
name: laravel-refactor
description: Refactor Laravel 13 + PHP 8.5 backend code — thin controllers, pure readonly services, typed DTOs, backed enums, PHPStan level 10. Use when asked to refactor PHP/Laravel code, move logic out of a controller, create a DTO or Form Request, fix PHPStan errors, or align module code with project conventions.
---

# Laravel Refactor

## Overview

Refactor backend code toward the project's conventions: controllers that only delegate, services that are pure and `readonly`, DTOs that carry typed data between layers, backed enums for all statuses and types, and zero PHPStan level 10 errors.

## Workflow

1. Read the target file(s) before touching anything.
2. Identify violations from the rules below.
3. Apply fixes in this order:
   - Structural violations (business logic in controller, `request()` in service)
   - Missing abstractions (Form Request needed, DTO needed, Enum needed)
   - Type violations (bare `array`, untyped params, missing `@return` annotations)
   - Dead code (`dd()`, `dump()`, commented-out blocks)
4. Run `./vendor/bin/phpstan analyse module/<Module> --memory-limit=512M` and `./vendor/bin/pint module/<Module>` after changes.
5. Do not add features or change behavior — refactor only.

## Module Structure

Domain logic lives in `module/<ModuleName>/` (namespace `Module\<ModuleName>\`):

```
module/<ModuleName>/
  Models/
  DTO/
  Enums/
  Exceptions/
  Http/
    Controllers/
    Requests/
    Resources/
  Services/
  Repositories/
  Providers/
  routes/api.php
```

`app/` is only for cross-cutting concerns: `User` model, auth middleware, global providers.

## Controllers

Controllers validate only; delegate everything to services.

```php
// Correct
final class DirectoryController extends Controller
{
    public function __construct(
        private readonly DirectoryService $service,
    ) {}

    public function store(DirectoryRequest $request): JsonResponse
    {
        $item = $this->service->create(DirectoryData::fromRequest($request));
        return (new DirectoryResource($item))->response()->setStatusCode(201);
    }
}

// Wrong
public function store(Request $request): JsonResponse
{
    $validated = $request->validate([...]);           // ← belongs in Form Request
    $item = Directory::query()->create($validated);   // ← belongs in service
    event(new DirectoryCreated($item));               // ← belongs in service
    return response()->json(['item' => $item]);
}
```

Rules:
- Use Form Request for all validation — `module/<Module>/Http/Requests/XxxRequest.php`
- Inject dependencies via constructor — never `app()` / `resolve()`
- Return either `Inertia::render(...)` or `response()->json()` — never mix in one controller
- Route model binding is fine in method signatures
- No `DB::transaction()`, no event dispatch, no mail — those go in services

## Services

Services are `final`, dependencies are `private readonly`. They never call `request()`.

```php
final class DirectoryService
{
    public function __construct(
        private readonly DirectoryRepository $directories,
        private readonly DirectoryVersionService $versions,
    ) {}

    public function create(DirectoryData $data): Directory
    {
        return DB::transaction(function () use ($data): Directory {
            $item = Directory::query()->create([
                'name' => $data->name,
                'slug' => $data->slug,
            ]);
            $this->versions->createInitial($item);
            return $item;
        });
    }
}
```

Rules:
- `final class` — never extend a service
- All dependencies `private readonly` via constructor
- Accept DTOs or typed scalars, never a `Request` object
- Wrap multi-step mutations in `DB::transaction()`
- One service = one domain responsibility; split when methods become unrelated

## DTOs

DTOs carry validated data between layers. They are `readonly` with a static `fromRequest()` factory.

```php
final readonly class DirectoryData
{
    /** @param string[] $categoryIds */
    public function __construct(
        public string  $name,
        public string  $slug,
        public ?string $description,
        public array   $categoryIds,
    ) {}

    public static function fromRequest(DirectoryRequest $request, ?Directory $existing = null): self
    {
        return new self(
            name:        $request->str('name')->toString(),
            slug:        $request->str('slug')->toString(),
            description: $request->filled('description') ? $request->str('description')->toString() : null,
            categoryIds: array_values(array_filter($request->array('category_ids'), is_string(...))),
        );
    }
}
```

Rules:
- `final readonly` — all properties are constructor-promoted and immutable
- Never store Eloquent models inside a DTO
- Live in `module/<Module>/DTO/`
- Named after the operation: `StoreDirectoryData`, `DirectoryData`, `DirectoryItemUpdateData`

## Form Requests

Form Requests contain validation rules and optional `prepareForValidation()` normalization.

```php
final class DirectoryRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['slug' => Str::slug($this->str('name')->toString())]);
    }
}
```

Rules:
- `authorize()` returns `true` — real authorization lives in middleware or service
- `rules()` returns `array<string, mixed>` — annotate the return type for PHPStan
- `prepareForValidation()` for normalization (slugify, cast types) before validation runs

## Enums

Use backed enums for all statuses and types. Always add `label()`, add `color()` when used in UI.

```php
enum DirectoryStatus: string
{
    case Active   = 'active';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Active   => 'Активный',
            self::Archived => 'Архивный',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active   => 'green',
            self::Archived => 'orange',
        };
    }
}
```

Never use string literals for status values in controllers, services, or queries — use `DirectoryStatus::Active->value` or cast the column via `$casts`.

## Models

- Fill `$fillable` or `$guarded` explicitly — never leave both empty
- Relationship methods contain no logic beyond the relation definition
- Scopes named with a verb: `scopeActive`, `scopeForProject`
- Custom attributes via `Attribute::make(get: ..., set: ...)` (Laravel 9+ style)
- Never query inside attribute getters — N+1

## PHP 8.5 Conventions

- Constructor promotion for all injected dependencies
- `match` instead of `switch` for exhaustive type dispatch
- Named arguments for multi-param calls where order is non-obvious
- Readonly properties for all value objects and DTOs
- Annotate all array types: `array<int, Foo>` or `list<string>` — never bare `array`
- Delete all `dd()`, `dump()`, `var_dump()`, `ray()`, and commented-out code blocks

## PHPStan Level 10

PHPStan runs at level 10 across `app/` and `module/`. All refactored code must pass.

Common fixes:
- Annotate `@return list<Foo>` or `@return array<string, Foo>` on all array returns
- Narrow `mixed` / `array` types at usage sites with `is_array()`, `instanceof`, or `assert()`
- Use `/** @var ClassName $var */` for route-bound models when PHPStan can't infer
- Add `@param array<string, mixed>` to methods that receive raw config or request arrays
- Never suppress errors in your own domain code — fix the type

## Verification

```bash
./vendor/bin/phpstan analyse module/<Module> --memory-limit=512M --error-format=table
./vendor/bin/pint module/<Module>
php artisan route:list   # when controller bindings or routes changed
```

Zero PHPStan errors required. Pint fixes style automatically — commit the result.

## Resources

Read [references/laravel-refactor-rules.md](references/laravel-refactor-rules.md) for the quick decision table and anti-pattern list.
