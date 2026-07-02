<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var array<string, list<string>> */
    private const PERMISSIONS = [
        'user_update' => ['user_create'],
        'user_token_view' => ['user_view', 'user_create'],
        'user_token_manage' => ['user_create'],
    ];

    public function up(): void
    {
        $now = now();

        foreach (array_keys(self::PERMISSIONS) as $permission) {
            DB::table('permissions')->insertOrIgnore([
                'name' => $permission,
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach (self::PERMISSIONS as $newPermission => $sourcePermissions) {
            $newId = DB::table('permissions')
                ->where('name', $newPermission)
                ->where('guard_name', 'web')
                ->value('id');

            if (!is_numeric($newId)) {
                continue;
            }

            foreach ($sourcePermissions as $sourcePermission) {
                $sourceId = DB::table('permissions')
                    ->where('name', $sourcePermission)
                    ->where('guard_name', 'web')
                    ->value('id');

                if (!is_numeric($sourceId)) {
                    continue;
                }

                DB::table('role_has_permissions')
                    ->where('permission_id', (int)$sourceId)
                    ->pluck('role_id')
                    ->each(static function (int|string $roleId) use ($newId): void {
                        DB::table('role_has_permissions')->insertOrIgnore([
                            'permission_id' => (int)$newId,
                            'role_id' => (int)$roleId,
                        ]);
                    });

                DB::table('model_has_permissions')
                    ->where('permission_id', (int)$sourceId)
                    ->get(['model_type', 'model_id'])
                    ->each(static function (object $assignment) use ($newId): void {
                        DB::table('model_has_permissions')->insertOrIgnore([
                            'permission_id' => (int)$newId,
                            'model_type' => $assignment->model_type,
                            'model_id' => $assignment->model_id,
                        ]);
                    });
            }
        }
    }

    public function down(): void
    {
        DB::table('permissions')
            ->where('guard_name', 'web')
            ->whereIn('name', array_keys(self::PERMISSIONS))
            ->delete();
    }
};
