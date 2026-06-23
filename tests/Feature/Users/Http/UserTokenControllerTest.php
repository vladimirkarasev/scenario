<?php

declare(strict_types=1);

namespace Tests\Feature\Users\Http;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Module\Projects\Models\Project;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * HTTP-тесты управления API-токенами пользователя.
 * Роуты: GET|POST /api/users/{user}/tokens
 *        DELETE /api/users/{user}/tokens/{tokenId}
 */
final class UserTokenControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    // -------------------------------------------------------------------------
    // GET /api/users/{user}/tokens
    // -------------------------------------------------------------------------

    /**
     * Список токенов пользователя возвращается в data.
     */
    public function test_index_returns_user_tokens(): void
    {
        [$actor] = $this->makeUserWithProject('user_view');
        $target  = User::factory()->create();
        $target->createToken('Token Alpha');
        $target->createToken('Token Beta');

        $this->actingAs($actor)
            ->getJson("/api/users/{$target->id}/tokens")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    /**
     * Без токенов — data пуст.
     */
    public function test_index_returns_empty_data_when_no_tokens(): void
    {
        [$actor] = $this->makeUserWithProject('user_view');
        $target  = User::factory()->create();

        $this->actingAs($actor)
            ->getJson("/api/users/{$target->id}/tokens")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    /**
     * Без пермишена user_view — 403.
     */
    public function test_index_returns_403_without_permission(): void
    {
        [$actor] = $this->makeUserWithProject();
        $target  = User::factory()->create();

        $this->actingAs($actor)
            ->getJson("/api/users/{$target->id}/tokens")
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // POST /api/users/{user}/tokens
    // -------------------------------------------------------------------------

    /**
     * Создание токена — 201, plain_text_token присутствует в ответе.
     */
    public function test_store_creates_token_and_returns_plain_text(): void
    {
        [$actor] = $this->makeUserWithProject('user_create');
        $target  = User::factory()->create();

        $response = $this->actingAs($actor)
            ->postJson("/api/users/{$target->id}/tokens", ['name' => 'My Token'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'My Token');

        $this->assertNotNull($response->json('data.plain_text_token'));
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id'   => $target->id,
            'tokenable_type' => User::class,
            'name'           => 'My Token',
        ]);
    }

    /**
     * Пустое тело (нет name) — 422.
     */
    public function test_store_returns_422_when_name_missing(): void
    {
        [$actor] = $this->makeUserWithProject('user_create');
        $target  = User::factory()->create();

        $this->actingAs($actor)
            ->postJson("/api/users/{$target->id}/tokens", [])
            ->assertUnprocessable();
    }

    /**
     * Без пермишена user_create — 403.
     */
    public function test_store_returns_403_without_permission(): void
    {
        [$actor] = $this->makeUserWithProject('user_view');
        $target  = User::factory()->create();

        $this->actingAs($actor)
            ->postJson("/api/users/{$target->id}/tokens", ['name' => 'Token'])
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // DELETE /api/users/{user}/tokens/{tokenId}
    // -------------------------------------------------------------------------

    /**
     * Удаление токена — 204, токен исчезает из БД.
     */
    public function test_destroy_deletes_token_and_returns_204(): void
    {
        [$actor] = $this->makeUserWithProject('user_create');
        $target  = User::factory()->create();
        $issued  = $target->createToken('To Delete');
        $tokenId = $issued->accessToken->id;

        $this->actingAs($actor)
            ->deleteJson("/api/users/{$target->id}/tokens/{$tokenId}")
            ->assertNoContent();

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenId]);
    }

    /**
     * Несуществующий tokenId — 204 (идемпотентное удаление).
     */
    public function test_destroy_is_idempotent_for_nonexistent_token(): void
    {
        [$actor] = $this->makeUserWithProject('user_create');
        $target  = User::factory()->create();

        $this->actingAs($actor)
            ->deleteJson("/api/users/{$target->id}/tokens/999999")
            ->assertNoContent();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** @return array{User, Project} */
    private function makeUserWithProject(string ...$permissions): array
    {
        $project = $this->makeProject();
        $actor   = User::factory()->create([
            'sitekey' => $project->sitekey,
            'host'    => $project->host,
        ]);

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
            $actor->givePermissionTo($permission);
        }

        return [$actor, $project];
    }

    private function makeProject(): Project
    {
        return Project::query()->create([
            'name'          => 'Project ' . Str::random(4),
            'sitekey'       => 'sk-' . Str::random(6),
            'host'          => Str::random(4) . '.local',
            'shared_secret' => Str::random(32),
            'is_active'     => true,
        ]);
    }
}
