<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Carbon;
use Module\Scenario\Models\ScenarioVersion;
use Module\Scenario\Models\ScenarioVersionRevision;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        putenv('DB_CONNECTION=sqlite');
        putenv('DB_DATABASE=:memory:');
        putenv('DB_HOST=127.0.0.1');
        putenv('DB_PORT=');
        putenv('DB_USERNAME=');
        putenv('DB_PASSWORD=');
        $_ENV['DB_CONNECTION'] = 'sqlite';
        $_ENV['DB_DATABASE'] = ':memory:';
        $_SERVER['DB_CONNECTION'] = 'sqlite';
        $_SERVER['DB_DATABASE'] = ':memory:';

        parent::setUp();

        config([
            'cache.default' => 'array',
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'dev_auth.enabled' => false,
            'mail.default' => 'array',
            'queue.default' => 'sync',
            'session.driver' => 'array',
        ]);
    }

    /** @param  array<string, mixed>  $content */
    protected function createRevision(ScenarioVersion $version, array $content = []): ScenarioVersionRevision
    {
        $latestCreatedAt = ScenarioVersionRevision::query()
            ->where('scenario_version_id', $version->id)
            ->latest('created_at')
            ->value('created_at');

        $createdAt = $content['created_at']
            ?? ($latestCreatedAt ? Carbon::parse($latestCreatedAt)->addSecond() : now());

        return ScenarioVersionRevision::query()->create([
            'scenario_version_id' => $version->id,
            'schema_json' => $content['schema_json'] ?? ['nodes' => [], 'edges' => []],
            'nodes_json' => $content['nodes_json'] ?? [],
            'edges_json' => $content['edges_json'] ?? [],
            'schema_version' => $content['schema_version'] ?? 1,
            'created_at' => $createdAt,
        ]);
    }
}
