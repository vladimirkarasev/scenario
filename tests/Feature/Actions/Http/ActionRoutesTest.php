<?php

declare(strict_types=1);

namespace Tests\Feature\Actions\Http;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class ActionRoutesTest extends TestCase
{
    public function test_module_api_routes_use_actions_prefix(): void
    {
        $expectedRoutes = [
            'actions.index' => 'api/actions',
            'actions.types' => 'api/actions/types',
            'actions.feed' => 'api/actions/feed',
            'actions.run' => 'api/actions/run',
            'actions.categories.index' => 'api/actions/categories',
            'actions.credentials.index' => 'api/actions/credentials',
            'actions.runs.index' => 'api/actions/runs',
            'actions.schedules.index' => 'api/actions/schedules',
            'actions.show' => 'api/actions/{action}',
            'actions.schedule.show' => 'api/actions/{action}/schedule',
        ];

        foreach ($expectedRoutes as $name => $uri) {
            $this->assertSame($uri, Route::getRoutes()->getByName($name)?->uri());
        }
    }
}
