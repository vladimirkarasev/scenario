import {Node, mergeAttributes} from '@tiptap/core'
import {VueNodeViewRenderer} from '@tiptap/vue-3'
import TiptapDetailsNodeView from '@/modules/scenario/components/tiptap/TiptapDetailsNodeView.vue'

declare module '@tiptap/core' {
    interface Commands<ReturnType> {
        details: {
            setDetails: () => ReturnType
        }
    }
}

export const Details = Node.create({
    name: 'details',
    group: 'block',
    content: 'block+',
    defining: true,

    addAttributes() {
        return {
            title: {
                default: 'Подробнее',
                parseHTML: (element) => element.querySelector('summary')?.textContent?.trim() ?? 'Подробнее',
                renderHTML: () => ({}),
            },
            open: {
                default: true,
                parseHTML: (element) => element.hasAttribute('open'),
                renderHTML: (attrs) => (attrs.open ? {open: ''} : {}),
            },
        }
    },

    parseHTML() {
        return [{tag: 'details', contentElement: '[data-type="details-content"]'}]
    },

    renderHTML({HTMLAttributes, node}) {
        return [
            'details',
            mergeAttributes(HTMLAttributes),
            ['summary', {}, node.attrs.title],
            ['div', {'data-type': 'details-content'}, 0],
        ]
    },

    addNodeView() {
        return VueNodeViewRenderer(TiptapDetailsNodeView)
    },

    addCommands() {
        return {
            setDetails: () => ({commands}) =>
                commands.insertContent({
                    type: this.name,
                    attrs: {title: 'Подробнее', open: true},
                    content: [{type: 'paragraph'}],
                }),
        }
    },
})
