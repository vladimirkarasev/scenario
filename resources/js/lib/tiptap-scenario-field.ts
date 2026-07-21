import {Node, mergeAttributes} from '@tiptap/core'
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

export const ScenarioField = Node.create<ScenarioFieldOptions>({
    name: 'scenarioField',
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
