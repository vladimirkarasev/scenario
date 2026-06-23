# Scenario Nodes

## Назначение

Узел описывает один шаг сценария. В graph snapshot узлы лежат в `nodes`, а связи между ними - в `edges`.

Минимальный узел:

```json
{
  "id": "node_block",
  "type": "block",
  "data": {
    "title": "Hello"
  }
}
```

Минимальная связь:

```json
{
  "id": "edge_start_block",
  "source": "node_start",
  "target": "node_block",
  "sourceHandle": null,
  "targetHandle": null
}
```

## Поддерживаемые Типы

Типы определены в `Module\Scenario\Enums\ScenarioNodeType`:

```text
start
block
condition
end
scenario_link
```

`ScenarioGraphResolver` валидирует, что каждый node имеет поддерживаемый `type`, а `source` и `target` каждого edge
указывают на существующие узлы.

## Node Handlers

Поведение узлов вынесено в handlers:

- `BlockNodeHandler` - интерактивный экран с блоками и input-полями.
- `ConditionNodeHandler` - ручное или автоматическое ветвление.
- `EndNodeHandler` - завершение сценария.
- `ScenarioLinkNodeHandler` - переход в другой сценарий.
- `DefaultNodeHandler` - fallback-обработчик.

Выбор обработчика происходит через `NodeHandlerRegistry`.

Контракт:

```php
interface NodeHandlerInterface
{
    public function isInteractive(array $node): bool;

    public function advance(ScenarioRun $run, array $node): NodeAdvanceResult;

    public function continueFrom(ScenarioRun $run, array $node, ScenarioRunContinueData $data): ?string;

    public function render(ScenarioVersion $version, array $node, array $context): array;
}
```

## Interactive И Auto

Интерактивный узел:

- возвращает `true` из `isInteractive()`;
- открывает `ScenarioRunStep`;
- рендерится в payload;
- ждет пользовательский ввод через `continue`.

Автоматический узел:

- возвращает `false` из `isInteractive()`;
- обрабатывается внутри `ScenarioPlayerService::progress()`;
- сразу возвращает следующий node id через `advance()`;
- может мутировать run и вернуть `NodeAdvanceResult::mutated()`.

## Block Node

`block` - основной интерактивный узел.

При render:

- возвращает `type = block`;
- подставляет переменные в `title`;
- подставляет переменные в `blocks`.

При continue:

- input пользователя мержится в `ScenarioRun.context`;
- следующий узел берется через `ScenarioGraphResolver::defaultNextNodeId()`.

Пример:

```json
{
  "id": "node_welcome",
  "type": "block",
  "data": {
    "title": "Hello, {{ user.name }}",
    "blocks": [
      {
        "id": "field_email",
        "type": "input",
        "props": {
          "name": "email",
          "label": "Email"
        },
        "children": []
      }
    ]
  }
}
```

## Condition Node

`condition` поддерживает два режима.

Manual mode:

- frontend показывает варианты;
- пользователь выбирает `selectedTargetNodeId`;
- backend проверяет, что target есть среди разрешенных options или branches;
- run переходит в выбранный узел.

Auto mode:

- backend вычисляет `expression`;
- сравнивает результат с `rules`;
- выбирает первый совпавший `targetNodeId`;
- если совпадений нет, использует `fallbackTargetNodeId`.

Пример auto-condition:

```json
{
  "id": "node_condition",
  "type": "condition",
  "data": {
    "mode": "auto",
    "expression": "user.id",
    "rules": [
      {
        "operator": "exists",
        "value": null,
        "targetNodeId": "node_member"
      }
    ],
    "fallbackTargetNodeId": "node_guest"
  }
}
```

Поддерживаемые операторы сейчас находятся в `ConditionEvaluator::matches()`:

```text
exists
empty
equals
not_equals
in
not_in
```

## End Node

`end` завершает запуск.

Особенность текущей реализации: `EndNodeHandler` считается интерактивным, чтобы финальный экран мог быть отрендерен в
payload. Когда progress доходит до `end`, `ScenarioPlayerService` переводит run в статус `completed`.

## Scenario Link Node

`scenario_link` переключает run на другой сценарий.

Этот handler может мутировать run:

- изменить `scenario_id`;
- изменить `scenario_version_id`;
- поставить `current_node_id` на стартовый узел связанного сценария.

После такой мутации `advance()` возвращает `NodeAdvanceResult::mutated()`, а player заново гидратит run.

## Выражения И Переменные

За выражения отвечает общий `App\Services\Expression\ExpressionService`; сценарный `VariableResolver` только
подготавливает контекст переменных.

Поддерживаемые шаблонные форматы:

```text
{{ user.name }}
${user.name}
```

Для условий выражение можно хранить без обертки:

```text
user.id
node.count + 1
fields["createdAt"] + day
```

Формат `[user.name]` не используется.

Контекст нормализуется через `ExpressionValue`, поэтому для PHP-массивов работает dot-доступ:

```text
user.name
node.count
```

Bracket-доступ ExpressionLanguage тоже возможен:

```text
fields["createdAt"]
```

## Как Добавлять Новый Тип Узла

1. Добавить новый тип в `ScenarioNodeType`.
2. Создать handler в `module/Scenario/Services/Nodes`.
3. Реализовать `NodeHandlerInterface`.
4. Зарегистрировать handler в `NodeHandlerRegistry`.
5. Обновить frontend-renderer, если узел отображается пользователю.
6. Добавить feature-тест на прохождение сценария через новый узел.

Минимальный handler:

```php
<?php

declare(strict_types=1);

namespace Module\Scenario\Services\Nodes;

use Module\Scenario\DTO\ScenarioRunContinueData;
use Module\Scenario\Models\ScenarioRun;
use Module\Scenario\Models\ScenarioVersion;

final class ExampleNodeHandler implements NodeHandlerInterface
{
    public function isInteractive(array $node): bool
    {
        return false;
    }

    public function advance(ScenarioRun $run, array $node): NodeAdvanceResult
    {
        return NodeAdvanceResult::next(null);
    }

    public function continueFrom(ScenarioRun $run, array $node, ScenarioRunContinueData $data): ?string
    {
        return null;
    }

    public function render(ScenarioVersion $version, array $node, array $context): array
    {
        return [
            'type' => $node['type'],
            'data' => $node['data'] ?? [],
        ];
    }
}
```
