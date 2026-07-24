import {ref} from 'vue'
import type {Editor} from '@tiptap/vue-3'
import type {ComputedRef, Ref} from 'vue'

export function useTiptapLinkDialog(
    editor: Ref<Editor | undefined> | ComputedRef<Editor | undefined>,
    editable: Ref<boolean> | ComputedRef<boolean>,
) {
    const open = ref(false)
    const url = ref('')
    const target = ref<string | null>(null)
    const hasLink = ref(false)

    function openDialog(): void {
        if (!editor.value || !editable.value) return

        const attrs = editor.value.getAttributes('link')
        url.value = typeof attrs.href === 'string' ? attrs.href : ''
        target.value = typeof attrs.target === 'string' ? attrs.target : null
        hasLink.value = editor.value.isActive('link')
        open.value = true
    }

    function apply({href, target: nextTarget}: { href: string, target: string | null }): void {
        editor.value?.chain().focus().extendMarkRange('link').setLink({href, target: nextTarget}).run()
    }

    function remove(): void {
        editor.value?.chain().focus().extendMarkRange('link').unsetLink().run()
    }

    return {open, url, target, hasLink, openDialog, apply, remove}
}
