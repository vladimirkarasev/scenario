export type CopyStrategy = 'auto' | 'clipboard' | 'event'

export const DEFAULT_COPY_STRATEGY: CopyStrategy = 'auto'

let fallbackText: string | null = null
let programmaticCopyActive = false

export function isProgrammaticCopyActive(): boolean {
    return programmaticCopyActive
}

export function rememberCopiedText(text: string): void {
    fallbackText = text
}

function copyWithEvent(text: string): boolean {
    if (typeof document === 'undefined' || typeof document.execCommand !== 'function') {
        return false
    }

    const textarea = document.createElement('textarea')
    textarea.value = text
    textarea.readOnly = true
    textarea.style.position = 'fixed'
    textarea.style.opacity = '0'
    textarea.style.pointerEvents = 'none'

    let eventHandled = false
    const handleCopy = (event: ClipboardEvent): void => {
        if (!event.clipboardData) {
            return
        }

        event.clipboardData.setData('text/plain', text)
        event.preventDefault()
        event.stopPropagation()
        eventHandled = true
    }

    document.body.appendChild(textarea)
    textarea.select()
    document.addEventListener('copy', handleCopy)
    programmaticCopyActive = true

    try {
        return document.execCommand('copy') || eventHandled
    } finally {
        programmaticCopyActive = false
        document.removeEventListener('copy', handleCopy)
        textarea.remove()
    }
}

async function copyWithClipboard(text: string): Promise<boolean> {
    if (!hasClipboardWriter()) {
        return false
    }

    try {
        await navigator.clipboard.writeText(text)
        return true
    } catch {
        return false
    }
}

function hasClipboardWriter(): boolean {
    return typeof navigator !== 'undefined' && Boolean(navigator.clipboard?.writeText)
}

export async function copyText(
    text: string,
    strategy: CopyStrategy = DEFAULT_COPY_STRATEGY,
): Promise<boolean> {
    rememberCopiedText(text)

    if (strategy === 'clipboard') {
        return copyWithClipboard(text)
    }

    if (strategy === 'event') {
        return copyWithEvent(text)
    }

    if (!hasClipboardWriter()) {
        return copyWithEvent(text)
    }

    return await copyWithClipboard(text) || copyWithEvent(text)
}

export async function readText(
    strategy: CopyStrategy = DEFAULT_COPY_STRATEGY,
): Promise<string | null> {
    if (strategy !== 'event' && typeof navigator !== 'undefined' && navigator.clipboard?.readText) {
        try {
            return await navigator.clipboard.readText()
        } catch {
            return fallbackText
        }
    }

    return fallbackText
}
