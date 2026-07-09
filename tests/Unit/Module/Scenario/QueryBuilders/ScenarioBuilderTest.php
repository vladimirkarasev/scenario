<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Scenario\QueryBuilders;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Module\Groups\Models\UserGroup;
use Module\Projects\Models\Project;
use Module\Scenario\Models\Scenario;
use Module\Users\Models\User;
use Tests\TestCase;

final class ScenarioBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_groups_do_not_grant_scenario_visibility(): void
    {
        $project = Project::query()->create([
            'name' => 'Project',
            'sitekey' => 'sk-'.Str::random(6),
            'host' => Str::random(4).'.local',
            'shared_secret' => Str::random(32),
            'is_active' => true,
        ]);
        $user = User::factory()->create(['project_id' => $project->id]);
        $activeGroup = $this->makeGroup($project, 'active-group', true);
        $inactiveGroup = $this->makeGroup($project, 'inactive-group', false);
        $user->groups()->attach([$activeGroup->id, $inactiveGroup->id]);

        $visibleScenario = Scenario::query()->create([
            'project_id' => $project->id,
            'name' => 'Visible',
            'is_active' => true,
        ]);
        $hiddenScenario = Scenario::query()->create([
            'project_id' => $project->id,
            'name' => 'Hidden',
            'is_active' => true,
        ]);
        $visibleScenario->groups()->attach($activeGroup);
        $hiddenScenario->groups()->attach($inactiveGroup);

        $ids = Scenario::query()->visibleForUser($user)->pluck('id');

        $this->assertTrue($ids->contains($visibleScenario->id));
        $this->assertFalse($ids->contains($hiddenScenario->id));
    }

    private function makeGroup(Project $project, string $slug, bool $active): UserGroup
    {
        return UserGroup::query()->create([
            'site_id' => $project->id,
            'name' => $slug,
            'slug' => $slug,
            'is_active' => $active,
        ]);
    }
}
