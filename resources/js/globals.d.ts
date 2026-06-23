/* eslint-disable @typescript-eslint/no-unused-vars */
import '@inertiajs/vue3'

declare global {
    function route(name: string, params?: unknown): string
}

interface ImportMetaEnv {
    readonly VITE_APP_NAME: string
    readonly VITE_IFRAME_AUTH_REDIRECT_URL?: string
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
