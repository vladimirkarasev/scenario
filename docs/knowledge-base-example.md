# Knowledge Base Example

## Назначение

Этот документ показывает пример базы знаний для AI-помощника на основе текущего проекта сценариев.

Идея: хранить документацию проекта как документы, резать ее на небольшие чанки, искать релевантные чанки по вопросу пользователя и передавать найденный контекст в AI.

## Пример исходного документа

```json
{
  "id": "scenario-player",
  "title": "Scenario Player",
  "source_type": "docs",
  "source_path": "docs/scenario-player.md",
  "content": "ScenarioVersion хранит snapshot VueFlow-графа в nodes_json и edges_json. ScenarioRun хранит текущее состояние исполнения, runtime context и статус. ScenarioRunStep пишет историю входа и выхода по узлам. ScenarioPlayerService управляет переходами и не доверяет frontend. ScenarioGraphResolver валидирует snapshot и находит узлы и переходы. VariableResolver подставляет runtime значения в строки. ConditionEvaluator вычисляет auto-conditions.",
  "metadata": {
    "module": "Scenario",
    "domain": "scenario-player",
    "language": "ru"
  }
}
```

## Пример чанков

```json
[
  {
    "id": "scenario-player:chunk:001",
    "document_id": "scenario-player",
    "position": 1,
    "content": "ScenarioVersion хранит snapshot VueFlow-графа в nodes_json и edges_json. Snapshot состоит из nodes и edges.",
    "metadata": {
      "section": "Архитектура",
      "module": "Scenario"
    }
  },
  {
    "id": "scenario-player:chunk:002",
    "document_id": "scenario-player",
    "position": 2,
    "content": "ScenarioRun хранит текущее состояние исполнения, runtime context и статус. ScenarioRunStep пишет историю входа и выхода по узлам.",
    "metadata": {
      "section": "Архитектура",
      "module": "Scenario"
    }
  },
  {
    "id": "scenario-player:chunk:003",
    "document_id": "scenario-player",
    "position": 3,
    "content": "App\\Services\\Expression\\ExpressionService вычисляет выражения через Symfony ExpressionLanguage. Для шаблонов поддерживаются форматы {{ expression }} и ${expression}. Формат [expression] не используется.",
    "metadata": {
      "section": "Переменные и выражения",
      "module": "Scenario"
    }
  }
]
```

## Минимальная схема хранения

```text
knowledge_documents
- id
- title
- source_type
- source_path
- content
- metadata json
- created_at
- updated_at

knowledge_chunks
- id
- document_id
- position
- content
- embedding
- metadata json
- created_at
- updated_at
```

Если vector search еще не подключен, поле `embedding` можно временно не использовать и начать с обычного полнотекстового поиска. Контракт сервиса при этом лучше сразу сделать таким, чтобы потом заменить реализацию поиска без переписывания AI-части.

## Пример вопроса

Вопрос пользователя:

```text
Где хранится состояние выполнения сценария?
```

Найденный контекст:

```text
ScenarioRun хранит текущее состояние исполнения, runtime context и статус.
ScenarioRunStep пишет историю входа и выхода по узлам.
```

Ответ AI:

```text
Состояние выполнения сценария хранится в ScenarioRun: там лежат текущий узел, runtime context и статус запуска. История прохождения узлов пишется отдельно в ScenarioRunStep.
```

## Пример prompt для AI

```text
Ты отвечаешь на вопросы по проекту scenario.
Используй только переданный контекст. Если контекста недостаточно, скажи, какой информации не хватает.

Контекст:
{{ retrieved_context }}

Вопрос:
{{ user_question }}
```

## Пример PHP-контракта

```php
<?php

declare(strict_types=1);

namespace Module\Knowledge\Services;

final readonly class KnowledgeSearchResult
{
    public function __construct(
        public string $chunkId,
        public string $documentId,
        public string $content,
        public float $score,
        public array $metadata = [],
    ) {}
}
```

```php
<?php

declare(strict_types=1);

namespace Module\Knowledge\Services;

interface KnowledgeSearchService
{
    /**
     * @return array<int, KnowledgeSearchResult>
     */
    public function search(string $question, int $limit = 5): array;
}
```

## Пример сервиса сборки контекста

```php
<?php

declare(strict_types=1);

namespace Module\Knowledge\Services;

final readonly class KnowledgeContextBuilder
{
    public function __construct(
        private KnowledgeSearchService $searchService,
    ) {}

    public function build(string $question, int $limit = 5): string
    {
        $chunks = $this->searchService->search($question, $limit);

        return collect($chunks)
            ->map(fn (KnowledgeSearchResult $result): string => $result->content)
            ->implode("\n\n");
    }
}
```

## Как применить к текущим docs

1. Загружать каждый markdown-файл из `docs` как отдельный `knowledge_document`.
2. Резать документ по заголовкам `#`, `##`, `###`.
3. Большие секции дополнительно делить на чанки по 800-1500 токенов.
4. В `metadata` сохранять `source_path`, `section`, `module`, `language`.
5. При вопросе искать 3-7 самых релевантных чанков.
6. Передавать найденный текст в AI как `retrieved_context`.

## Что важно для качества

- Не смешивать разные темы в одном чанке.
- Сохранять путь к источнику, чтобы потом показывать ссылку на документ.
- Обновлять чанки при изменении исходного документа.
- Хранить оригинальный документ отдельно от чанков.
- Не давать AI отвечать без найденного контекста, если нужен точный ответ по проекту.
