---
name: vue-refactor
description: Refactor Vue 3 + TypeScript + Inertia frontend code so pages stay thin, logic lives in composables, types are explicit, and no `any` exists. Use when asked to refactor Vue components, extract composables, type a file, remove `any`, fix ESLint/TypeScript errors, or align frontend code with project conventions (shadcn-vue, Pinia, repositories, API conventions).
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
4. Run `npx eslint <file>` and `npx tsc --noEmit` after changes. Fix all reported errors.
5. Do not add features or change behavior — refactor only.

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
- Repositories contain only HTTP calls + response normalization, no type definitions

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

Repository files in `modules/<module>/repositories/` call these helpers and return typed results. Pages and composables call repositories, never `getJson` directly.

Filter params: `filter[search]=foo`, `filter[ids][]=1` — never flat `search=foo`.
Pagination params: `page[number]=2`, `page[size]=20` — never `per_page=`.
Build params as `URLSearchParams` in the composable and pass directly to the repository.

## Template Rules

- Use shadcn-vue components (`Button`, `Input`, `Select`, `Dialog`, `Table`, etc.) instead of native `<button>`, `<input>`, `<select>`, `<dialog>`, `<table>`
- Icons from `lucide-vue-next` only
- No inline styles — TailwindCSS classes only
- No logic in template expressions — move to `computed` or methods
- Prefer `:prop` shorthand over `:prop="true"` → just `prop`
- `v-html` only when necessary; comment why

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
npx eslint resources/js/<changed-file>
npx tsc --noEmit
```

Zero errors required. Warnings from `vue/require-default-prop` on shadcn-vue components are acceptable.

## Resources

Read [references/vue-refactor-rules.md](references/vue-refactor-rules.md) for quick decision tables on where logic should live and common antipatterns to avoid.
