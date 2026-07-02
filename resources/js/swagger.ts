import SwaggerUIBundle from 'swagger-ui-dist/swagger-ui-bundle.js'
import SwaggerUIStandalonePreset from 'swagger-ui-dist/swagger-ui-standalone-preset.js'
import 'swagger-ui-dist/swagger-ui.css'
import './swagger.css'

SwaggerUIBundle({
    url: '/openapi.yaml',
    dom_id: '#swagger-ui',
    deepLinking: true,
    displayRequestDuration: true,
    persistAuthorization: true,
    presets: [
        SwaggerUIBundle.presets.apis,
        SwaggerUIStandalonePreset,
    ],
    layout: 'StandaloneLayout',
})
