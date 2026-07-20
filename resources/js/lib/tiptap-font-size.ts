import {TextStyle} from '@tiptap/extension-text-style'

declare module '@tiptap/core' {
    interface Commands<ReturnType> {
        fontSize: {
            setFontSize: (fontSize: string) => ReturnType
            unsetFontSize: () => ReturnType
        }
    }
}

// Расширяет стандартный textStyle mark атрибутом fontSize — тот же mark, что
// использует Color (extension-color), поэтому подключается вместо обычного
// TextStyle, а не рядом с ним. Управляется из bubble-menu тем же способом,
// что цвет/заливка — через выделение текста, без отдельных контролов на ноде.
export const FontSize = TextStyle.extend({
    name: 'textStyle',

    addAttributes() {
        return {
            ...this.parent?.(),
            fontSize: {
                default: null,
                parseHTML: (element) => element.style.fontSize || null,
                renderHTML: (attributes) => {
                    if (!attributes.fontSize) return {}
                    return {style: `font-size: ${attributes.fontSize}`}
                },
            },
        }
    },

    addCommands() {
        return {
            ...this.parent?.(),
            setFontSize: (fontSize: string) => ({chain}) => chain().setMark('textStyle', {fontSize}).run(),
            unsetFontSize: () => ({chain}) => chain().setMark('textStyle', {fontSize: null}).run(),
        }
    },
})
