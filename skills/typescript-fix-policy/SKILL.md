---
name: typescript-fix-policy
description: When vue-tsc or tsc shows TypeScript errors — fix ALL of them, including pre-existing ones unrelated to current changes. Apply after any frontend change before declaring work done; also when user runs tsc and surfaces errors.
---

# TypeScript Fix Policy

Когда `vue-tsc --noEmit` / `tsc` показывает ошибки — чинить **все** найденные, не только связанные с текущим изменением.

**Why:** В проекте действует правило «чистый tsc-вывод». Pre-existing ошибки — не повод их игнорировать; они накапливаются и в какой-то момент маскируют новые. Пользователь явно просил так делать.

**How to apply:**

1. После любых правок фронта обязательно: `./node_modules/.bin/vue-tsc --noEmit`.
2. Если есть ошибки — поправить **все**, не отделяя «свои» от «чужих».
3. Если ошибка в стороннем коде (зависимость, генерированный файл) — исправлять конфигурацией `tsconfig`, shim-файлами (`resources/js/shims-vue.d.ts`, `resources/js/globals.d.ts` для глобалов вроде `route()`), а не `@ts-ignore`.
4. Не помечать задачу как готовую, пока есть ошибки tsc.
5. Lint (`npm run lint`) проверяется отдельно — там warnings допускаются, errors — нет, см. также правило про неиспользуемые импорты.

**Связанные шаги:**
- `composer run dev` для запуска фронта; `vue-tsc` — отдельная команда.
- `tsconfig.json` уже настроен с `allowArbitraryExtensions` и `vite/client`.

**Анти-паттерн:** оставить ошибки со словами «они были до меня».
