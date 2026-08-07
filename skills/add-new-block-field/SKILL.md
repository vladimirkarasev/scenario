---
name: add-new-block-field
description: Add a new field type (or field group) to the scenario block editor — covers the TypeScript type layer, block editor palette + settings UI, player renderer, and PHP backend. Use when asked to add a new input type, display element, or field category to the block node editor.
---

# Add New Block Field Type

## Overview

A block field type exists at four layers that must all be updated:

| Layer | What changes |
|---|---|
| **TypeScript types** | `scenario-block-fields.ts` — type union + interface + factory + normalizer |
| **Block editor UI** | `field-settings/XxxFieldSettings.vue` + registration in `ScenarioBlockEditorDrawer.vue` |
| **Player renderer** | `SurveyBlockRenderer.vue` — `v-else-if` branch for the new type |
| **PHP backend** | `BlockNodeHandler::renderField()` — maps stored field data to API props |
| **Project presets** | Snapshot normalization, cloning and backend `BlockFieldType` allow-list |

Key files:
- `resources/js/modules/scenario/lib/scenario-block-fields.ts` — source of truth for all types
- `resources/js/modules/scenario/components/ScenarioBlockEditorDrawer.vue` — palette + settings dispatch
- `resources/js/modules/scenario/components/field-settings/` — one component per field type
- `resources/js/modules/scenario/components/SurveyBlockRenderer.vue` — player-side renderer
- `module/Scenario/Services/Nodes/BlockNodeHandler.php` — backend `renderField()`
- `module/Scenario/Enums/BlockFieldType.php` — backend allow-list for field preset snapshots

## Step-by-Step

### 1. TypeScript: add the type string

File: `resources/js/modules/scenario/lib/scenario-block-fields.ts`

```ts
export type BlockFieldType = 'input' | ... | 'my_field'
```

### 2. TypeScript: add the interface

After the existing field interfaces:

```ts
export interface MyBlockField extends BaseBlockField {
    type: 'my_field'
    myProp: string
    anotherProp: boolean
}
```

Always extend `BaseBlockField` which provides: `id`, `type`, `name`, `label`, `required`, `varName`, `validation?`.

Fields that don't collect user input and don't appear in the form (`rich_text`, `collapse`) set `varName: ''` and ignore `required`.

### 3. TypeScript: add to the union

```ts
export type BlockField =
    | InputBlockField
    | ...
    | MyBlockField
```

### 4. TypeScript: add factory default

In `createScenarioBlockField()`, inside the `byType` record:

```ts
my_field: {
    id,
    type: 'my_field',
    name: `my_field_${n}`,
    label: 'Моё поле',
    required: false,
    varName: labelToVarName('Моё поле'),
    myProp: '',
    anotherProp: false,
},
```

### 5. TypeScript: add normalizer case

In `normalizeScenarioBlockField()`, inside the `switch (type)` block:

```ts
case 'my_field':
    return {
        ...nb,
        myProp: String(f.myProp ?? (base as MyBlockField).myProp),
        anotherProp: Boolean(f.anotherProp ?? (base as MyBlockField).anotherProp),
    } as MyBlockField
```

`nb` is the normalized `BaseBlockField`. The normalizer reads from `f` (raw stored JSON) and falls back to `base` defaults. Always prefer camelCase keys from `f`; add snake_case aliases for backward compatibility if needed.

### 6. Create the settings component

File: `resources/js/modules/scenario/components/field-settings/MyFieldSettings.vue`

The component has two `<script>` blocks:

```vue
<script lang="ts">
import { markRaw } from 'vue'
import { SomeIcon } from 'lucide-vue-next'
// fieldMeta is imported by ScenarioBlockEditorDrawer to build the palette
export const fieldMeta = { type: 'my_field', label: 'Моё поле', icon: markRaw(SomeIcon) }
</script>

<script setup lang="ts">
import { Label } from '@/components/ui/label'
import { Input } from '@/components/ui/input'
import type { MyBlockField } from '../../lib/scenario-block-fields'

defineProps<{ field: MyBlockField; disabled?: boolean }>()
const emit = defineEmits<{ update: [patch: Partial<MyBlockField>] }>()
defineOptions({ inheritAttrs: false })
</script>

<template>
    <div class="space-y-4">
        <div class="space-y-1.5">
            <Label class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Моё свойство</Label>
            <Input
                :model-value="field.myProp"
                :disabled="disabled"
                class="h-9 text-sm"
                @update:model-value="emit('update', { myProp: String($event) })"
            />
        </div>
    </div>
</template>
```

The `update` event emits a **partial patch** — only the changed keys. `ScenarioBlockEditorDrawer` merges it with the existing field object.

Icons: use `lucide-vue-next` only. Browse at https://lucide.dev.

For field types that reuse another field's settings UI, just `import` the shared component and delegate, like `InputFieldSettings.vue` does with `SimpleTextFieldSettings.vue`.

### 7. Register in ScenarioBlockEditorDrawer

File: `resources/js/modules/scenario/components/ScenarioBlockEditorDrawer.vue`

**Import:**
```ts
import MyFieldSettings, { fieldMeta as myFieldMeta } from '@/modules/scenario/components/field-settings/MyFieldSettings.vue'
```

**Add to palette group** (in `fieldGroups`):

```ts
const fieldGroups = [
    { title: 'Поля',    items: [inputMeta, ..., myFieldMeta] },   // ← or a new group:
    { title: 'Моя группа', items: [myFieldMeta] },
]
```

**Add settings component dispatch** (in `fieldSettingsComponents`):

```ts
const fieldSettingsComponents: Record<string, Component> = {
    ...
    my_field: markRaw(MyFieldSettings),
}
```

That's it for the editor side. The `fieldTypeMap` computed is built automatically from `fieldGroups`, so label and icon are resolved without extra code.

### 8. Add player renderer branch

File: `resources/js/modules/scenario/components/SurveyBlockRenderer.vue`

Add a `v-else-if` branch **before** the final `v-else` fallback:

```vue
<div v-else-if="block.type === 'my_field'" class="grid gap-1.5">
    <Label class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">
        {{ resolvedProps.label ?? fieldName }}
        <span v-if="Boolean(resolvedProps.required)" class="ml-0.5 text-destructive">*</span>
    </Label>
    <!-- your input component here, bound to formData[fieldName] -->
    <p v-if="hasError" class="text-[12px] text-destructive">{{ fieldError }}</p>
</div>
```

Available inside the template:
- `resolvedProps` — `field.props` with `{{ variable }}` expressions resolved from `context`
- `formData[fieldName]` — reactive form state (mutate in place)
- `fieldName`, `fieldError`, `hasError`, `disabled`

If the field is display-only (no user input), omit `formData` binding and skip `fieldError`.

### 9. PHP backend: add to renderField

File: `module/Scenario/Services/Nodes/BlockNodeHandler.php`

In `renderField()`, add the new type to the `$blockType = match ($type)` expression:

```php
$blockType = match ($type) {
    'textarea', 'number', ..., 'my_field' => $type,
    default => 'input',
};
```

Then add a `match ($blockType)` arm in the `$props` assignment (or extend `default` if the standard props suffice):

```php
'my_field' => [
    'name'      => $name,
    'label'     => $this->strField($field, 'label', $name),
    'required'  => $this->boolField($field, 'required'),
    'myProp'    => $this->strField($field, 'myProp'),
    'anotherProp' => $this->boolField($field, 'anotherProp'),
],
```

Available `NodeHelpers` methods: `strField`, `boolField`, `intField`, `arrayField`.

If the field collects user input, add validation in `BlockNodeValidator.php`.

### 10. Keep project field presets compatible

Every field type can be saved as a project-scoped reusable preset. Presets are snapshots, not live links to fields already placed in blocks.

- Add the type to `module/Scenario/Enums/BlockFieldType.php`.
- Ensure `normalizeScenarioBlockField()` accepts a stored snapshot without a top-level `id`.
- Ensure `duplicateBlockFieldIds()` regenerates every runtime identity owned by the type. This includes nested option, validation-rule and action-item IDs, plus references such as `parentId`.
- Keep external resource IDs such as directory or action identifiers unchanged. The field settings UI must handle a referenced resource that was later removed.
- Never store a preset ID on an inserted field. `instantiateScenarioBlockField()` creates an independent copy and the editor makes `varName` unique.
- Add a unit test proving that two insertions do not share IDs and that nested references are remapped correctly.

## Field groups in the palette

The palette in `ScenarioBlockEditorDrawer.vue` is organized as:

```ts
const fieldGroups = [
    { title: 'Поля',                   items: [...] },  // interactive inputs
    { title: 'Контент',                items: [...] },  // display-only
    { title: 'Удалённые справочники',  items: [...] },  // data-bound
]
```

To add a whole new group, append a new entry to `fieldGroups`. To add a field to an existing group, push `myFieldMeta` into that group's `items`.

## Validation rules (optional)

If the new field type supports configurable validation, register it in `AVAILABLE_VALIDATION_RULES`:

```ts
export const AVAILABLE_VALIDATION_RULES: Partial<Record<BlockFieldType, ValidationRuleType[]>> = {
    ...
    my_field: ['minLength', 'maxLength'],
}
```

The `ValidationChainBuilder` component appears automatically in the settings dialog when this is set.

## Verification

```bash
npx tsc --noEmit
npx eslint resources/js/modules/scenario/
./vendor/bin/phpstan analyse module/Scenario --memory-limit=512M
```

Then:
1. Open the block editor, drag the new field from the palette onto a block.
2. Open field settings — verify your settings component renders.
3. Open the preview tab — verify it renders in the player preview.
4. Run a scenario that hits the block — verify the API response includes the correct `type` and `props`.
5. Save the field as a user preset, insert it twice, edit one copy and verify the preset and the other copy are unchanged.

## References

See [references/add-new-block-field-checklist.md](references/add-new-block-field-checklist.md) for the quick checklist.
