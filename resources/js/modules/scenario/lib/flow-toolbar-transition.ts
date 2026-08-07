function asHtmlElement(element: Element): HTMLElement {
    return element as HTMLElement
}

export function onFlowToolbarEnter(element: Element): void {
    const toolbar = asHtmlElement(element)
    toolbar.style.overflow = 'hidden'
    toolbar.style.height = '0'
    toolbar.style.opacity = '0'
    toolbar.style.transform = 'translateY(-4px)'

    requestAnimationFrame(() => {
        toolbar.style.transition = 'height 0.25s cubic-bezier(0.4,0,0.2,1), opacity 0.2s ease, transform 0.25s cubic-bezier(0.4,0,0.2,1)'
        toolbar.style.height = `${toolbar.scrollHeight}px`
        toolbar.style.opacity = '1'
        toolbar.style.transform = 'translateY(0)'
    })
}

export function onFlowToolbarAfterTransition(element: Element): void {
    const toolbar = asHtmlElement(element)
    toolbar.style.transition = ''
    toolbar.style.height = ''
    toolbar.style.overflow = ''
    toolbar.style.opacity = ''
    toolbar.style.transform = ''
}

export function onFlowToolbarLeave(element: Element): void {
    const toolbar = asHtmlElement(element)
    toolbar.style.overflow = 'hidden'
    toolbar.style.height = `${toolbar.scrollHeight}px`
    toolbar.style.opacity = '1'
    toolbar.style.transform = 'translateY(0)'

    requestAnimationFrame(() => {
        toolbar.style.transition = 'height 0.25s cubic-bezier(0.4,0,0.2,1), opacity 0.2s ease, transform 0.25s cubic-bezier(0.4,0,0.2,1)'
        toolbar.style.height = '0'
        toolbar.style.opacity = '0'
        toolbar.style.transform = 'translateY(-4px)'
    })
}
