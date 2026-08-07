<?php

declare(strict_types=1);

namespace Tests\Feature\Seeders;

use Database\Seeders\DevSeeder;
use Database\Seeders\Fixtures\DevProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Module\Groups\Models\UserGroup;
use Module\Projects\Models\Project;
use Module\Users\Models\User;
use Tests\TestCase;

final class DevSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_two_projects_with_administrators_operators_and_groups(): void
    {
        $this->seed(DevSeeder::class);
        $this->seed(DevSeeder::class);

        $this->assertDatabaseCount('projects', 2);
        $this->assertProjectStructure(DevProject::Alfa);
        $this->assertProjectStructure(DevProject::Beta);
    }

    private function assertProjectStructure(DevProject $definition): void
    {
        $projectId = $definition->id();
        $project = Project::query()->whereKey($projectId)->firstOrFail();
        $administrator = User::query()
            ->where('project_id', $projectId)
            ->where('login', 'admin')
            ->firstOrFail();
        $operators = User::query()
            ->where('project_id', $projectId)
            ->whereIn('login', ['operator-1', 'operator-2', 'operator-3'])
            ->orderBy('login')
            ->get();
        $operatorOne = $operators->firstWhere('login', 'operator-1');
        $operatorTwo = $operators->firstWhere('login', 'operator-2');
        $operatorThree = $operators->firstWhere('login', 'operator-3');

        self::assertSame($definition->label(), $project->name);
        self::assertTrue($administrator->hasRole('administrator'));
        self::assertCount(3, $operators);
        self::assertInstanceOf(User::class, $operatorOne);
        self::assertInstanceOf(User::class, $operatorTwo);
        self::assertInstanceOf(User::class, $operatorThree);
        self::assertTrue($operators->every(
            static fn (User $operator): bool => $operator->hasRole('operator'),
        ));
        self::assertSame(2, UserGroup::query()->where('site_id', $projectId)->count());
        self::assertSame(1, $operatorOne->groups()->count());
        self::assertSame(1, $operatorTwo->groups()->count());
        self::assertSame(2, $operatorThree->groups()->count());
    }
}
