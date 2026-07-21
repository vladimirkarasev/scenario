<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Actions\Services;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Module\Actions\Models\Action;
use Module\Actions\Services\DirectorySyncScheduleService;
use Module\Directories\Enums\DirectoryImportSourceType;
use Module\Directories\Models\Directory;
use Module\Projects\Models\Project;
use Tests\TestCase;

final class DirectorySyncSectionTest extends TestCase
{
    use RefreshDatabase;

    private DirectorySyncScheduleService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DirectorySyncScheduleService::class);
    }

    public function test_sync_action_lands_in_system_section(): void
    {
        $directory = $this->makeDirectory();

        $this->service->upsert($directory, enabled: false, cron: null, timezone: null);

        $action = Action::query()->where('code', 'directory_sync_'.str_replace('-', '_', $directory->id))->firstOrFail();

        $this->assertMatchesRegularExpression('/^[a-z][a-z0-9_]*$/', $action->code);
        $this->assertMatchesRegularExpression('/^[a-z][a-z0-9_]*$/', $action->slug);

        $category = Category::query()->where('is_system', true)->where('name', 'Синхронизация справочников')->first();
        $this->assertNotNull($category);

        $this->assertDatabaseHas('model_has_categories', [
            'category_id' => $category->id,
            'model_id' => $action->id,
            'model_type' => Action::class,
        ]);

        $this->assertDatabaseHas('model_has_categories', [
            'category_id' => $category->id,
            'model_id' => $category->id,
            'model_type' => Action::class,
            'project_id' => $directory->project_id,
        ]);
    }

    public function test_system_section_is_reused_across_directories(): void
    {
        $project = $this->makeProject();
        $this->service->upsert($this->makeDirectory($project), enabled: false, cron: null, timezone: null);
        $this->service->upsert($this->makeDirectory($project), enabled: false, cron: null, timezone: null);

        $count = Category::query()
            ->where('is_system', true)
            ->where('name', 'Синхронизация справочников')
            ->count();

        $this->assertSame(1, $count);
    }

    public function test_system_section_registration_count_stays_one(): void
    {
        $directory = $this->makeDirectory();
        $this->service->upsert($directory, enabled: false, cron: null, timezone: null);

        $category = Category::query()->where('is_system', true)->firstOrFail();

        $selfRows = DB::table('model_has_categories')
            ->where('category_id', $category->id)
            ->where('model_id', $category->id)
            ->count();

        $this->assertSame(1, $selfRows);
    }

    private function makeProject(): Project
    {
        return Project::query()->create([
            'name' => 'Proj '.Str::random(4),
            'sitekey' => 'sk-'.Str::random(6),
            'host' => 'example.com',
            'is_active' => true,
        ]);
    }

    private function makeDirectory(?Project $project = null): Directory
    {
        return Directory::query()->create([
            'project_id' => ($project ?? $this->makeProject())->id,
            'name' => 'Dir '.Str::random(4),
            'slug' => 'dir-'.Str::random(6),
            'source_type' => DirectoryImportSourceType::Proxy->value,
        ]);
    }
}
