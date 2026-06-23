# Vue Refactor Rules

## Decision Table: Where Does Logic Go?

| What is it? | Where it goes |
|---|---|
| Data fetch + loading/error for a page | `useXxxList` composable |
| Dialog open state + form + submit/delete | `useXxxModal` composable |
| Filter inputs + URLSearchParams builder | `useXxxFilters` composable |
| Cross-page shared state (catalog, editor) | Pinia store |
| HTTP call + response normalization | Repository |
| Shared types used across files | `modules/<module>/types/<name>.ts` |
| One-off derived value from props/state | `computed()` in the component |
| Template-only helper (label, color, icon) | Local function in `<script setup>` |

## TypeScript: `any` Replacement Guide

| Situation | Replace with |
|---|---|
| HTTP response from `getJson` | `getJson<{ item: Foo }>(...)`  |
| HTTP response from `sendJson` | `sendJson<{ item: Foo }>(...)`  |
| Event handler `(e: any)` | `(e: Event)` or `(e: MouseEvent)` etc. |
| Catch block `(err: any)` | `catch (err)` + `err instanceof Error ? err.message : String(err)` |
| `ref<any>(null)` | `ref<FooType \| null>(null)` |
| `ref<any[]>([])` | `ref<FooType[]>([])` |
| Nested unknown API value | `asRecord(v: unknown)` helper → `Record<string, unknown>` |
| forEach/map callback `(item: any)` | Remove annotation — TypeScript infers from array type |
| Template cast `(mode.id as any)` | `(mode.id as typeof viewMode)` |

## Composable Extraction Checklist

Extract a composable when the component has:
- [ ] `loading` + `errorMessage` + a fetch function
- [ ] `dialogOpen` + `form` reactive + `submit` async function
- [ ] `filters` reactive + watcher that calls fetch
- [ ] Same pattern already exists in another composable in `composables/`

Do **not** extract when:
- logic is 5 lines or fewer and not reused
- it would just be a wrapper with no real encapsulation

## Pinia vs Composable

Use Pinia when: state must survive route changes, be read by unrelated sibling routes, or be initialized once app-wide.

Use composable when: state is scoped to one page or one component subtree, or resets on unmount.

## API Call Anti-Patterns

Do not:
- call `getJson` / `sendJson` directly from a component — use a repository
- put filter param names flat: `search=foo` — use `filter[search]=foo`
- use `per_page=` — use `page[size]=`
- define types inside repository files — put them in `types/`
- pass the entire Axios response object up — normalize in the repository

## ESLint Suppressions

Only suppress with an inline comment when the rule conflicts with an intentional architectural pattern:

```ts
/* eslint-disable vue/no-mutating-props -- formData is a reactive Record passed for in-place mutation by design */
```

Never suppress `@typescript-eslint/no-explicit-any`. Fix the type instead.

## File Naming

All frontend files use kebab-case:
- Components: `DirectoryTreeNode.vue`
- Composables: `useDirectoryItems.ts`
- Repositories: `directoryRepository.ts`
- Types: `directory.ts` inside `types/`
- Stores: `scenarioCatalog.ts`

## Naming Conventions

| Kind | Pattern | Example |
|---|---|---|
| Composable | `useNounVerb` or `useNoun` | `useDirectoryItems`, `useUserModal` |
| Pinia store | noun + context | `scenarioCatalog`, `actionManager` |
| Repository const | `nounRepository` | `directoryRepository` |
| Type file | singular noun | `directory.ts`, `scenario.ts` |
| Props interface | `Props` (local) or exported with component name | — |
