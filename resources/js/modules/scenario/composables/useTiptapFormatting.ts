import type {Editor} from '@tiptap/vue-3'
import type {ComputedRef, Ref} from 'vue'

export function useTiptapFormatting(editor: Ref<Editor | undefined> | ComputedRef<Editor | undefined>, editable: Ref<boolean> | ComputedRef<boolean>) {
    function isActive(nameOrAttrs: string | Record<string, unknown>, attrs?: Record<string, unknown>): boolean {
        if (!editor.value) return false
        return typeof nameOrAttrs === 'string' ? editor.value.isActive(nameOrAttrs, attrs) : editor.value.isActive(nameOrAttrs)
    }

    function run(command: (chain: ReturnType<NonNullable<typeof editor.value>['chain']>) => unknown) {
        if (!editor.value || !editable.value) return
        command(editor.value.chain().focus())
    }

    function toolbarButtonClass(active = false) {
        return active ? 'bg-slate-100 text-slate-950 ring-1 ring-slate-200' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-950'
    }

    function bubbleButtonClass(active = false) {
        return active ? 'bg-slate-100 text-slate-950' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-950'
    }

    function currentBlockType() {
        for (const level of [1, 2, 3, 4, 5, 6]) {
            if (isActive('heading', {level})) return `heading${level}`
        }
        return 'paragraph'
    }

    function setBlockType(e: Event) {
        const value = (e.target as HTMLSelectElement).value
        const match = value.match(/^heading(\d)$/)

        if (match) {
            run((chain) => chain.toggleHeading({level: Number(match[1]) as 1 | 2 | 3 | 4 | 5 | 6}).run())
            return
        }

        run((chain) => chain.setParagraph().run())
    }

    function currentColor() {
        return editor.value?.getAttributes('textStyle')?.color ?? '#000000'
    }

    function setColor(e: Event) {
        run((chain) => chain.setColor((e.target as HTMLInputElement).value).run())
    }

    function currentHighlightColor() {
        return editor.value?.getAttributes('highlight')?.color ?? '#fef08a'
    }

    function setHighlightColor(e: Event) {
        run((chain) => chain.setHighlight({color: (e.target as HTMLInputElement).value}).run())
    }

    function setLink() {
        if (!editor.value || !editable.value) return

        const previousUrl = editor.value.getAttributes('link').href ?? ''
        const url = window.prompt('URL', previousUrl)

        if (url === null) return

        if (url.trim() === '') {
            editor.value.chain().focus().extendMarkRange('link').unsetLink().run()
            return
        }

        editor.value.chain().focus().extendMarkRange('link').setLink({href: url.trim()}).run()
    }

    return {
        isActive,
        run,
        toolbarButtonClass,
        bubbleButtonClass,
        currentBlockType,
        setBlockType,
        currentColor,
        setColor,
        currentHighlightColor,
        setHighlightColor,
        setLink,
    }
}
