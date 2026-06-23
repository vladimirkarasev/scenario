# Static Analysis — Quick Reference

## Backend (PHPStan level 10)

### Commands

```bash
./vendor/bin/phpstan analyse module/<Module> --memory-limit=512M --error-format=table
./vendor/bin/pint module/<Module>
./vendor/bin/pint module/<Module> --test   # dry-run
```

### PHPStan error identifiers → fix

| Identifier | Meaning | Fix |
|---|---|---|
| `cast.int` | Casting `mixed` to `int` | Use `$request->integer()` or `is_numeric()` guard |
| `cast.string` | Casting `mixed` to `string` | Use `$request->string()` or `is_string()` guard |
| `argument.type` | `mixed` passed where typed expected | Narrow with `is_*()` before calling |
| `return.type` | Return type mismatch | Fix return type annotation or value |
| `property.notFound` | Accessing undefined property | Add `@property` docblock or fix relation |
| `method.notFound` | Calling undefined method | Add `@method` docblock or fix scope name |
| `nullsafe.neverNull` | Nullsafe `?->` on non-nullable | Remove `?->` |
| `offsetAccess.notFound` | Array key access on `mixed` | Assert `is_array()` first |
| `missingType.return` | No return type declared | Add return type |
| `missingType.parameter` | No param type declared | Add parameter type |

### Global ignoreErrors in phpstan.neon

The four global patterns suppress known Larastan limitations. Running PHPStan on a **subset** (e.g. one handler) will show "ignored error pattern was not matched" — this is expected, not a real error. Only run the full `module/<Name>` to avoid this noise.

### Eloquent return type helpers

```php
// Returns Model|null
User::query()->find($id);

// Returns Model (throws ModelNotFoundException if missing)
User::query()->findOrFail($id);

// Returns Collection<int, User>
User::query()->where(...)->get();

// Returns LengthAwarePaginator<int, User>
User::query()->paginate(20, ['*'], 'page[number]');
```

---

## Frontend (TypeScript + ESLint)

### Commands

```bash
npx tsc --noEmit               # TypeScript check
npm run lint                   # ESLint check
npm run lint:fix               # ESLint auto-fix
npm run knip                   # Unused exports/files
```

### TypeScript error patterns → fix

| Error | Typical cause | Fix |
|---|---|---|
| `'X' is possibly 'undefined'` | Optional chaining not handled | `?.` access or narrow with `if` |
| `'X' is possibly 'null'` | Nullable ref/prop | `?? fallback` or `!` if impossible |
| `Type 'X' is not assignable to 'Y'` | Wrong type passed | Fix the type or cast with `as` |
| `Property 'x' does not exist on 'Y'` | Missing interface field | Add field to interface |
| `Object is of type 'unknown'` | Unnarrowed `unknown` value | Use type guards or cast |
| `Cannot find module '@/...'` | Wrong import path | Check `tsconfig.json` paths alias |
| `Type 'string \| null' is not assignable to 'string'` | Unhandled null | `?? ''` fallback or nullable type |

### ESLint rule identifiers → fix

| Rule | Fix |
|---|---|
| `@typescript-eslint/no-explicit-any` | Replace `any` with `unknown` or typed interface |
| `@typescript-eslint/no-unused-vars` | Delete or prefix with `_` |
| `@typescript-eslint/consistent-type-imports` | Change `import` to `import type` (auto-fixable) |
| `vue/component-api-style` | Rewrite to `<script setup lang="ts">` |
| `vue/define-macros-order` | Put `defineProps` before `defineEmits` (auto-fixable) |
| `vue/no-unused-vars` | Remove unused template vars |

### Type import pattern

```ts
// Always use type imports for interfaces/types
import type { User, UserGroup } from '@/types'

// Value imports for runtime values (classes, enums, functions)
import { UserRole } from '@/modules/users/types'
```

### Unknown narrowing patterns

```ts
// Pattern 1: type guard function
function isUser(x: unknown): x is User {
    return typeof x === 'object' && x !== null && 'id' in x
}

// Pattern 2: cast with as (when shape is guaranteed by API contract)
const user = data as User

// Pattern 3: zod/validation (if used)
const user = UserSchema.parse(data)
```

---

## Workflow checklist

### Before committing backend changes

- [ ] `./vendor/bin/pint module/<Module>`
- [ ] `./vendor/bin/phpstan analyse module/<Module> --memory-limit=512M --error-format=table` — 0 errors

### Before committing frontend changes

- [ ] `npm run lint:fix`
- [ ] `npx tsc --noEmit` — 0 errors
- [ ] `npm run lint` — 0 errors
- [ ] `npm run knip` — no unexpected unused exports

### When PHPStan errors seem unfixable

1. Check if the error has an identifier — look it up in PHPStan docs
2. Check if Larastan has a known issue for it
3. Add a narrow `ignoreErrors` with the specific identifier and file path
4. Add a comment explaining why the suppression is needed
