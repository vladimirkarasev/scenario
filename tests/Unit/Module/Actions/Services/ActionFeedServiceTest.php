<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Actions\Services;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Module\Actions\DTO\ActionFeedData;
use Module\Actions\Models\Action;
use Module\Actions\Services\ActionFeedService;
use Tests\TestCase;

/**
 * Feed отдаёт смешанный поток "разделы + действия" с единой пагинацией, навигация по parent_id.
 */
final class ActionFeedServiceTest extends TestCase
{
    use RefreshDatabase;

    private ActionFeedService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ActionFeedService::class);
    }

    public function test_root_feed_returns_root_folders_and_uncategorized_actions(): void
    {
        $category = $this->makeCategory('CRM');
        $inSection = $this->makeAction('in-section');
        $inSection->categories()->sync([$category->id]);
        $this->makeAction('loose');

        $feed = $this->service->feed(null, new ActionFeedData(
            parentSet: true, parentId: null, search: null, page: 1, perPage: 20,
        ));

        $this->assertSame(1, $feed['pagination']['folders_total']);
        $this->assertSame(1, $feed['pagination']['items_total']); // только uncategorized

        $types = array_column($feed['data'], 'type');
        $this->assertSame(['folder', 'action'], $types); // папки сверху
        $this->assertSame('loose', $feed['data'][1]['name']);
    }

    public function test_section_feed_returns_actions_of_that_section(): void
    {
        $category = $this->makeCategory('CRM');
        $a = $this->makeAction('crm-export');
        $a->categories()->sync([$category->id]);
        $this->makeAction('loose');

        $feed = $this->service->feed(null, new ActionFeedData(
            parentSet: true, parentId: $category->id, search: null, page: 1, perPage: 20,
        ));

        $this->assertSame(0, $feed['pagination']['folders_total']);
        $this->assertSame(1, $feed['pagination']['items_total']);
        $this->assertSame('crm-export', $feed['data'][0]['name']);
        $this->assertSame('action', $feed['data'][0]['type']);
    }

    private function makeCategory(string $name): Category
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
            'model_type' => Action::class,
            'project_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $category;
    }

    private function makeAction(string $slug): Action
    {
        return Action::query()->create([
            'name' => $slug,
            'slug' => $slug,
            'code' => str_replace('-', '_', $slug),
            'type' => 'template_file',
            'is_active' => true,
            'config' => [],
        ]);
    }
}
