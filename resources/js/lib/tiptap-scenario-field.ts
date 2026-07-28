import {Node, mergeAttributes} from '@tiptap/core'
import {Selection} from '@tiptap/pm/state'
import {VueNodeViewRenderer} from '@tiptap/vue-3'
import TiptapScenarioFieldNodeView from '@/modules/scenario/components/tiptap/TiptapScenarioFieldNodeView.vue'
import type {BlockField, BlockFieldType} from '@/modules/scenario/lib/scenario-block-fields'
import type {Component} from 'vue'

export interface ScenarioFieldOptions {
    getField: (fieldId: string) => BlockField | null
    isEditable: () => boolean
    fieldTypeLabel: (type: BlockFieldType) => string
    fieldTypeIcon: (type: BlockFieldType) => Component
    onUpdateField: (fieldId: string, patch: Partial<BlockField>) => void
    onOpenSettings: (fieldId: string) => void
    onDeleteField: (fieldId: string) => void
}

export const SCENARIO_FIELD_NODE_NAME = 'scenarioField'

export function shouldConsumeEnterInScenarioField(parentNodeTypeName: string): boolean {
    return parentNodeTypeName === SCENARIO_FIELD_NODE_NAME
}

export interface EnterInScenarioFieldAction {
    insertAt: number
    focusAt: number | null
}

export function computeEnterInScenarioFieldAction(params: {
    parentOffset: number
    parentContentSize: number
    beforePos: number
    afterPos: number
}): EnterInScenarioFieldAction {
    const atEnd = params.parentOffset === params.parentContentSize

    return atEnd
        ? {insertAt: params.afterPos, focusAt: params.afterPos + 1}
        : {insertAt: params.beforePos, focusAt: null}
}

export const ScenarioField = Node.create<ScenarioFieldOptions>({
    name: SCENARIO_FIELD_NODE_NAME,
    // Выше приоритета core-расширения Keymap (100), иначе его дефолтный
    // Enter -> splitBlock срабатывает первым и наш обработчик ниже не вызывается.
    priority: 1000,
    group: 'block',
    content: 'inline*',
    draggable: true,
    selectable: false,
    isolating: true,

    addOptions() {
        return {
            getField: () => null,
            isEditable: () => true,
            fieldTypeLabel: (type: BlockFieldType) => type,
            fieldTypeIcon: () => null as unknown as Component,
            onUpdateField: () => {},
            onOpenSettings: () => {},
            onDeleteField: () => {},
        }
    },

    addAttributes() {
        return {
            fieldId: {
                default: null,
                parseHTML: (element) => element.getAttribute('data-field-id'),
                renderHTML: (attrs) => ({'data-field-id': attrs.fieldId}),
            },
        }
    },

    parseHTML() {
        return [{tag: 'div[data-type="scenario-field"]'}]
    },

    renderHTML({HTMLAttributes}) {
        return ['div', mergeAttributes(HTMLAttributes, {'data-type': 'scenario-field'}), 0]
    },

    addNodeView() {
        return VueNodeViewRenderer(TiptapScenarioFieldNodeView)
    },

    addKeyboardShortcuts() {
        return {
            Enter: () => {
                const {$from} = this.editor.state.selection

                if (!shouldConsumeEnterInScenarioField($from.parent.type.name)) return false

                const action = computeEnterInScenarioFieldAction({
                    parentOffset: $from.parentOffset,
                    parentContentSize: $from.parent.content.size,
                    beforePos: $from.before($from.depth),
                    afterPos: $from.after($from.depth),
                })

                const chain = this.editor.chain().focus().insertContentAt(
                    action.insertAt,
                    {type: 'paragraph'},
                    {updateSelection: action.focusAt !== null},
                )

                return (action.focusAt !== null ? chain.setTextSelection(action.focusAt) : chain).run()
            },

            // Между двумя isolating-нодами (лейбл поля) ProseMirror по умолчанию
            // ставит gap-cursor вместо перехода в соседнюю ноду; ArrowDown/Up
            // явно ищут ближайшую валидную текстовую позицию за пределами узла.
            ArrowDown: () => {
                const {$from} = this.editor.state.selection
                if ($from.parent.type.name !== this.name) return false

                const afterPos = $from.after($from.depth)
                if (afterPos >= this.editor.state.doc.content.size) return false

                const target = Selection.near(this.editor.state.doc.resolve(afterPos), 1)

                return this.editor.chain().focus().setTextSelection({from: target.from, to: target.to}).run()
            },

            ArrowUp: () => {
                const {$from} = this.editor.state.selection
                if ($from.parent.type.name !== this.name) return false

                const beforePos = $from.before($from.depth)
                if (beforePos <= 0) return false

                const target = Selection.near(this.editor.state.doc.resolve(beforePos), -1)

                return this.editor.chain().focus().setTextSelection({from: target.from, to: target.to}).run()
            },

            Backspace: () => {
                const {selection} = this.editor.state
                if (!selection.empty) return false

                const {$from} = selection

                if ($from.parent.type.name === this.name && $from.parent.content.size === 0 && $from.parentOffset === 0) {
                    return true
                }

                if ($from.parent.type.name !== 'paragraph' || $from.parent.content.size !== 0 || $from.parentOffset !== 0) {
                    return false
                }

                const from = $from.before($from.depth)
                const to = from + $from.parent.nodeSize
                const nodeBefore = from > 0 ? this.editor.state.doc.resolve(from).nodeBefore : null
                const nodeAfter = to < this.editor.state.doc.content.size
                    ? this.editor.state.doc.resolve(to).nodeAfter
                    : null

                const adjacentToField = nodeBefore?.type.name === this.name || nodeAfter?.type.name === this.name
                if (!adjacentToField) return false

                return this.editor.chain().focus().deleteRange({from, to}).run()
            },
        }
    },
})
