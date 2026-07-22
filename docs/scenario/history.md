# Scenario History

## Назначение

`ScenarioRunStep` — это таблица движка выполнения (стек шагов для `jump`/rollback, call
stack для связанных сценариев), а не история для отображения оператору. Отображаемая
история (таймлайн переходов, заполнения полей, выбора условий, результатов action,
переходов по связанным сценариям, старта/завершения прогона) хранится отдельно, в
`scenario_run_history_events` — см. раздел [«Отображаемая история»](#отображаемая-история-scenario_run_history_events)
ниже.

`ScenarioRunStep` нужен для:

- восстановления input/output по узлам во время выполнения;
- `jump`/rollback (мягкая отмена шагов через `cancelled_at`);
- call stack для возврата из связанных сценариев;
- будущей логики defaults для полей.

## Где Хранится

Таблица:

```text
scenario_run_steps
```

Основные поля:

```text
id
run_id
node_id
node_type
input
output
entered_at
exited_at
created_at
updated_at
```

Модель:

```text
Module\Scenario\Models\ScenarioRunStep
```

Менеджер:

```text
Module\Scenario\Services\ScenarioRunStepManager
```

## Lifecycle Шага

Типичный flow интерактивного узла:

1. `ScenarioPlayerService::progress()` доходит до интерактивного узла.
2. `ScenarioRunStepManager::ensureOpen()` создает открытый step.
3. Пользователь отправляет input.
4. `ScenarioPlayerService::transition()` вызывает `closeOpen()`.
5. `closeOpen()` записывает input, output и `exited_at`.
6. Run переходит дальше.

Открытый step - это запись, у которой `exited_at = null`.

## ensureOpen

`ensureOpen()` создает step только если для текущего run и node еще нет открытой записи.

Это защищает от дублей, когда player несколько раз рендерит один и тот же интерактивный узел.

Записываются:

```text
node_id
node_type
entered_at
```

## closeOpen

`closeOpen()` закрывает открытый step текущего узла.

Записываются:

```text
input
output
exited_at
```

Если открытый step не найден, менеджер создает его на месте и сразу закрывает. Это fallback для случаев, когда
transition пришел без предварительного `ensureOpen()`.

## createAuto

`createAuto()` предназначен для автоматических узлов, но сейчас в основном runtime flow не вызывается.

Его можно использовать, если для auto-узлов нужно писать отдельную историю. В таком случае step создается сразу
закрытым:

```text
entered_at = now()
exited_at = now()
output = ...
```

## Что Писать В input

`input` - данные, которые пришли от пользователя при продолжении узла.

Пример:

```json
{
  "email": "user@example.test",
  "name": "Vladimir"
}
```

Для block-узлов эти данные также мержатся в `ScenarioRun.context`.

## Что Писать В output

`output` - системный результат обработки узла.

Примеры:

```json
{
  "next_node_id": "node_next"
}
```

```json
{
  "completed": true
}
```

Для условий output можно расширить выбранной веткой или результатом expression, если понадобится аудит принятия решений.

## История Полей И Defaults

Если в узлах появятся поля формы, их лучше сохранять с контекстом узла.

Хороший формат:

```json
{
  "fieldsByNode": {
    "node_client": {
      "phone": "+79990000000",
      "name": "Иван"
    },
    "node_manager": {
      "phone": "+78880000000"
    }
  }
}
```

Для истории конкретного прохождения это можно хранить в `ScenarioRunStep.input`.

Для defaults при следующем открытии формы:

1. найти последнюю релевантную запись истории;
2. взять `input` по `node_id`;
3. подставить значения в поля этого узла.

Важно не хранить одинаковые поля только по имени `phone`, потому что в разных узлах они могут означать разные вещи.
Минимальный ключ - `node_id + field_key`.

## Правила

- Не перезаписывать историю задним числом без отдельной причины.
- Не смешивать input пользователя и системный output.
- Для повторяющихся полей всегда учитывать `node_id`.
- Runtime context хранить в `ScenarioRun.context`, а служебное состояние прохождения - в `ScenarioRunStep`.
- Если нужно логировать auto-узлы, использовать `createAuto()` или эквивалентную закрытую запись.

## Отображаемая история (`scenario_run_history_events`)

Таблица `scenario_run_history_events` — append-only лог для UI (`RunHistory.vue`,
`GET /api/scenarios/runner/{id}/history`). В отличие от `scenario_run_steps`, строки в ней
никогда не обновляются и не отменяются: `jump` продолжает писать новые события, старые
остаются как есть (при отображении "отменённость" резолвится через связанный
`scenario_run_steps.cancelled_at`, если событие с ним связано).

Модель: `Module\Scenario\Models\ScenarioRunHistoryEvent`.
Типы событий: `Module\Scenario\Enums\ScenarioRunHistoryEventType` (`transition`,
`field_filled`, `field_changed`, `condition_evaluated`, `action_completed`, `action_failed`,
`scenario_link_followed`, `run_started`, `run_completed`, `run_failed`).

Запись идёт через доменные события Laravel (Event/Listener), а не напрямую из
`ScenarioPlayerService`/хендлеров — это позволяет добавлять новые типы событий, не трогая
существующий код:

- `ScenarioRunStarted`/`ScenarioRunCompleted`/`ScenarioRunFailed` — `ScenarioPlayerService`;
- `ScenarioNodeEntered`/`ScenarioNodeExited` — `ScenarioRunStepManager` (ensureOpen/closeOpen);
  диффинг полей для `field_filled`/`field_changed` делает листенер, сравнивая с последним
  сохранённым значением поля в самой таблице истории;
- `ScenarioConditionEvaluated` — `ConditionNodeHandler` (auto и manual режимы);
- `ScenarioLinkFollowed` — `ScenarioLinkNodeHandler`;
- `Module\Actions\Events\ScenarioActionStageFinished` — `ExecuteActionActivity` (Actions
  module), когда экшен запущен из Action-ноды сценария (`scenarioRunId`/`actionNodeId`
  непустые). Слушает его Scenario module, а не наоборот — Actions ничего не знает о
  таблице истории Scenario.

Все слушатели собраны в `Module\Scenario\Listeners\RecordScenarioRunHistoryEvent` и
зарегистрированы явно в `ScenarioServiceProvider::boot()` (event auto-discovery в проекте
выключена).

`ScenarioRunHistoryService::buildHistory()` читает таблицу как есть, без пересчёта на
лету, и резолвит `node_title` через `ScenarioGraphResolver` по сохранённым
`scenario_version_id`/`node_id` (как и раньше).
