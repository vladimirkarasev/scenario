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

// Block-нода поля блока. Заголовок поля — обычный редактируемый inline-контент
// tiptap (content: 'inline*'), поэтому форматируется тем же тулбаром/bubble
// menu, что и остальной документ (bold/color/highlight и т.д.) — специальных
// контролов внутри самой ноды для этого не нужно. Реальный контрол поля
// (Input/Select/...) — статичная (не-ProseMirror) часть NodeView вокруг
// заголовка. Поле резолвится по fieldId через options.getField, которые
// настраивает BlockEditorGutenbergEditor.
export const ScenarioField = Node.create<ScenarioFieldOptions>({
    name: 'scenarioField',
    group: 'block',
    content: 'inline*',
    draggable: true,
    // false — иначе клик/тройной клик по заголовку иногда схлопывает выделение
    // текста в NodeSelection всей ноды, и следующий Backspace/Delete удаляет
    // поле целиком вместо текста заголовка. Перетаскивание (draggable) от этого
    // не зависит — работает через data-drag-handle.
    selectable: false,
    // true — иначе ProseMirror на Backspace в самом начале документа может
    // «разжаловать» пустое поле в обычный параграф (стандартный fallback
    // joinBackward для текстовых блоков) — поле визуально пропадает из
    // документа, хотя его данные остаются. Пустые параграфы рядом с полем
    // при этом чистим вручную через addKeyboardShortcuts ниже, не полагаясь
    // на joinBackward, которому isolating закрывает проход через границу.
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

    // isolating блокирует стандартный joinBackward сквозь границу поля, поэтому
    // пустой параграф, оставшийся вплотную к полю (например, после Enter),
    // сам не удаляется по Backspace — чистим его здесь явно, не трогая саму
    // ноду поля. Единственное исключение — самый последний параграф в
    // документе: его держит встроенный TrailingNode (из StarterKit), он тут
    // же вставит такой же обратно, и это осознанное поведение (всегда есть
    // куда кликнуть после последнего поля), поэтому его не трогаем.
    addKeyboardShortcuts() {
        return {
            Backspace: () => {
                const {selection} = this.editor.state
                if (!selection.empty) return false

                const {$from} = selection

                // Курсор внутри уже пустого заголовка поля: гасим Backspace явно,
                // иначе ProseMirror-фоллбэк «разжалует» пустой textblock в обычный
                // paragraph (стандартное поведение joinBackward, когда сливать
                // не с чем) — поле визуально исчезает из документа, хотя данные
                // остаются. isolating сам по себе от этого не защищает.
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
