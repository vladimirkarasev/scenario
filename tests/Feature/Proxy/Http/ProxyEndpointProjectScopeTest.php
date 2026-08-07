<?php

declare(strict_types=1);

namespace Tests\Feature\Proxy\Http;

use Module\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Module\Projects\Models\Project;
use Module\Proxy\Models\ProxyEndpoint;
use Tests\Stubs\Proxy\TestLeadProxyHandler;
use Tests\TestCase;

final class ProxyEndpointProjectScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_binds_endpoint_to_current_project(): void
    {
        [$user, $project] = $this->makeUserWithProject();

        $response = $this->actingAs($user)
            ->postJson('/api/proxy/endpoints', [
                'name' => 'Интеграция',
                'code' => 'integration-1',
                'handler_class' => TestLeadProxyHandler::class,
            ])
            ->assertCreated()
            ->assertJsonPath('data.attributes.project_id', $project->id);

        $this->assertDatabaseHas('proxy_endpoints', [
            'code' => 'integration-1',
            'project_id' => $project->id,
        ]);
    }

    public function test_index_returns_only_current_project_endpoints(): void
    {
        [$user, $project] = $this->makeUserWithProject();
        $other = $this->makeProject();

        $mine = $this->makeEndpoint(projectId: $project->id);
        $this->makeEndpoint(projectId: $other->id);
        $this->makeEndpoint(projectId: null);

        $response = $this->actingAs($user)
            ->getJson('/api/proxy/endpoints')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->assertSame((string) $mine->id, $response->json('data.0.id'));
    }

    public function test_feed_returns_only_current_project_endpoints(): void
    {
        [$user, $project] = $this->makeUserWithProject();
        $other = $this->makeProject();

        $this->makeEndpoint(projectId: $project->id);
        $this->makeEndpoint(projectId: $other->id);

        $this->actingAs($user)
            ->getJson('/api/proxy/feed?filter[parent_id]=null')
            ->assertOk()
            ->assertJsonPath('meta.items_total', 1);
    }

    /** @return array{User, Project} */
    private function makeUserWithProject(): array
    {
        $project = $this->makeProject();

        $user = User::factory()->create([
            'project_id' => $project->id,
            'sitekey' => $project->sitekey,
            'host' => $project->host,
        ]);

        return [$user, $project];
    }

    private function makeProject(): Project
    {
        return Project::query()->create([
            'name' => 'Project '.Str::random(4),
            'sitekey' => 'sk-'.Str::random(8),
            'host' => Str::random(6).'.local',
            'is_active' => true,
        ]);
    }

    private function makeEndpoint(?string $projectId): ProxyEndpoint
    {
        return ProxyEndpoint::query()->create([
            'project_id' => $projectId,
            'uuid' => Str::uuid()->toString(),
            'name' => 'Endpoint '.Str::random(4),
            'code' => 'code-'.Str::random(6),
            'handler_class' => TestLeadProxyHandler::class,
        ]);
    }
}
