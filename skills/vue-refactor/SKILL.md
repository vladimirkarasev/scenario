---
name: vue-refactor
description: Refactor Vue 3 + TypeScript + Inertia frontend code so pages stay thin, logic lives in composables, types are explicit, requests are race-safe, deep reactivity is controlled, and UI follows project design and security conventions. Use when asked to refactor Vue components, audit watch/watchEffect or nesting depth, remove deep watchers, extract composables, type a file, remove `any`, fix frontend races, extract API work, or align code with shadcn-vue, Pinia and repositories.
---

# Vue Refactor

## Overview

Refactor frontend code toward the project's established conventions: thin page components that delegate to composables, strict TypeScript with no `any`, Pinia only for cross-page state, repositories for all HTTP calls, and types isolated in `types/` files.

## Workflow

1. Read the target file(s) before touching anything.
2. Identify violations from the rules below.
3. Apply fixes in this order:
   - TypeScript violations (missing types, `any`, wrong generics)
   - Structural violations (logic in template, missing composable extraction)
   - Import violations (wrong paths, missing type imports)
   - Style violations (native elements instead of shadcn-vue, inline styles)
4. Audit every `watch` / `watchEffect`: source breadth, nesting depth, trigger frequency and work performed per trigger.
5. Check request ordering, component lifecycle, HTML rendering, permissions and sibling-page design consistency.
6. Run checks only through Taskfile targets and fix all reported errors.
7. Do not add features or change behavior — refactor only.

## Script Setup Rules

Always use `<script setup lang="ts">`. Never use Options API.

Code order inside `<script setup>`:
1. `import` statements
2. Local interfaces and type aliases
3. `defineProps` / `defineEmits`
4. Helper functions (pure, no side effects)
5. `ref`, `reactive`, `computed` state
6. `watch` / `watchEffect`
7. Action methods
8. `onMounted` / `onUnmounted`

## TypeScript Rules

- All props: `defineProps<{ ... }>()` — never the object form with runtime validators unless a default is needed
- All emits: `defineEmits<{ eventName: [arg: Type] }>()`
- No `any` anywhere — use `unknown` at boundaries, then narrow or cast
- Catch blocks: `catch (err) { message = err instanceof Error ? err.message : String(err) }`
- HTTP responses from `getJson`/`sendJson`: use the generic — `getJson<{ item: Foo }>(...)`
- Types shared across files go in `modules/<module>/types/<name>.ts`, not inside repositories or components
- Repositories contain only HTTP calls + response normalization, with no exported domain type definitions

## Composables

Extract into a composable when a component has any of:
- data fetching + loading/error state
- a create/edit/delete dialog with a form
- filter state tied to a list

Naming: `use` + noun. Examples: `useUserList`, `useUserModal`, `useUserFilters`.

Standard pattern per page:
- `useXxxList` — fetch, pagination, search, `items`, `meta`, `loading`
- `useXxxModal` — dialog open state, form reactive object, `submit`, `remove`
- `useXxxFilters` — filter state, dropdown options, `URLSearchParams` builder

Composable returns only reactive refs, computed values, and methods. No raw reactive state exposed as mutable object properties.

Do **not** put UI state (dialogs, loading spinners) into Pinia stores.

## Concurrency and Lifecycle

- For list/search/filter/pagination reads use the project `useLatestRequest` composable. The latest started request is the only request allowed to update `items`, `meta`, `error` and `loading`.
- Treat debounce only as traffic reduction; it does not prevent an older response from overwriting a newer response.
- Invalidate pending reads on unmount when they can update state after the owner disappears.
- Do not use latest-wins for create/update/delete or autosave. Serialize mutations or use an explicit coalescing queue so acknowledged writes are not silently discarded.
- Do not reimplement request counters in each composable. Keep ordering behavior behind the shared Strategy/policy.

## Watch and Reactive Depth Audit

- Find every `watch`, `watchEffect`, writable `computed` and implicit two-way binding in the changed feature. Record what mutation makes each one run.
- Treat `deep: true` on documents, graphs, nested forms, node arrays and API payloads as a performance and correctness risk. Do not use it for persistence, parent synchronization or dirty tracking.
- Estimate reactive nesting before adding a watcher: object → arrays → items → `data` → nested fields/settings. The wider and deeper the structure, the more expensive traversal becomes even when the callback looks small.
- Prefer explicit mutation commands such as `addNode`, `updateField`, `applySettings` and `markChanged`. Persist only from an explicit save action unless autosave is a stated product requirement.
- Keep selection, hover, focus, drawer state and other ephemeral UI mutations outside the persisted document contract.
- If nested observation is genuinely required, watch the smallest scalar getter or stable signature. In Vue versions supporting numeric depth, use the minimum bounded `deep: N`; never choose unbounded depth by default.
- Never clone, stringify, normalize or emit the whole document from a watcher that can run during typing, dragging, resize, viewport movement or selection.
- Require a unit test proving that unrelated nested/UI mutations do not trigger persistence or a full-document emission.
- Also inspect component/template nesting: split a component when deep conditional branches make ownership of state and side effects unclear. Component extraction alone must not introduce duplicated watchers or hidden two-way synchronization.

## Pinia

Use Pinia only for state shared across multiple pages or routes:
- scenario editor document state
- catalog tree state
- action manager state

Do **not** use Pinia for local component state, form state, or dialog open/close.

## HTTP / API Conventions

All API calls go through `resources/js/lib/http.ts` helpers:
- `getJson<T>(url, fallbackMessage)` → `Promise<T>`
- `sendJson<T>(url, { method, body, fallbackMessage })` → `Promise<T>`
- `sendMultipart<T>(url, { method, body, fallbackMessage })` → `Promise<T>`
- `destroyJson(url, fallbackMessage)` → `Promise<null>`

Repository files in `modules/<module>/repositories/` call these helpers and return typed results. Infrastructure adapters under `lib/` may own transport setup. Pages, components, stores and feature composables call repositories, never HTTP helpers or axios directly.

Filter params: `filter[search]=foo`, `filter[ids][]=1` — never flat `search=foo`.
Pagination params: `page[number]=2`, `page[size]=20` — never `per_page=`.
Build params as `URLSearchParams` in the composable and pass directly to the repository.

## Template Rules

- Use shadcn-vue components (`Button`, `Input`, `Select`, `Dialog`, `Table`, etc.) instead of native `<button>`, `<input>`, `<select>`, `<dialog>`, `<table>`
- Icons from `lucide-vue-next` only
- No inline styles — TailwindCSS classes only
- No logic in template expressions — move to `computed` or methods
- Prefer `:prop` shorthand over `:prop="true"` → just `prop`
- Render rich HTML only through the centralized safe-html sanitizer. Do not bind untrusted content directly to `v-html`.
- Use `rel="noopener noreferrer"` with `target="_blank"`.

## Design Consistency

- Use `AppShell` and `PageHeader` for application pages.
- Use `app-page`, `app-page-container`, `app-panel` and `app-error` instead of copying large page-level Tailwind chains.
- Reuse `SearchInput`, `EmptyState` and `ListPagination` for list states.
- Extract module navigation into a shared tabs component when two or more sibling pages use it.
- Use semantic CSS variables for colors and verify both light and dark themes.
- Hide unavailable actions according to permissions; a disabled button is not an authorization boundary.

## ESLint Rules (enforced)

- `@typescript-eslint/no-explicit-any` — error
- `@typescript-eslint/no-unused-vars` — error (prefix `_` to allow)
- `@typescript-eslint/consistent-type-imports` — use `import type { ... }`
- `vue/component-api-style` — script-setup only
- `vue/define-macros-order` — defineProps before defineEmits, both before any function
- `vue/prefer-true-attribute-shorthand` — `:open="true"` → `open`
- `vue/no-mutating-props` — add `/* eslint-disable */` comment with reason if intentional (composable context pattern)

## Verification

After every refactor:

```bash
task lint
task typecheck
task test:frontend -- <changed-test-path>
```

Run `task build:frontend` after module-wide changes. Zero errors are required. Warnings from `vue/require-default-prop` on shadcn-vue components are acceptable only when they are part of the known baseline.

## Resources

Read [references/vue-refactor-rules.md](references/vue-refactor-rules.md) for quick decision tables on where logic should live and common antipatterns to avoid.
