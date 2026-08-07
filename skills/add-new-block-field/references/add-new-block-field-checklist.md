# Add New Block Field — Quick Checklist

Replace `my_field` / `MyField` / `myFieldMeta` with your actual type string and names throughout.

## TypeScript (`resources/js/modules/scenario/lib/scenario-block-fields.ts`)

- [ ] Add `'my_field'` to `BlockFieldType` union
- [ ] Add `MyBlockField extends BaseBlockField` interface with all type-specific properties
- [ ] Add `MyBlockField` to `BlockField` union
- [ ] Add entry in `createScenarioBlockField()` → `byType` record (all required props, sensible defaults)
- [ ] Add `case 'my_field':` in `normalizeScenarioBlockField()` → reads from raw `f`, falls back to `base`
- [ ] Add `'my_field'` to `module/Scenario/Enums/BlockFieldType.php`
- [ ] Verify normalization works when a preset snapshot has no top-level `id`
- [ ] Regenerate all owned nested IDs and remap their internal references in `duplicateBlockFieldIds()`
- [ ] Preserve external resource IDs; handle missing referenced resources in settings UI

## Block editor UI

- [ ] Create `resources/js/modules/scenario/components/field-settings/MyFieldSettings.vue`
  - [ ] Non-setup `<script lang="ts">` block exports `fieldMeta = { type, label, icon }`
  - [ ] Setup block: `defineProps<{ field: MyBlockField; disabled?: boolean }>()`
  - [ ] Emits `update: [patch: Partial<MyBlockField>]` — partial patch only, not full object
  - [ ] `defineOptions({ inheritAttrs: false })`
- [ ] `resources/js/modules/scenario/components/ScenarioBlockEditorDrawer.vue`
  - [ ] Import: `import MyFieldSettings, { fieldMeta as myFieldMeta } from '...'`
  - [ ] Add `myFieldMeta` to the appropriate group in `fieldGroups`
  - [ ] Add `my_field: markRaw(MyFieldSettings)` to `fieldSettingsComponents`

## Player renderer (`resources/js/modules/scenario/components/SurveyBlockRenderer.vue`)

- [ ] Add `v-else-if="block.type === 'my_field'"` branch before the final `v-else`
- [ ] Bind `formData[fieldName]` if field collects user input
- [ ] Show `fieldError` / `hasError` if field is required-capable

## PHP backend (`module/Scenario/Services/Nodes/BlockNodeHandler.php`)

- [ ] Add `'my_field'` to the `match ($type)` expression that sets `$blockType`
- [ ] Add `'my_field' => [...]` arm in the `$props` assignment with all props the frontend needs
- [ ] (Optional) Add validation rules in `BlockNodeValidator.php`

## Project field presets

- [ ] Save the configured field as a project preset
- [ ] Insert the preset twice and verify field, rule, option and action-item IDs do not overlap
- [ ] Verify duplicate `varName` values receive deterministic `_2`, `_3` suffixes
- [ ] Update the preset from a configured field and verify existing block fields remain unchanged
- [ ] Delete the preset and verify existing block fields remain unchanged

## Decision table

| Field characteristic | What to do |
|---|---|
| Collects user input (name/varName matters) | Set `varName` in factory + normalizer; bind `formData[fieldName]` in renderer |
| Display-only (rich_text, collapse) | Set `varName: ''` in factory; skip `required`; no `formData` binding |
| Configurable validation | Add to `AVAILABLE_VALIDATION_RULES` in `scenario-block-fields.ts` |
| Shares UI with an existing field | Import the shared settings component and delegate to it |
| New palette group | Add `{ title: '...', items: [...] }` entry to `fieldGroups` in `ScenarioBlockEditorDrawer.vue` |

## Verification

```bash
npx tsc --noEmit
npx eslint resources/js/modules/scenario/
./vendor/bin/phpstan analyse module/Scenario --memory-limit=512M
```
