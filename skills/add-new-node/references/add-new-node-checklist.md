# Add New Node — Quick Checklist

Replace `my_node` / `MyNode` with your actual type string and class name throughout.

## Backend

- [ ] `module/Scenario/Enums/ScenarioNodeType.php` — add `case MyNode = 'my_node';`
- [ ] `module/Scenario/Services/Nodes/MyNodeHandler.php` — implement `NodeHandlerInterface`
  - [ ] `isInteractive()` — `false` for auto-advance, `true` to wait for user input
  - [ ] `advance()` — for non-interactive nodes; return `NodeAdvanceResult::next(?string)`
  - [ ] `continueFrom()` — for interactive nodes; validate input, return next node ID or `null`
  - [ ] `render()` — shape of the API response the scenario player sends to the client
- [ ] `module/Scenario/Services/Nodes/NodeHandlerRegistry.php`
  - [ ] Add `private MyNodeHandler $myNode` to constructor
  - [ ] Add `ScenarioNodeType::MyNode->value => $this->myNode` to `match`

## Frontend

- [ ] `resources/js/modules/scenario/lib/scenario-flow-document.ts`
  - [ ] Add `'my_node'` to `NodeType` union
  - [ ] Add entry in `defaultNodeData()` with all `ScenarioBlockData` base fields + any extras
- [ ] `resources/js/modules/scenario/components/flow/nodes/MyNode.vue` — create component
  - [ ] `defineProps<NodeProps>()` from `@vue-flow/core`
  - [ ] At least one `Handle type="target"` (input) and one `Handle type="source"` (output)
- [ ] `resources/js/modules/scenario/components/flow/ScenarioFlowEditor.vue`
  - [ ] Import `MyNode`
  - [ ] Add `my_node: markRaw(MyNode)` to `nodeTypes`
  - [ ] Add `{ type: 'my_node', label: '...' }` to `paletteItems`
  - [ ] (Optional) Handle in `onNodeDoubleClick()` if needs custom editor
  - [ ] (Optional) Handle in `addNode()` if needs special init on creation

## Verification

```bash
npx tsc --noEmit
npx eslint resources/js/modules/scenario/
./vendor/bin/phpstan analyse module/Scenario --memory-limit=512M
```

## Decision Table

| Does the node… | What to do |
|---|---|
| Auto-advance (no user input) | `isInteractive()` → `false`; logic in `advance()` |
| Wait for user choice | `isInteractive()` → `true`; logic in `continueFrom()` |
| Need conditional output ports | Add `conditionBranches` to `defaultNodeData`, give each `Handle` a matching `id` |
| Need a custom editor drawer | Add open flag, handle in `onNodeDoubleClick()` |
| Need special data on creation | Handle in `addNode()` after `createScenarioFlowNode()` |
