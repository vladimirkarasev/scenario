<?php

declare(strict_types=1);

namespace Tests\Feature\Users\Http;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Module\Users\Models\User;
use Tests\TestCase;

final class TokenAuthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_logout_without_bearer_token_is_idempotent(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/auth/logout')
            ->assertOk()
            ->assertJsonStructure(['data' => ['message'], 'meta' => ['timestamp', 'requestId']]);
    }

    public function test_current_user_response_carries_meta(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonStructure([
                'data' => ['id', 'permissions'],
                'meta' => ['timestamp', 'requestId'],
            ]);
    }
}
