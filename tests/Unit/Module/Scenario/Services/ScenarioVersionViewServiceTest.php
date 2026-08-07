<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Scenario\Services;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Module\Scenario\DTO\ScenarioVersionHistoryData;
use Module\Scenario\Models\Scenario;
use Module\Scenario\Models\ScenarioVersion;
use Module\Scenario\Models\ScenarioVersionRevision;
use Module\Scenario\Services\ScenarioVersionViewService;
use Tests\TestCase;

final class ScenarioVersionViewServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_editor_payload_contains_latest_schema_without_revision_list(): void
    {
        $version = $this->makeVersion();
        $this->makeRevision($version, 'first');
        $this->makeRevision($version, 'latest');

        $payload = app(ScenarioVersionViewService::class)->editor($version);

        $this->assertSame(['marker' => 'latest'], $payload['schema_json']);
        $this->assertArrayNotHasKey('revisions', $payload);
    }

    public function test_history_is_paginated_and_does_not_return_schema(): void
    {
        $version = $this->makeVersion();
        $this->makeRevision($version, 'first');
        $this->makeRevision($version, 'second');
        $this->makeRevision($version, 'third');

        $result = app(ScenarioVersionViewService::class)->history(
            $version,
            new ScenarioVersionHistoryData(page: 2, perPage: 2),
        );

        $this->assertCount(1, $result['revisions']);
        $this->assertSame(2, $result['pagination']['current_page']);
        $this->assertSame(3, $result['pagination']['total']);
        $this->assertArrayNotHasKey('schema_json', $result['revisions'][0]);
    }

    private function makeVersion(): ScenarioVersion
    {
        $scenario = Scenario::query()->create(['name' => 'Scenario '.Str::random(4), 'is_active' => true]);

        return ScenarioVersion::query()->create([
            'id' => (string)Str::uuid(),
            'scenario_id' => $scenario->id,
            'name' => 'v1',
            'status' => 'draft',
        ]);
    }

    private function makeRevision(ScenarioVersion $version, string $marker): void
    {
        ScenarioVersionRevision::query()->create([
            'scenario_version_id' => $version->id,
            'schema_json' => ['marker' => $marker],
            'nodes_json' => [],
            'edges_json' => [],
            'schema_version' => 1,
            'created_at' => now()->addSeconds($version->revisions()->count()),
        ]);
    }
}
