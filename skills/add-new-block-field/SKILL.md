---
name: add-new-block-field
description: Add a new scenario block field type or palette group across the current Vue editor/player, PHP field Strategy/Factory, validation, presets, variables and tests. Use when asked to add an input, display element, map/directory field or field category to the block editor.
---

# Add New Block Field

Поле является сквозным контрактом. До изменений найти ближайший существующий тип с той же семантикой и обновить только применимые слои. Не копировать старую монолитную реализацию `BlockNodeHandler::renderField()` и не использовать удалённый `NodeHelpers`.

## Карта актуальных границ

| Слой | Основные файлы |
|---|---|
| TypeScript contract/defaults/normalization | `resources/js/modules/scenario/lib/scenario-block-fields.ts` |
| Settings и palette | `resources/js/modules/scenario/components/block-editor/field-settings/` и `ScenarioBlockEditorDrawer.vue` |
| Player | `resources/js/modules/scenario/components/player/SurveyBlockRenderer.vue` или отдельный `SurveyXxxField.vue` |
| PHP enum | `module/Scenario/Enums/BlockFieldType.php` |
| PHP Strategy/Factory | `module/Scenario/Services/Nodes/Block/Fields/` |
| Backend validation | `module/Scenario/Services/Nodes/Block/BlockNodeValidator.php` и `Rules/` |
| Presets/variables | `types/field-preset.ts`, `types/scenario-variable-entry.ts` и связанные composables/tests |

## TypeScript contract

В `scenario-block-fields.ts`:

1. Добавить строку в `BlockFieldType` и отдельный interface, расширяющий `BaseBlockField`.
2. Добавить interface в discriminated union `BlockField`.
3. Добавить полный default в `createScenarioBlockField()`.
4. Добавить безопасную normalization-ветку для raw/stored JSON. Поддерживать legacy snake_case alias только при реальной обратной совместимости.
5. Обновить `duplicateBlockFieldIds()` для всех принадлежащих полю nested ID и внутренних ссылок. Внешние directory/action/proxy ID не менять.

Display-only поля не создают переменную и не участвуют в form validation. Input-поля получают устойчивые `name`/`varName`; конфликт имён разрешается существующим механизмом редактора.

## Editor

Создать `components/block-editor/field-settings/XxxFieldSettings.vue` по ближайшему аналогу:

- `defineProps<{ field: XxxField; disabled?: boolean }>()`;
- typed emit `update: [patch: Partial<XxxField>]`;
- partial patch вместо мутации prop или отправки всего документа;
- shadcn-vue controls и `lucide-vue-next`;
- `fieldMeta` с `type`, русским `label` и icon.

В `ScenarioBlockEditorDrawer.vue` зарегистрировать meta в видимой группе либо в явно именованном списке временно скрытых полей, а settings component — в `fieldSettingsComponents`. Не добавлять watcher для синхронизации всего поля: изменения идут через явную команду/patch store.

## Player

Для небольшого стандартного input допустима typed ветка в `SurveyBlockRenderer.vue`. Сложное поле с собственными reads, состоянием, картой, таблицей или зависимыми фильтрами вынести в `components/player/SurveyXxxField.vue`.

- Не вызывать HTTP из renderer/component; использовать repository через composable.
- Для повторных reads применять `useLatestRequest` и инвалидировать pending state при unmount.
- Писать значение только в `formData[fieldName]`; display-only поле его не меняет.
- Отображать server validation error и disabled state по существующему контракту.

## PHP Strategy/Factory

1. Добавить enum case в `BlockFieldType`.
2. Создать `XxxBlockField` в `Services/Nodes/Block/Fields/`, реализующий `BlockFieldInterface` напрямую или через `AbstractBlockField`.
3. Зарегистрировать type в `BlockFieldFactory::normalizedType()` и одну creation-ветку в `create()`.
4. Передавать зависимости через constructor; не использовать `app()`, `resolve()` и `request()` внутри field/Factory.

`BlockFieldFactory` — единственная точка выбора Strategy. Создание stateful field value внутри Factory допустимо; container lookup и дублирование type-switch в handler запрещены.

Если поле собирает ввод, добавить правила в `BlockNodeValidator`. Для сложной проверки создать отдельный `ValidationRule`, а не расширять условную цепочку бизнес-логикой.

## Presets и переменные

- Snapshot preset может не иметь top-level `id`; normalizer обязан восстановить runtime identity.
- Две вставки preset не разделяют field/option/rule/action-item ID и внутренние ссылки.
- Удалённый внешний ресурс обрабатывается settings UI без падения.
- Для нового value shape обновить variable entry/accessor только если форма действительно публикует это значение.

## Тесты и проверка

Добавить релевантные frontend unit-тесты для defaults, normalization и duplicate IDs; backend unit-тест — для `BlockFieldFactory`/props и validation. Следовать [quick checklist](references/add-new-block-field-checklist.md).

Запускать только через Taskfile:

```bash
task test:frontend -- resources/js/modules/scenario/__tests__/<test>.test.ts
task test:unit -- --filter=BlockField
task typecheck
task lint -- resources/js/modules/scenario
task phpstan -- module/Scenario
task build:frontend
```
