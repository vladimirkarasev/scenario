<?php

declare(strict_types=1);

namespace Tests\Feature\Proxy\Http;

use App\Models\Category;
use Module\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Module\Proxy\Models\ProxyEndpoint;
use Tests\Stubs\Proxy\TestLeadProxyHandler;
use Tests\TestCase;

final class ProxyFeedControllerTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithProxyProject;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->createProxyUser();
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
            ->assertJsonPath('meta.folders_total', 1)
            ->assertJsonPath('meta.items_total', 1);

        $types = array_column($response->json('data'), 'type');
        $this->assertSame(['proxy-folders', 'proxy-endpoints'], $types);
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
            ->assertJsonPath('meta.items_total', 1)
            ->assertJsonPath('meta.folders_total', 0);

        $this->assertSame('proxy-endpoints', $response->json('data.0.type'));
        $this->assertSame((string) $inside->id, $response->json('data.0.id'));
    }

    public function test_feed_search_matches_endpoint_name_and_code(): void
    {
        $this->makeEndpoint('AutoCRM лиды', code: 'autocrm-leads');
        $this->makeEndpoint('Прочее', code: 'other');

        $response = $this->actingAs($this->user)
            ->getJson('/api/proxy/feed?filter[search]=autocrm')
            ->assertOk()
            ->assertJsonPath('meta.items_total', 1);

        $this->assertSame('autocrm-leads', $response->json('data.0.attributes.code'));
    }

    public function test_feed_endpoint_row_contains_expected_fields(): void
    {
        $this->makeEndpoint('Интеграция', code: 'int-1');

        $row = $this->actingAs($this->user)
            ->getJson('/api/proxy/feed?filter[parent_id]=null')
            ->assertOk()
            ->json('data.0');

        foreach (['uuid', 'name', 'code', 'is_active', 'is_mocked', 'base_uri'] as $key) {
            $this->assertArrayHasKey($key, $row['attributes']);
        }
        $this->assertSame('proxy-endpoints', $row['type']);
    }

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
            'project_id' => $this->proxyProject->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $category;
    }

    private function makeEndpoint(string $name, ?string $code = null): ProxyEndpoint
    {
        return ProxyEndpoint::query()->create([
            'project_id' => $this->proxyProject->id,
            'uuid' => Str::uuid()->toString(),
            'name' => $name,
            'code' => $code ?? 'code-'.Str::random(6),
            'handler_class' => TestLeadProxyHandler::class,
        ]);
    }
}
