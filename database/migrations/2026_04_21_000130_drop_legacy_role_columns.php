<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'role')) {
            DB::table('users')
                ->select(['id', 'role'])
                ->whereNotNull('role')
                ->where('role', '!=', '')
                ->orderBy('id')
                ->lazy()
                ->each(function (object $user): void {
                    $role = Role::findOrCreate($user->role, 'web');

                    DB::table('model_has_roles')->updateOrInsert([
                        'role_id' => $role->id,
                        'model_type' => 'Module\\Users\\Models\\User',
                        'model_id' => $user->id,
                    ], []);
                });
        }

        Schema::table('users', static function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'role')) {
                $table->dropColumn('role');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', static function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'role')) {
                $table->string('role')->nullable()->after('login');
            }
        });
    }
};
