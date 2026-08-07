---
name: test-with-taskfile
description: Run and report project tests, static analysis, Rector checks and frontend builds exclusively through Taskfile targets. Use when asked to test changes, run unit or feature tests, verify backend/frontend code, execute PHPStan/Rector/TypeScript/ESLint/Knip, investigate a failing check, or prepare verification before handoff or MR.
---

# Test with Taskfile

Run project checks through `task` from the repository root. Do not invoke `php artisan test`, PHPUnit, Vitest, npm/npx validation commands, PHPStan or Rector directly when an equivalent target exists in `Taskfile.yml`.

Before running a command, inspect `Taskfile.yml` if the target or its argument contract might have changed.

## Select the smallest useful check

| Need | Command |
|---|---|
| Backend Unit suite | `task test:unit` |
| One backend class | `task test:unit -- --filter=ScenarioFieldPresetServiceTest` |
| One backend method | `task test:unit -- --filter=ClassName::test_method_name` |
| Backend Feature suite | `task test:feature` |
| Full backend suite | `task test` |
| All frontend unit tests | `task test:frontend` |
| Selected frontend tests | `task test:frontend -- resources/js/modules/foo/__tests__/foo.test.ts` |
| PHPStan, configured project | `task phpstan` |
| PHPStan, selected paths | `task phpstan -- module/Scenario app/Http` |
| Preview PHP refactoring | `task rector:check -- module/Scenario tests/Unit/Module/Scenario` |
| Apply PHP refactoring | `task rector -- module/Scenario tests/Unit/Module/Scenario` |
| Vue/TypeScript types | `task typecheck` |
| ESLint | `task lint` |
| ESLint on selected paths | `task lint -- resources/js/modules/scenario` |
| ESLint autofix | `task lint:fix -- resources/js/modules/scenario` |
| Unused frontend code | `task knip` |
| Frontend production build | `task build:frontend` |
| Complete frontend verification | `task check:frontend` |

Pass tool arguments only after `--`; Task exposes them as `.CLI_ARGS`.

## Verification workflow

1. Start with tests nearest to the changed behavior.
2. Run the relevant static analysis for the touched layer.
3. Run the broader unit suite when the change affects shared infrastructure, serialization, validation, stores or repositories.
4. Run a production frontend build after UI, bundling, routing or dependency changes.
5. Run full backend tests only when requested, before MR, or when the impact is genuinely cross-cutting.

Respect explicit scope such as “only unit”: use `test:unit` and `test:frontend`; do not add or run Feature/E2E tests.

## Failure handling

- Treat a non-zero exit as a failed check even when most tests pass.
- Separate defects introduced by the change from environment failures such as unavailable Docker services or extensions.
- Do not hide, delete or weaken failing tests.
- Fix in-scope defects and rerun the exact failed task before broadening the run.
- If a task target is missing or incorrect, update `Taskfile.yml`; do not bypass it with a direct command.
- Do not start containers automatically unless the requested verification requires it. Report that `task up` is needed when services are unavailable.

## Handoff

Report the exact `task` commands, passed/failed counts, and any blockers. Keep unrelated pre-existing or environment failures distinct from the result of targeted checks.
