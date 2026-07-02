<?php

declare(strict_types=1);

namespace Tests\Feature\Users;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Module\Users\Models\Role;
use Module\Users\Models\User;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

final class UserMorphMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_user_morph_types_are_normalized_without_duplicates(): void
    {
        $user = User::factory()->create();
        $role = Role::query()->create(['name' => 'legacy-role', 'guard_name' => 'web']);
        $permission = Permission::query()->create(['name' => 'legacy-permission', 'guard_name' => 'web']);
        $token = $user->createToken('legacy-token')->accessToken;

        DB::table('model_has_roles')->insert([
            'role_id' => $role->id,
            'model_type' => 'App\\Models\\User',
            'model_id' => $user->id,
        ]);
        DB::table('model_has_permissions')->insert([
            'permission_id' => $permission->id,
            'model_type' => 'App\\Models\\User',
            'model_id' => $user->id,
        ]);
        DB::table('personal_access_tokens')
            ->where('id', $token->id)
            ->update(['tokenable_type' => 'App\\Models\\User']);

        $migration = require database_path('migrations/2026_07_01_000200_normalize_user_morph_types.php');
        $migration->up();
        $migration->up();

        $this->assertDatabaseMissing('model_has_roles', ['model_type' => 'App\\Models\\User']);
        $this->assertDatabaseMissing('model_has_permissions', ['model_type' => 'App\\Models\\User']);
        $this->assertDatabaseHas('model_has_roles', [
            'role_id' => $role->id,
            'model_type' => User::class,
            'model_id' => $user->id,
        ]);
        $this->assertDatabaseHas('model_has_permissions', [
            'permission_id' => $permission->id,
            'model_type' => User::class,
            'model_id' => $user->id,
        ]);
        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $token->id,
            'tokenable_type' => User::class,
        ]);
    }
}
