declare module 'swagger-ui-dist/swagger-ui-bundle.js' {
    import type {SwaggerUIBundle} from 'swagger-ui-dist'

    const bundle: SwaggerUIBundle
    export default bundle
}

declare module 'swagger-ui-dist/swagger-ui-standalone-preset.js' {
    const preset: unknown
    export default preset
}
