import type {Router} from '@inertiajs/core'
import type {Component, Plugin} from 'vue'

declare module '@inertiajs/vue3' {
    export const router: Router

    export function usePage<T extends Record<string, unknown> = Record<string, unknown>>(): {
        props: T & { auth?: unknown }
        url: string
        component: string
        version: string | null
    }

    export function createInertiaApp(options: {
        title?: (title: string) => string
        resolve: (name: string) => unknown
        setup: (options: {
            el: HTMLElement
            App: Component
            props: Record<string, unknown>
            plugin: Plugin
        }) => unknown
        progress?: {
            color?: string
            delay?: number
            includeCSS?: boolean
            showSpinner?: boolean
        } | false
    }): Promise<void>
}
