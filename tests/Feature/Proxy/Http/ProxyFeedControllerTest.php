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
 * HTTP-тесты ленты интеграций (разделы сверху + интеграции, единая пагинация).
 * Роут: GET /api/proxy/feed
 */
final class ProxyFeedControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/proxy/feed')->assertUnauthorized();
    }

    public function test_root_feed_lists_root_folders_and_unsectioned_endpoints(): void
    {
        $this->makeSection('CRM');
        $this->makeEndpoint('Без раздела');

        $response = $this->actingAs($this->user)
            ->getJson('/api/proxy/feed?filter[parent_id]=null')
            ->assertOk()
            ->assertJsonPath('pagination.folders_total', 1)
            ->assertJsonPath('pagination.items_total', 1);

        $types = array_column($response->json('data'), 'type');
        $this->assertSame(['folder', 'endpoint'], $types); // папки всегда сверху
    }

    public function test_feed_inside_section_returns_only_its_endpoints(): void
    {
        $section = $this->makeSection('CRM');
        $inside = $this->makeEndpoint('Внутри');
        $inside->categories()->sync([$section->id]);
        $this->makeEndpoint('Снаружи');

        $response = $this->actingAs($this->user)
            ->getJson("/api/proxy/feed?filter[parent_id]={$section->id}")
            ->assertOk()
            ->assertJsonPath('pagination.items_total', 1)
            ->assertJsonPath('pagination.folders_total', 0);

        $this->assertSame('endpoint', $response->json('data.0.type'));
        $this->assertSame($inside->id, $response->json('data.0.id'));
    }

    public function test_feed_search_matches_endpoint_name_and_code(): void
    {
        $this->makeEndpoint('AutoCRM лиды', code: 'autocrm-leads');
        $this->makeEndpoint('Прочее', code: 'other');

        $response = $this->actingAs($this->user)
            ->getJson('/api/proxy/feed?filter[search]=autocrm')
            ->assertOk()
            ->assertJsonPath('pagination.items_total', 1);

        $this->assertSame('autocrm-leads', $response->json('data.0.code'));
    }

    public function test_feed_endpoint_row_contains_expected_fields(): void
    {
        $this->makeEndpoint('Интеграция', code: 'int-1');

        $row = $this->actingAs($this->user)
            ->getJson('/api/proxy/feed?filter[parent_id]=null')
            ->assertOk()
            ->json('data.0');

        foreach (['type', 'id', 'uuid', 'name', 'code', 'is_active', 'is_mocked', 'base_uri'] as $key) {
            $this->assertArrayHasKey($key, $row);
        }
        $this->assertSame('endpoint', $row['type']);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeSection(string $name): Category
    {
        $category = Category::query()->create([
            'id' => (string) Str::uuid(),
            'name' => $name,
            'parent_id' => null,
            'is_active' => true,
        ]);

        DB::table('model_has_categories')->insertOrIgnore([
            'category_id' => $category->id,
            'model_id' => $category->id,
            'model_type' => ProxyEndpoint::class,
            'project_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $category;
    }

    private function makeEndpoint(string $name, ?string $code = null): ProxyEndpoint
    {
        return ProxyEndpoint::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => $name,
            'code' => $code ?? 'code-'.Str::random(6),
            'handler_class' => TestLeadProxyHandler::class,
        ]);
    }
}
