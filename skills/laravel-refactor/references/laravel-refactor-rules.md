# Laravel Refactor Rules

## Decision Table: Where Does Logic Go?

| What is it? | Where it goes |
|---|---|
| Input validation + normalization | Form Request (`Http/Requests/`) |
| HTTP response shape / status code | Controller method |
| Domain mutation (create / update / delete) | Service method |
| Multi-step orchestration | Service method wrapped in `DB::transaction()` |
| Data carried between Request and Service | `readonly` DTO (`DTO/`) |
| Status / type constants | Backed Enum (`Enums/`) with `label()` / `color()` |
| Derived model attribute | `Attribute::make()` on the Model |
| Reusable DB filter | Eloquent scope (`scopeXxx`) on the Model |
| Cross-module shared service | `app/Services/` |
| JSON:API resource shaping | `Http/Resources/` (Laravel API Resource) |

## Controller Anti-Patterns

Do not:
- call `$request->validate([...])` inline — use a Form Request
- write `new SomeService()` — inject via constructor
- use `app()` / `resolve()` — prefer constructor DI
- mix `Inertia::render()` and `response()->json()` in one controller
- call `DB::transaction()` — that's the service's job
- dispatch events, send mail, or fire jobs from a controller method
- access `$request->user()` for domain checks — pass the user into the service

## Service Anti-Patterns

Do not:
- call `request()` helper — accept all data as typed arguments or DTOs
- instantiate models with `new Model()` — use `Model::query()->create()`
- put unrelated domain operations in one service (one service = one domain)
- skip `DB::transaction()` on multi-step mutations that must be atomic
- return raw Eloquent models when the caller needs a shaped array — shape in the service or resource

## DTO Anti-Patterns

Do not:
- store Eloquent model instances inside a DTO
- mutate DTO properties after construction (use `readonly`)
- use `array` as a DTO substitute — create a typed class instead
- put validation rules inside a DTO — that belongs in the Form Request

## PHPStan Anti-Patterns

Do not:
- use `@phpstan-ignore-next-line` in your own domain code — fix the type
- return bare `array` — annotate as `array<string, mixed>` or `list<Foo>`
- leave `mixed` parameters that flow into typed operations without a narrowing check

## Naming Conventions

| Kind | Pattern | Example |
|---|---|---|
| Service | `NounService` | `DirectoryService`, `DirectoryVersionService` |
| DTO | `NounData` or `VerbNounData` | `DirectoryData`, `DirectoryItemUpdateData` |
| Form Request | `VerbNounRequest` or `NounRequest` | `DirectoryRequest`, `StoreDirectoryImportRequest` |
| Enum | Noun | `ScenarioStatus`, `ScenarioNodeType` |
| Enum case | PascalCase | `case Active = 'active'` |
| Repository | `NounRepository` | `DirectoryRepository`, `DirectoryItemRepository` |
| Exception | `NounException` | `DirectoryException` |

## Extraction Order

When pulling logic out of a fat controller method:

1. Create the Form Request — move `validate()` rules there
2. Create the DTO — map Request fields to typed properties in `fromRequest()`
3. Create or extend the Service — move the body of the controller method there, accepting the DTO
4. Wrap multi-step persistence in `DB::transaction()`
5. Slim the controller down to: `FormRequest → DTO → service call → response`
