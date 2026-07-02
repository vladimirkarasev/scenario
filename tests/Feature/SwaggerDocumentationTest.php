<?php

namespace Tests\Feature;

use Tests\TestCase;

final class SwaggerDocumentationTest extends TestCase
{
    public function testSwaggerUiIsAvailable(): void
    {
        $this->get('/swagger')
            ->assertOk()
            ->assertSee('id="swagger-ui"', false)
            ->assertSee('Scenario API — Swagger UI');
    }

    public function testOldScalarUrlRedirectsToSwagger(): void
    {
        $this->get('/scalar')
            ->assertMovedPermanently()
            ->assertRedirect('/swagger');
    }

    public function testOpenApiDocumentIsAvailable(): void
    {
        $this->get('/openapi.yaml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/yaml');

        self::assertFileExists(public_path('openapi.yaml'));
    }
}
