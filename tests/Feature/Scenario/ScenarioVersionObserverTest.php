<?php

declare(strict_types=1);

namespace Tests\Feature\Scenario;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Module\Scenario\Enums\ScenarioStatus;
use Module\Scenario\Models\Scenario;
use Module\Scenario\Models\ScenarioVersion;
use Tests\TestCase;

final class ScenarioVersionObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_activating_version_sets_scenario_status_to_active(): void
    {
        $scenario = $this->makeScenario('draft');
        $version = $this->makeVersion($scenario, 'draft');

        $version->update(['status' => 'active']);

        $scenario->refresh();

        $this->assertSame(ScenarioStatus::Active, $scenario->status);
        $this->assertSame($version->id, $scenario->active_version_id);
    }

    public function test_activating_second_version_replaces_active_version_id(): void
    {
        $scenario = $this->makeScenario('active');
        $first = $this->makeVersion($scenario, 'active');
        $scenario->update(['active_version_id' => $first->id]);

        $second = $this->makeVersion($scenario, 'draft');

        $second->update(['status' => 'active']);

        $scenario->refresh();

        $this->assertSame(ScenarioStatus::Active, $scenario->status);
        $this->assertSame($second->id, $scenario->active_version_id);
    }

    public function test_deactivating_only_active_version_sets_scenario_to_draft(): void
    {
        $scenario = $this->makeScenario('active');
        $version = $this->makeVersion($scenario, 'active');
        $scenario->update(['active_version_id' => $version->id]);

        $version->update(['status' => 'draft']);

        $scenario->refresh();

        $this->assertSame(ScenarioStatus::Draft, $scenario->status);
        $this->assertNull($scenario->active_version_id);
    }

    public function test_deactivating_one_version_keeps_scenario_active_via_other(): void
    {
        $scenario = $this->makeScenario('active');
        $first = $this->makeVersion($scenario, 'active');
        $second = $this->makeVersion($scenario, 'active');
        $scenario->update(['active_version_id' => $first->id]);

        $first->update(['status' => 'draft']);

        $scenario->refresh();

        $this->assertSame(ScenarioStatus::Active, $scenario->status);
        $this->assertSame($second->id, $scenario->active_version_id);
    }

    public function test_archiving_only_active_version_sets_scenario_to_draft(): void
    {
        $scenario = $this->makeScenario('active');
        $version = $this->makeVersion($scenario, 'active');
        $scenario->update(['active_version_id' => $version->id]);

        $version->update(['status' => 'archived']);

        $scenario->refresh();

        $this->assertSame(ScenarioStatus::Draft, $scenario->status);
        $this->assertNull($scenario->active_version_id);
    }

    public function test_updating_version_name_does_not_change_scenario_status(): void
    {
        $scenario = $this->makeScenario('draft');
        $version = $this->makeVersion($scenario, 'draft');

        $version->update(['name' => 'Новое название']);

        $scenario->refresh();

        $this->assertSame(ScenarioStatus::Draft, $scenario->status);
        $this->assertNull($scenario->active_version_id);
    }

    private function makeScenario(string $status = 'draft'): Scenario
    {
        return Scenario::query()->create([
            'name' => 'Тест-сценарий',
            'is_active' => true,
            'status' => $status,
        ]);
    }

    private function makeVersion(Scenario $scenario, string $status = 'draft'): ScenarioVersion
    {
        return ScenarioVersion::query()->create([
            'id' => (string)Str::uuid(),
            'scenario_id' => $scenario->id,
            'status' => $status,
        ]);
    }
}
