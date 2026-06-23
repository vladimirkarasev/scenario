---
name: scenario-variables
description: Scenario variable system uses FLAT names ({{ varName }}, not {{ Block.varName }}) with last-wins conflict resolution. Value transformations go through ExpressionLanguage functions (dateFormat, addTime, upper, …), NOT proxy accessors like .year/.format:.... Apply when working with scenario expressions, variable rendering, or VariableEntry DTOs.
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
