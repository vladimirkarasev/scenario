<?php

declare(strict_types=1);

namespace Tests\Feature\Scenario\Http;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Module\Users\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class ScenarioSystemVariablesControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function test_index_returns_call_variables_for_calls_scenario(): void
    {
        $user = $this->makeUser('scenario_view');

        $this->actingAs($user)
            ->getJson('/api/scenarios/system-variables?type=colls')
            ->assertOk()
            ->assertJsonPath('data.type', 'colls')
            ->assertJsonPath('data.groups.0.name', '_run')
            ->assertJsonPath('data.groups.3.name', '_call')
            ->assertJsonPath('data.groups.3.fields.0.suffix', 'incoming_phone')
            ->assertJsonStructure(['meta' => ['timestamp', 'requestId']]);
    }

    public function test_index_excludes_call_variables_from_telegram_scenario(): void
    {
        $user = $this->makeUser('scenario_view');

        $this->actingAs($user)
            ->getJson('/api/scenarios/system-variables?type=telegram')
            ->assertOk()
            ->assertJsonPath('data.type', 'telegram')
            ->assertJsonCount(3, 'data.groups')
            ->assertJsonMissing(['name' => '_call']);
    }

    public function test_index_rejects_unknown_scenario_type(): void
    {
        $user = $this->makeUser('scenario_view');

        $this->actingAs($user)
            ->getJson('/api/scenarios/system-variables?type=unknown')
            ->assertUnprocessable()
            ->assertJsonStructure(['errors', 'meta' => ['timestamp', 'requestId']]);
    }

    public function test_index_requires_view_permission(): void
    {
        $this->actingAs($this->makeUser())
            ->getJson('/api/scenarios/system-variables?type=colls')
            ->assertForbidden();
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
