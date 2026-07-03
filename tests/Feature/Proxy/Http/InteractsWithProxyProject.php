<?php

declare(strict_types=1);

namespace Tests\Feature\Proxy\Http;

use Illuminate\Support\Str;
use Module\Projects\Models\Project;
use Module\Users\Models\User;

trait InteractsWithProxyProject
{
    protected Project $proxyProject;

    protected function createProxyUser(): User
    {
        $this->proxyProject = Project::query()->create([
            'name' => 'Proxy test project',
            'sitekey' => 'proxy-'.Str::random(12),
            'host' => Str::random(12).'.test',
            'is_active' => true,
        ]);

        return User::factory()->create([
            'project_id' => $this->proxyProject->id,
            'sitekey' => $this->proxyProject->sitekey,
            'host' => $this->proxyProject->host,
        ]);
    }
}
