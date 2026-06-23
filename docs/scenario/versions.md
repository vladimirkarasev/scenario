# Scenario Versions

## Назначение

Версия сценария фиксирует graph snapshot на момент сохранения.

Это нужно, чтобы уже запущенные сценарии не ломались, когда редактор меняет текущий граф.

Главная идея: `Scenario` - это сущность сценария, а `ScenarioVersion` - конкретная версия его графа.

## Где Хранится

Таблица:

```text
scenario_versions
```

Модель:

```text
Module\Scenario\Models\ScenarioVersion
```

Основные поля:

```text
id
scenario_id
schema_json
nodes_json
edges_json
schema_version
created_at
```

`id` версии - UUID.

## Snapshot

Версия хранит graph snapshot:

```json
{
  "schema_version": 1,
  "nodes": [],
  "edges": []
}
```

В текущей модели есть три поля:

- `schema_json` - полный snapshot;
- `nodes_json` - нормализованный список узлов;
- `edges_json` - нормализованный список связей.

`ScenarioGraphResolver::snapshot()` сначала берет `nodes_json` и `edges_json`. Если они пустые, он fallback-ом берет `schema_json.nodes` и `schema_json.edges`.

## Revisions

Помимо самой версии есть таблица:

```text
scenario_version_revisions
```

Модель:

```text
Module\Scenario\Models\ScenarioVersionRevision
```

Revision создается при:

- создании версии;
- обновлении версии.

За это отвечает `ScenarioVersionService::storeRevision()`.

Revision хранит копию:

```text
scenario_version_id
schema_json
nodes_json
edges_json
schema_version
created_at
```

## Зачем Нужны Revisions

`ScenarioVersion` - текущая редактируемая запись версии.

`ScenarioVersionRevision` - история сохранений этой версии.

Это полезно для:

- аудита изменений графа;
- отката к предыдущему состоянию;
- сравнения изменений;
- восстановления после ошибки редактора;
- понимания, какой snapshot был сохранен в конкретный момент.

## Создание Версии

Flow:

1. Controller принимает request.
2. Данные собираются в `ScenarioVersionData`.
3. `ScenarioVersionService::create()` проверяет право управления каталогом.
4. Создается `ScenarioVersion`.
5. Сразу создается `ScenarioVersionRevision`.
6. Возвращается payload версии для каталога.

## Обновление Версии

Flow:

1. Controller принимает request.
2. Данные собираются в `ScenarioVersionData`.
3. `ScenarioVersionService::update()` проверяет право управления каталогом.
4. `ScenarioVersion` обновляется новыми graph-полями.
5. Создается новая `ScenarioVersionRevision`.
6. Возвращается обновленный payload.

## Удаление Версии

`ScenarioVersionService::delete()` удаляет версию после проверки доступа.

Связанные revisions удаляются каскадом через foreign key.

## Правила Работы С Версиями

- Runtime state не должен попадать в `ScenarioVersion`.
- В `ScenarioVersion` хранится только graph snapshot и schema metadata.
- Если меняется структура графа, нужно обновлять `schema_version`.
- Если версия обновлена, нужно создавать revision.
- Run должен ссылаться на конкретный `scenario_version_id`.
- Старые runs должны продолжать работать на своей версии.

## Версионирование Schema

`schema_version` нужен для миграции формата graph snapshot.

Пример будущего изменения:

```text
schema_version = 1
nodes: [{ id, type, data }]
edges: [{ id, source, target }]

schema_version = 2
nodes: [{ id, type, position, data }]
edges: [{ id, source, target, sourceHandle, targetHandle, data }]
```

Если формат меняется, лучше добавить отдельный migrator:

```text
old snapshot -> normalized snapshot -> current snapshot
```

Так старый формат не будет расползаться по runtime-коду.

## Graph Resolver

`ScenarioGraphResolver` отвечает за чтение версии:

- достает snapshot;
- валидирует nodes и edges;
- ищет стартовый узел;
- ищет узел по id;
- возвращает outgoing edges;
- находит default next node.

Если graph поврежден, resolver должен упасть до начала неправильного выполнения сценария.
