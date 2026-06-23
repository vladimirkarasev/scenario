import type {DefineComponent} from 'vue'

declare module '@vue/runtime-core' {
    interface ComponentCustomProperties {
        route: (name: string, params?: unknown) => string
    }
}

export {}
