---
name: scenario-variables
description: Scenario variables use flat user names plus DI-registered system-variable providers per ScenarioType. Transform values through ExpressionLanguage functions, not proxy accessors. Apply when working with expressions, variable rendering, VariableEntry DTOs, system-variable groups or providers such as WhatsappSystemVariableProvider.
---

# Scenario Variables — flat names + ExpressionLanguage functions

## Плоские имена

Переменные блоков **плоские**: `{{ varName }}`. Не `{{ BlockTitle.varName }}`.

**Принцип «последний побеждает»**: если два блока имеют поле с одинаковым `varName`, побеждает блок, идущий позже в схеме. Уникальность — ответственность оператора.

## Функции ExpressionLanguage (вместо прокси-аксессоров)

Прежний синтаксис `varName.year`, `.month`, `.iso`, `.ts`, `.format:...`, `.keys`, `.list` **удалён**. Всё теперь — функции:

| Старый аксессор | Новая функция |
|---|---|
| `Дата.format:"DD.MM.YYYY"` | `dateFormat(Дата, "DD.MM.YYYY HH:mm")` |
| `Дата.year` | через `dateFormat(Дата, "YYYY")` |
| `Дата + 1h` | `addTime(Дата, "1h2m")` |
| Строки | `upper / lower / trim / length / concat / replace / implode` |

**Если придётся реанимировать `.year/.iso/.format:...`** — сначала проверить БД на использование старого синтаксиса в `nodes_json` / `schema_json`. Не возвращать без миграции.

## Select-поля

`{{ Категория }}` для select-поля возвращает **label** (не код опции). Это делает `ScenarioExpressionService::resolveFlatValue` через `selectLabels` при инъекции переменных в контекст.

## Рендер подписей через API

Frontend не интерпретирует ExpressionLanguage самостоятельно. Подписи select/suggest и chips рендерятся через repository:

- `POST /api/expression/render` — один шаблон и один `context`;
- `POST /api/expression/render-batch` — один шаблон для списка контекстов с уникальными `id`;
- batch возвращает объект `data`, где ключ — строковое представление `id`, значение — готовая подпись;
- исходная строка доступна выражению как через плоские поля, так и через `item`, например `{{ item["city-name"] }}`;
- отсутствующие в отдельных строках поля нормализуются по общей форме batch;
- `??` сохраняет стандартную null-семантику ExpressionLanguage; для первой непустой строки используется `?:`.

```json
{
  "item": {"template": "{{ region ?: city ?: category }}"},
  "context": [
    {"id": 1, "data": {"region": "", "city": "Москва"}},
    {"id": 2, "data": {"category": "СПБ"}}
  ]
}
```

```json
{"data": {"1": "Москва", "2": "СПБ"}}
```

## PHP DTO

`module/Scenario/DTO/Variables/`:

| Класс | Для каких типов полей |
|---|---|
| `VariableEntryInterface` | контракт: `toArray()` / `fromArray()` |
| `SimpleVariableEntry` | input, email, phone, textarea, number, checkbox, hidden |
| `SelectVariableEntry` | select — хранит `list<array<mixed>> $options` |
| `DateVariableEntry` | date / datetime — хранит `string $format` (moment-формат) |
| `DirectoryVariableEntry` | directory_list, directory_table |
| `VariableEntryFactory` | создаёт нужный DTO по `_field_type` |

**Хранение**: `_variable_map` в `ScenarioRun.context` как `array<string, array<string, mixed>>` (JSON). При чтении `VariableEntryFactory::fromArray()` восстанавливает DTO.

## TypeScript

`resources/js/modules/scenario/types/scenario-variable-entry.ts`:

- `MainVariableEntry` — `isAccessor: false`, `fieldType: BlockFieldType`.
- `AccessorVariableEntry` — типы остались, но фронту вероятно нужен апдейт под функции вместо аксессоров (TODO когда понадобится UI-помощник для подбора функций).

## Системные переменные по типу сценария

Актуальная архитектура находится в `module/Scenario/Services/Variables/System/`:

- `ScenarioSystemVariableProvider` — Strategy с `supports(ScenarioType)` и `groups()`;
- `ScenarioSystemVariableRegistry` — DI-реестр выбора Strategy;
- `ScenarioSystemVariableCatalog` — объединяет common groups и provider выбранного типа;
- `SystemVariableGroup`/`SystemVariableField` — readonly value objects ответа;
- `WhatsappSystemVariableProvider.php` — provider для существующего `ScenarioType::Watsapp` (сохранять текущее spelling до отдельной миграции enum/API/данных).

При добавлении системных переменных существующему типу изменять только его provider: добавить `SystemVariableGroup` и поля с реальными suffix из runtime context. Если нужен новый context root, добавить case в `ScenarioContextKey` и label; имя обязано начинаться с `_` и совпадать с ключом, который фактически строит runtime payload.

При добавлении нового типа сценария:

1. Создать `XxxSystemVariableProvider implements ScenarioSystemVariableProvider`.
2. Зарегистрировать provider constructor DI в `ScenarioSystemVariableRegistry` и вернуть его из `providers()`.
3. Не использовать `app()`, `resolve()` и статический service locator; providers без состояния остаются `final readonly`.
4. Обновить API Feature-тест каталога и frontend `ScenarioType` contract, если тип новый.

Common groups (`_run`, `_operator`, `_project`) изменять в `CommonSystemVariableProvider`, а не копировать в WhatsApp/Telegram/Calls. Provider может делегировать другому provider через DI, как `CallBotsSystemVariableProvider`, только если контракты реально совпадают.

Обязательно проверить, что каждый advertised ref (`{{ _group.suffix }}`) присутствует в runtime context либо явно допускает отсутствие. Добавить тест каталога и тест разрешения значения в payload/expression. Frontend получает каталог через `scenarioSystemVariableRepository` → `useScenarioSystemVariables`; не хардкодить список подсказок в component.

## Где живёт логика

| Файл | Что делает |
|---|---|
| `ScenarioPlayerService::buildVariableMap()` | строит flat `_variable_map` при старте прогона |
| `ScenarioExpressionService::evaluate()` | инъекция переменных в контекст + вызов ExpressionLanguage |
| `ScenarioExpressionService::injectFlatVariables()` | flat-инъекция `_variable_map → context[varName]` с label-резолвом для select |
| `ScenarioBlockEditorDrawer.vue::allVariables` | computed с типом `VariableEntry[]` |

## Связанные

- [[add-expression-function]] — как добавить новую функцию для `{{ ... }}`.
- [[add-new-block-field]] — добавление нового типа поля → новый `VariableEntry` подкласс.
