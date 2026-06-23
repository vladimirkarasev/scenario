---
name: add-new-node
description: Add a new node type to the scenario flow editor — covers both the PHP backend (enum, handler, registry) and the Vue 3 frontend (type union, defaultNodeData, Vue component, ScenarioFlowEditor registration). Use when asked to create a new node type, add a node to the graph editor, or implement a new step type in the scenario engine.
---

# Add New Node Type

## Overview

A node type spans two layers: the **backend** (PHP handler that runs during scenario execution) and the **frontend** (Vue component that renders in the graph editor). Both must be updated for a node to work end-to-end.

Key paths:
- Backend enum: `module/Scenario/Enums/ScenarioNodeType.php`
- Backend handlers: `module/Scenario/Services/Nodes/`
- Frontend types + data: `resources/js/modules/scenario/lib/scenario-flow-document.ts`
- Frontend node components: `resources/js/modules/scenario/components/flow/nodes/`
- Frontend editor registration: `resources/js/modules/scenario/components/flow/ScenarioFlowEditor.vue`

## Step-by-Step

### Backend

#### 1. Add enum case

File: `module/Scenario/Enums/ScenarioNodeType.php`

```php
case MyNode = 'my_node';
```

The value is the string used everywhere as `node.type`.

#### 2. Create handler

File: `module/Scenario/Services/Nodes/MyNodeHandler.php`

```php
<?php

declare(strict_types=1);

namespace Module\Scenario\Services\Nodes;

use Module\Scenario\DTO\ScenarioRunContinueData;
use Module\Scenario\Models\ScenarioRun;
use Module\Scenario\Models\ScenarioVersion;

final readonly class MyNodeHandler implements NodeHandlerInterface
{
    use NodeHelpers;

    public function isInteractive(array $node): bool
    {
        return false; // true = player waits for user input before advancing
    }

    public function advance(ScenarioRun $run, array $node): NodeAdvanceResult
    {
        // Non-interactive: compute next node and return
        return NodeAdvanceResult::next(null); // null = first outgoing edge
    }

    public function continueFrom(ScenarioRun $run, array $node, ScenarioRunContinueData $data): ?string
    {
        // Interactive: validate user input, return next node ID or null to end
        return null;
    }

    public function render(ScenarioVersion $version, array $node, array $context): array
    {
        // What the scenario player API returns to the client
        $data = $this->nodeData($node);
        return [
            'type' => 'my_node',
            'title' => $this->strField($data, 'title'),
        ];
    }
}
```

`NodeHelpers` provides: `nodeData(array $node): array`, `nodeId(array $node): string`, `strField(array $data, string $key, string $default = ''): string`, `arrayField(array $data, string $key): array`, `runVersion(ScenarioRun $run): ScenarioVersion`.

`NodeAdvanceResult::next(?string $nodeId)` — advance to explicit node ID or first outgoing edge when `null`.

#### 3. Register in registry

File: `module/Scenario/Services/Nodes/NodeHandlerRegistry.php`

Add constructor property:
```php
private MyNodeHandler $myNode,
```

Add match case in `for()`:
```php
ScenarioNodeType::MyNode->value => $this->myNode,
```

Laravel's service container auto-wires the constructor — no binding needed.

### Frontend

#### 4. Add to NodeType union

File: `resources/js/modules/scenario/lib/scenario-flow-document.ts`

```ts
export type NodeType = 'start' | 'block' | 'action' | 'condition' | 'end' | 'scenario_link' | 'my_node'
```

#### 5. Add default data

In the same file, inside `defaultNodeData()`:

```ts
my_node: { title: 'My Node', variable: '', skipInSurvey: false, text: '', fields: [], targetScenarioId: null, conditionBranches: [] },
```

Add any extra fields the node needs beyond the base `ScenarioBlockData` — they are stored in the same `data` object (the interface has `[key: string]: unknown`).

#### 6. Create Vue component

File: `resources/js/modules/scenario/components/flow/nodes/MyNode.vue`

Use an existing simple node (e.g. `EndNode.vue` or `StartNode.vue`) as a template. Node components receive `NodeProps` from `@vue-flow/core`.

```vue
<script setup lang="ts">
import type { NodeProps } from '@vue-flow/core'
import { Handle, Position } from '@vue-flow/core'

defineProps<NodeProps>()
</script>

<template>
  <div class="...">
    <Handle type="target" :position="Position.Left" />
    <span>{{ data.title }}</span>
    <Handle type="source" :position="Position.Right" />
  </div>
</template>
```

Use `Handle type="target"` for input ports and `Handle type="source"` for output ports. For conditional output ports (like `ConditionNode`), give each `Handle` a unique `id` that matches the `conditionBranches[].id` values.

#### 7. Register in ScenarioFlowEditor

File: `resources/js/modules/scenario/components/flow/ScenarioFlowEditor.vue`

Add import at the top:
```ts
import MyNode from '@/modules/scenario/components/flow/nodes/MyNode.vue'
```

Add to `nodeTypes` object:
```ts
my_node: markRaw(MyNode),
```

Add to `paletteItems` array (the drag-to-canvas sidebar):
```ts
{ type: 'my_node', label: 'Моя нода' },
```

#### 8. Handle double-click (if needed)

In `onNodeDoubleClick()`, the default behaviour opens the generic drawer (`drawerOpen.value = true`). Override only if the node needs its own editor:

```ts
if (node.type === 'my_node') {
    myNodeEditorOpen.value = true
    return
}
```

#### 9. Handle addNode init (if needed)

In `addNode()`, the default creates a node from `defaultNodeData`. Override for type-specific auto-naming or state:

```ts
if (type === 'my_node') {
    block.data.title = `my_node_${someCount + 1}`
}
```

## Verification

```bash
npx tsc --noEmit
npx eslint resources/js/modules/scenario/
```

Then open the editor, drag the new node from the palette, and double-click it. Check the scenario player API response includes the node's `render()` output by running a scenario that reaches the node.

## References

See [references/add-new-node-checklist.md](references/add-new-node-checklist.md) for the quick checklist.
