---
name: static-analysis
description: Run static analysis on backend (PHPStan level 10 + Pint) and frontend (TypeScript + ESLint + Knip). Use when asked to check types, fix type errors, lint code, find unused exports, or clean up a module before a PR.
---

# Static Analysis

## Overview

| Tool | Target | Command |
|---|---|---|
| PHPStan | PHP backend | `./vendor/bin/phpstan analyse <path> --memory-limit=512M` |
| Pint | PHP style | `./vendor/bin/pint <path>` |
| TypeScript | Vue + TS files | `npx tsc --noEmit` |
| ESLint | JS/TS/Vue files | `npm run lint` |
| Knip | Unused exports | `npm run knip` |

Run **backend** and **frontend** independently — they have nothing in common.

---

## Backend

### PHPStan

```bash
# Analyse a single module
./vendor/bin/phpstan analyse module/Scenario --memory-limit=512M --error-format=table

# Analyse multiple paths
./vendor/bin/phpstan analyse module/Scenario module/Proxy --memory-limit=512M --error-format=table

# Full project (app/ + all modules — slow)
./vendor/bin/phpstan analyse --memory-limit=512M --error-format=table
```

Config: `phpstan.neon` — level 10, Larastan extension, paths `app/` + `module/`.

Output columns: `Line`, `Error message`, `(identifier)`. The identifier links to the PHPStan error documentation — use it when looking up fixes.

#### Common errors and fixes at level 10

**`Cannot cast mixed to int` (cast.int)**

```php
// Bad — $request->input() returns mixed
$perPage = (int) $request->input('page.size', 20);

// Fix — assert the type first
$perPage = max(1, min(100, (int) $request->input('page.size', 20)));
// PHPStan still sees mixed. Correct fix:
$raw = $request->input('page.size', 20);
$perPage = is_numeric($raw) ? (int) $raw : 20;

// Or use the typed accessor
$perPage = (int) $request->integer('page.size', 20);
```

**`Method X returns mixed but return type is Y`**

```php
// Bad
public function find(int $id): User
{
    return User::query()->find($id); // returns Model|null, not User
}

// Fix
public function find(int $id): ?User
{
    return User::query()->find($id);
}
// Or use findOrFail() which returns User (not mixed)
return User::query()->findOrFail($id);
```

**`Parameter $x of method Y expects Foo, mixed given`**

```php
// Bad
$filter = $request->array('filter');
$this->repo->paginate($filter['search']); // mixed

// Fix — narrow the type before passing
$search = isset($filter['search']) && is_string($filter['search']) ? $filter['search'] : null;
$this->repo->paginate($search);
```

**`Property X has no type declaration`**

```php
// Bad
class Foo {
    public $bar;
}

// Fix
class Foo {
    public string $bar;
    // or
    public ?string $bar = null;
}
```

**`Call to an undefined method X::y()`**

Usually happens with Eloquent scopes or dynamic methods — add a `@method` docblock or use `/** @var Builder<Model> $query */`.

**`Doctrine\DBAL\* not found`** / **`Larastan ignoreErrors patterns not matched`**

These are expected warnings from the global `ignoreErrors` in `phpstan.neon`. They appear when running on a small subset that doesn't contain the code those patterns suppress. Not real errors — ignore.

#### PHPStan anti-patterns

- Do not add `@phpstan-ignore-next-line` without a comment explaining why
- Do not widen return types to `mixed` to silence errors — narrow the source type instead
- Do not add `@param mixed $x` when you can use a narrower type
- Do not cast `mixed` directly — always check `is_string()` / `is_int()` / `is_array()` first

### Pint (code style)

```bash
# Dry-run — show what would change
./vendor/bin/pint module/Scenario --test

# Apply fixes
./vendor/bin/pint module/Scenario

# Single file
./vendor/bin/pint module/Scenario/Services/ScenarioService.php
```

Pint runs automatically after each PHPStan pass — always run both together before committing.

### Workflow for a module

```bash
# 1. Style first (so PHPStan sees clean code)
./vendor/bin/pint module/<Module>

# 2. Static analysis
./vendor/bin/phpstan analyse module/<Module> --memory-limit=512M --error-format=table

# 3. Fix errors, repeat until clean
```

---

## Frontend

### TypeScript

```bash
npx tsc --noEmit
```

Checks all `.ts` and `.vue` files under paths declared in `tsconfig.json`. Config: strict mode, `noEmit: true`, path alias `@/*` → `resources/js/*`.

#### Common errors and fixes

**`Type 'string | undefined' is not assignable to type 'string'`**

```ts
// Bad
const name: string = props.user?.name

// Fix — handle undefined
const name = props.user?.name ?? ''
// or narrow with a guard
if (!props.user) return
const name: string = props.user.name
```

**`Argument of type 'X | null' is not assignable to parameter of type 'X'`**

```ts
// Bad
doSomething(ref.value) // ref.value is Foo | null

// Fix
if (ref.value !== null) {
    doSomething(ref.value)
}
// or use non-null assertion if null is impossible at this point
doSomething(ref.value!)
```

**`Object is possibly 'undefined'`**

```ts
// Bad
const id = items.find(i => i.active).id

// Fix
const id = items.find(i => i.active)?.id
```

**`Property 'x' does not exist on type 'Y'`**

Usually caused by a missing type declaration. Add the property to the interface, or use a type guard.

**`'any' is not allowed` (no-explicit-any)**

```ts
// Bad
const data: any = response.data

// Fix — use unknown and narrow
const data: unknown = response.data
if (typeof data === 'object' && data !== null && 'id' in data) {
    // narrow further
}
// Or use a typed interface
const data = response.data as MyResponseDto
```

**`Type 'X' is not assignable to type 'never'`**

Usually a union exhaustiveness issue. Add the missing case or a proper fallback.

### ESLint

```bash
# Check
npm run lint
# equivalent to: npx eslint resources/js

# Auto-fix (safe fixes only)
npm run lint:fix
# equivalent to: npx eslint resources/js --fix
```

Config: `eslint.config.mjs` — flat config (ESLint 9), `@typescript-eslint`, `eslint-plugin-vue`.

#### Key rules enforced

| Rule | Level | Notes |
|---|---|---|
| `@typescript-eslint/no-explicit-any` | error | Use `unknown` or a typed interface |
| `@typescript-eslint/no-unused-vars` | error | Prefix with `_` if intentionally unused |
| `@typescript-eslint/consistent-type-imports` | error | Must use `import type` for type-only imports |
| `vue/component-api-style: ['script-setup']` | error | Options API is forbidden |
| `vue/define-macros-order` | error | `defineProps` before `defineEmits` |

#### Common fixes

**`consistent-type-imports`** — ESLint auto-fixes this with `--fix`:
```ts
// Bad
import { User } from '@/types'

// Fix
import type { User } from '@/types'
```

**`no-unused-vars`**:
```ts
// Bad
const unused = 42

// Fix — delete it, or prefix with _ if it must stay
const _unused = 42
```

**`no-explicit-any`**:
```ts
// Bad
function process(data: any) {}

// Fix
function process(data: unknown) {}
// or a typed overload
function process(data: MyData) {}
```

### Knip (unused exports)

```bash
npm run knip
```

Reports: unused files, unused exports, unused dependencies. Run before a PR to catch dead code.

Do not suppress Knip warnings with ignores unless the export is used dynamically (e.g., a plugin hook called by name). If it's genuinely unused, delete it.

### Workflow for frontend

```bash
# 1. ESLint auto-fix (safe changes)
npm run lint:fix

# 2. TypeScript — full check
npx tsc --noEmit

# 3. Manual fixes for remaining errors

# 4. Final ESLint check (no --fix)
npm run lint

# 5. Unused exports (optional, before PR)
npm run knip
```

---

## Decision Table

| Situation | What to do |
|---|---|
| PHPStan `cast.int` on `$request->input()` | Use `$request->integer('key')` or `is_numeric()` guard |
| PHPStan `mixed` returned from Eloquent | Use `findOrFail()` or add `@var` annotation with correct type |
| PHPStan ignoreErrors pattern not matched | Expected warning when running on a subset — not a real error |
| PHPStan error in vendor / generated code | Add narrow `ignoreErrors` entry in `phpstan.neon` with identifier |
| TSC error in a `.vue` file | Run `npx tsc --noEmit` — it covers Vue files via `vue-tsc` config |
| ESLint error that can't be auto-fixed | Fix manually — do not use `eslint-disable` without a reason |
| Knip reports a false positive | Use `knip.config.ts` `ignore` — document why it's needed |
| Error only in CI but not locally | Check Node / PHP version mismatch; run in Docker via `task shell` |

## References

See [references/static-analysis-reference.md](references/static-analysis-reference.md) for error identifier quick-reference.
