<?php

declare(strict_types=1);

namespace Tests\Feature\Proxy\Http;

use App\Models\Category;
use Module\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Module\Proxy\Models\ProxyEndpoint;
use Module\Proxy\Proxies\Test\TestLeadProxyHandler;
use Tests\TestCase;

/**
 * HTTP-тесты разделов (категорий) интеграций.
 * Роуты: GET|POST /api/proxy/categories, PUT|DELETE /api/proxy/categories/{category}
 * + фильтрация эндпоинтов по filter[category_ids][] и привязка через category_ids.
 */
final class ProxyCategoryControllerTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithProxyProject;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->createProxyUser();
    }

    // -------------------------------------------------------------------------
    // CRUD разделов
    // -------------------------------------------------------------------------

    public function test_store_creates_section_and_returns_it_in_index(): void
    {
        $this->actingAs($this->user)
            ->postJson('/api/proxy/categories', ['name' => 'CRM', 'is_active' => true])
            ->assertCreated()
            ->assertJsonPath('data.attributes.name', 'CRM');

        $this->actingAs($this->user)
            ->getJson('/api/proxy/categories')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.name', 'CRM');
    }

    public function test_store_links_section_to_proxy_model_type(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/proxy/categories', ['name' => 'Раздел', 'is_active' => true])
            ->assertCreated();

        $this->assertDatabaseHas('model_has_categories', [
            'category_id' => $response->json('data.id'),
            'model_type' => ProxyEndpoint::class,
        ]);
    }

    public function test_index_filters_by_parent_id(): void
    {
        $parent = $this->makeSection('Родитель');
        $this->makeSection('Дочерний', parentId: $parent->id);
        $this->makeSection('Другой корень');

        $this->actingAs($this->user)
            ->getJson("/api/proxy/categories?filter[parent_id]={$parent->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.name', 'Дочерний');
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/proxy/categories')->assertUnauthorized();
    }

    public function test_update_renames_section(): void
    {
        $section = $this->makeSection('Старое');

        $this->actingAs($this->user)
            ->putJson("/api/proxy/categories/{$section->id}", ['name' => 'Новое', 'is_active' => true])
            ->assertOk()
            ->assertJsonPath('data.attributes.name', 'Новое');

        $this->assertDatabaseHas('categories', ['id' => $section->id, 'name' => 'Новое']);
    }

    public function test_destroy_removes_section(): void
    {
        $section = $this->makeSection('Удаляемый');

        $this->actingAs($this->user)
            ->deleteJson("/api/proxy/categories/{$section->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('categories', ['id' => $section->id]);
    }

    public function test_destroy_blocks_system_section(): void
    {
        $section = $this->makeSection('Системный', isSystem: true);

        $this->actingAs($this->user)
            ->deleteJson("/api/proxy/categories/{$section->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('categories', ['id' => $section->id]);
    }

    // -------------------------------------------------------------------------
    // Привязка эндпоинтов к разделам
    // -------------------------------------------------------------------------

    public function test_store_endpoint_assigns_categories(): void
    {
        $section = $this->makeSection('CRM');

        $response = $this->actingAs($this->user)
            ->postJson('/api/proxy/endpoints', [
                'name' => 'Интеграция',
                'code' => 'integration-1',
                'handler_class' => TestLeadProxyHandler::class,
                'category_ids' => [$section->id],
            ])
            ->assertCreated()
            ->assertJsonPath('data.attributes.category_ids', [$section->id]);

        $this->assertDatabaseHas('model_has_categories', [
            'category_id' => $section->id,
            'model_id' => $response->json('data.attributes.uuid'),
            'model_type' => ProxyEndpoint::class,
        ]);
    }

    public function test_update_endpoint_syncs_categories(): void
    {
        $first = $this->makeSection('Первый');
        $second = $this->makeSection('Второй');
        $endpoint = $this->makeEndpoint();
        $endpoint->categories()->sync([$first->id]);

        $this->actingAs($this->user)
            ->putJson("/api/proxy/endpoints/{$endpoint->id}", [
                'name' => $endpoint->name,
                'code' => $endpoint->code,
                'handler_class' => $endpoint->handler_class,
                'category_ids' => [$second->id],
            ])
            ->assertOk()
            ->assertJsonPath('data.attributes.category_ids', [$second->id]);
    }

    public function test_index_filters_endpoints_by_category(): void
    {
        $section = $this->makeSection('CRM');
        $inSection = $this->makeEndpoint();
        $inSection->categories()->sync([$section->id]);
        $this->makeEndpoint(); // без раздела

        $response = $this->actingAs($this->user)
            ->getJson("/api/proxy/endpoints?filter[category_ids][]={$section->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->assertSame((string) $inSection->id, $response->json('data.0.id'));
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeSection(string $name, ?string $parentId = null, bool $isSystem = false): Category
    {
        $category = Category::query()->create([
            'id' => (string) Str::uuid(),
            'name' => $name,
            'parent_id' => $parentId,
            'is_active' => true,
            'is_system' => $isSystem,
        ]);

        DB::table('model_has_categories')->insertOrIgnore([
            'category_id' => $category->id,
            'model_id' => $category->id,
            'model_type' => ProxyEndpoint::class,
            'project_id' => $this->proxyProject->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $category;
    }

    private function makeEndpoint(): ProxyEndpoint
    {
        return ProxyEndpoint::query()->create([
            'project_id' => $this->proxyProject->id,
            'uuid' => Str::uuid()->toString(),
            'name' => 'Endpoint '.Str::random(4),
            'code' => 'code-'.Str::random(6),
            'handler_class' => TestLeadProxyHandler::class,
        ]);
    }
}
