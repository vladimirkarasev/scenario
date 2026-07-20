<?php

declare(strict_types=1);

namespace Tests\Feature\Seeders;

use Database\Seeders\DemoDirectorySeeder;
use Database\Seeders\DemoProjectSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Module\Directories\Models\Directory;
use Module\Directories\Models\DirectoryItem;
use Module\Projects\Models\Project;
use Tests\TestCase;

final class DemoDirectorySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_twenty_varied_directories_with_large_and_small_trees(): void
    {
        $this->seed([DemoProjectSeeder::class, DemoDirectorySeeder::class]);

        $project = Project::query()->where('sitekey', DemoProjectSeeder::SITEKEY)->firstOrFail();

        $this->assertSame(20, Directory::query()->where('project_id', $project->id)->count());
        $this->assertSame(20, DB::table('model_has_categories')
            ->where('model_type', Directory::class)
            ->where('project_id', $project->id)
            ->count());

        $geography = Directory::query()->where('project_id', $project->id)
            ->where('slug', 'delivery-geography')
            ->firstOrFail();
        $geographyVersion = $geography->activeVersion()->firstOrFail();

        $this->assertGreaterThan(300, $geographyVersion->items()->count());
        $this->assertTrue($geographyVersion->items()->whereNotNull('parent_id')->exists());

        $district = $geographyVersion->items()
            ->where('external_key', 'geo-RU-r01-c01-d01')
            ->firstOrFail();
        $this->assertNotNull($district->parent?->parent?->parent_id);

        $paymentMethods = Directory::query()->where('project_id', $project->id)
            ->where('slug', 'payment-methods')
            ->firstOrFail();

        $this->assertCount(6, $paymentMethods->activeVersion()->firstOrFail()->items);
        $this->assertTrue($paymentMethods->activeVersion()->firstOrFail()->allow_other);

        $fieldTypes = Directory::query()
            ->where('project_id', $project->id)
            ->with('activeVersion')
            ->get()
            ->flatMap(static function (Directory $directory): array {
                $version = $directory->activeVersion;

                return $version === null ? [] : array_column($version->schema_json, 'type');
            })
            ->unique()
            ->sort()
            ->values()
            ->all();

        $this->assertSame(['boolean', 'date', 'datetime', 'integer', 'string'], $fieldTypes);
        $itemCount = DirectoryItem::query()->count();
        $this->assertGreaterThan(800, $itemCount);

        $this->seed(DemoDirectorySeeder::class);

        $this->assertSame(20, Directory::query()->where('project_id', $project->id)->count());
        $this->assertSame($itemCount, DirectoryItem::query()->count());
    }
}
