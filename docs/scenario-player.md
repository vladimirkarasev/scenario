# Scenario Player

## Архитектура

- `ScenarioVersion` хранит snapshot VueFlow-графа в `nodes_json` и `edges_json`.
- `ScenarioRun` хранит текущее состояние исполнения, runtime context и статус.
- `ScenarioRunStep` пишет историю входа/выхода по узлам.
- `ScenarioPlayerService` управляет переходами и не доверяет frontend.
- `ScenarioGraphResolver` валидирует snapshot и находит узлы/переходы.
- `VariableResolver` подставляет runtime значения в строки вида `{{ user.name }}` и `${user.name}`.
- `ConditionEvaluator` вычисляет auto-conditions.

## Структура файлов

- Backend:
  - `module/Scenario/Enums`
  - `module/Scenario/Models`
  - `module/Scenario/DTO`
  - `module/Scenario/Services`
  - `module/Scenario/Http/Controllers/ScenarioRunController.php`
  - `module/Scenario/routes/api.php`
- Frontend:
  - `resources/js/Pages/ScenarioPlayer.vue`
  - `resources/js/components/ScenarioPlayer.vue`
  - `resources/js/components/BlockRenderer.vue`
  - `resources/js/components/ConditionRenderer.vue`
  - `resources/js/components/GutenbergBlockRenderer.vue`
  - `resources/js/composables/useScenarioPlayer.ts`
  - `resources/js/lib/scenario-player-types.ts`
  - `resources/js/lib/scenario-player-variables.ts`

## Пример snapshot graph JSON

```json
{
  "schema_version": 1,
  "nodes": [
    {
      "id": "node_start",
      "type": "start",
      "data": {
        "title": "Start"
      }
    },
    {
      "id": "node_welcome",
      "type": "block",
      "data": {
        "title": "Welcome",
        "blocks": [
          {
            "id": "block_heading",
            "type": "heading",
            "props": {
              "text": "Hello, {{ user.name }}"
            },
            "children": []
          }
        ]
      }
    },
    {
      "id": "node_condition",
      "type": "condition",
      "data": {
        "mode": "manual",
        "question": "Что сделать дальше?",
        "options": [
          {
            "label": "Продолжить",
            "targetNodeId": "node_end"
          }
        ]
      }
    },
    {
      "id": "node_end",
      "type": "end",
      "data": {
        "title": "Done",
        "blocks": [
          {
            "id": "block_done",
            "type": "paragraph",
            "props": {
              "text": "Scenario completed"
            },
            "children": []
          }
        ]
      }
    }
  ],
  "edges": [
    {
      "id": "edge_1",
      "source": "node_start",
      "target": "node_welcome",
      "sourceHandle": null,
      "targetHandle": null
    },
    {
      "id": "edge_2",
      "source": "node_welcome",
      "target": "node_condition",
      "sourceHandle": null,
      "targetHandle": null
    }
  ]
}
```

## Подключение к Inertia

- Маршрут страницы: `/dashboard/scenarios/{scenario}/play`
- Controller: `DashboardController::playScenario()`
- Page: `resources/js/Pages/ScenarioPlayer.vue`
- Player сам создаёт run через `POST /api/scenario-runs` и дальше ходит в:
  - `GET /api/scenario-runs/{run}`
  - `POST /api/scenario-runs/{run}/continue`
  - `POST /api/scenario-runs/{run}/jump`
