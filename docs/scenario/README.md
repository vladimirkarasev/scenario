# Scenario Module

## Назначение

Модуль `Scenario` отвечает за хранение сценариев, версий сценариев, графа узлов, запуск сценария и прохождение пользователя по этому графу.

Основная модель работы:

1. Пользователь или редактор создает сценарий.
2. Сценарий сохраняется как версия с graph snapshot.
3. При запуске создается `ScenarioRun`.
4. `ScenarioPlayerService` двигает run по узлам графа.
5. Интерактивные узлы ждут пользовательский ввод.
6. Каждый вход и выход из узла пишется в `ScenarioRunStep`.

## Структура Модуля

```text
module/Scenario
- DTO
- Enums
- Http
- Models
- Providers
- Services
- routes/api.php
```

Ключевые директории:

- `Models` - Eloquent-модели сценариев, версий, запусков и шагов.
- `DTO` - входные структуры для сервисов и контроллеров.
- `Http/Controllers` - API-контроллеры модуля.
- `Http/Requests` - валидация входящих запросов.
- `Services` - основная бизнес-логика.
- `Services/Nodes` - обработчики конкретных типов узлов.
- `Enums` - типы узлов и статусы выполнения.

## Основные Модели

`Scenario`

Хранит сам сценарий: название, описание, активность и связь с версиями.

`ScenarioVersion`

Хранит snapshot конкретной версии сценария. Подробно: [versions.md](versions.md).

`ScenarioRun`

Хранит состояние конкретного запуска: сценарий, версию, текущий узел, runtime context и статус.

`ScenarioRunStep`

Хранит историю прохождения узлов. Подробно: [history.md](history.md).

## Основной Runtime Flow

Создание запуска:

1. `ScenarioRunController` принимает запрос.
2. Данные собираются в `ScenarioRunData`.
3. `ScenarioPlayerService::createRun()` находит сценарий и версию.
4. `ScenarioGraphResolver::findStartNode()` находит стартовый узел.
5. Создается `ScenarioRun`.
6. `ScenarioPlayerService::progress()` двигает run до первого интерактивного узла.

Продолжение запуска:

1. Frontend отправляет input в `POST /api/scenario-runs/{run}/continue`.
2. `ScenarioPlayerService::continue()` находит текущий узел.
3. Обработчик узла решает, куда идти дальше.
4. `ScenarioRunStepManager` закрывает текущий шаг.
5. Контекст обновляется.
6. Run переходит к следующему узлу.

## Runtime Context

`ScenarioRun.context` - рабочее состояние запуска.

Туда попадают:

- стартовый context из запроса;
- input из block-узлов;
- служебное состояние `_player`;
- любые значения, которые будут нужны условиям и шаблонам.

Пример:

```json
{
  "user": {
    "id": 42,
    "name": "Vladimir"
  },
  "email": "user@example.test",
  "_player": {
    "total_steps": 3,
    "visited": {
      "node_start": 1,
      "node_block": 1
    }
  }
}
```

## API

Основные endpoints описаны в `module/Scenario/routes/api.php`.

Запуски сценариев:

```text
POST /api/scenario-runs
GET /api/scenario-runs/{runId}
POST /api/scenario-runs/{runId}/continue
POST /api/scenario-runs/{runId}/jump
```

Каталог, сценарии, категории и версии обслуживаются отдельными контроллерами:

- `CatalogController`
- `CategoryController`
- `ScenarioController`
- `ScenarioVersionController`
- `ScenarioRunController`

## Документы

- [nodes.md](nodes.md) - как работают узлы, handlers, transitions, условия и выражения.
- [history.md](history.md) - как пишется история прохождения узлов.
- [versions.md](versions.md) - как устроены версии сценариев и revisions.
- [../scenario-player.md](../scenario-player.md) - старый краткий документ по player flow.
- [../knowledge-base-example.md](../knowledge-base-example.md) - пример базы знаний для AI.

## Правила Разработки

- Не доверять frontend при выборе следующего узла.
- Все переходы проверять на backend.
- Не хранить runtime-only данные в `ScenarioVersion`.
- Не менять старые версии сценариев задним числом без ревизии.
- Новые runtime-поля класть в `ScenarioRun.context`.
- Историю действий писать через `ScenarioRunStep`.
- Для условий использовать `App\Services\Expression\ExpressionService` через `VariableResolver`, а не `eval`.
- Для нового поведения узла добавлять handler, а не расширять `ScenarioPlayerService` большим `if`.

## Тестирование

Основные тесты:

```text
tests/Feature/ScenarioPlayerTest.php
tests/Unit/Module/Scenario/Services/VariableResolverTest.php
```
