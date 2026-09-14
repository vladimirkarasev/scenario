<?php

declare(strict_types=1);

namespace Tests\Feature\Scenario\Http;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Module\Users\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class ScenarioNodeTypesControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function test_index_returns_question_instead_of_block_for_telegram(): void
    {
        $response = $this->actingAs($this->makeUser('scenario_view'))
            ->getJson('/api/scenarios/node-types?type=telegram')
            ->assertOk()
            ->assertJsonPath('data.type', 'telegram')
            ->assertJsonStructure(['meta' => ['timestamp', 'requestId']]);

        $types = $response->json('data.nodes.*.type');

        $this->assertContains('question', $types);
        $this->assertNotContains('block', $types);
    }

    public function test_index_returns_block_without_question_for_calls(): void
    {
        $response = $this->actingAs($this->makeUser('scenario_view'))
            ->getJson('/api/scenarios/node-types?type=colls')
            ->assertOk();

        $types = $response->json('data.nodes.*.type');

        $this->assertContains('block', $types);
        $this->assertNotContains('question', $types);
    }

    public function test_index_rejects_unknown_scenario_type(): void
    {
        $this->actingAs($this->makeUser('scenario_view'))
            ->getJson('/api/scenarios/node-types?type=unknown')
            ->assertUnprocessable()
            ->assertJsonStructure(['errors', 'meta' => ['timestamp', 'requestId']]);
    }

    private function makeUser(string ...$permissions): User
    {
        $user = User::factory()->create();

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
            $user->givePermissionTo($permission);
        }

        return $user;
    }
}
