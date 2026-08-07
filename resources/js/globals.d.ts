/* eslint-disable @typescript-eslint/no-unused-vars */
import '@inertiajs/vue3'
import type * as Ymaps3 from '@yandex/ymaps3-types'

declare global {
    function route(name: string, params?: unknown): string

    interface Window {
        ymaps3: typeof Ymaps3
    }
}

interface ImportMetaEnv {
    readonly VITE_APP_NAME: string
    readonly VITE_EMBED_AUTH_REDIRECT_URL?: string
    readonly VITE_YANDEX_MAPS_API_KEY?: string
    readonly VITE_YANDEX_MAPS_ROUTER_API_KEY?: string
}

interface ImportMeta {
    readonly env: ImportMetaEnv
}

declare module '@inertiajs/vue3' {
    interface PageProps {
        auth: {
            user: {
                id: number
                name: string
                email: string
                sitekey: string | null
                host: string | null
                login: string | null
                roles: string[]
            } | null
        }
    }
}

export {}
