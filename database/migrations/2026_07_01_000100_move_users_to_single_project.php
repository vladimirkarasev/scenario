<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $usersWithMultipleProjects = DB::table('project_users')
            ->select('user_id')
            ->groupBy('user_id')
            ->havingRaw('COUNT(DISTINCT project_id) > 1')
            ->pluck('user_id');

        if ($usersWithMultipleProjects->isNotEmpty()) {
            throw new RuntimeException(
                'Нельзя автоматически перевести пользователей в один проект. '
                .'Несколько проектов найдены у user_id: '.$usersWithMultipleProjects->implode(', '),
            );
        }

        DB::statement(
            'UPDATE users SET project_id = ('
            .'SELECT pu.project_id FROM project_users pu WHERE pu.user_id = users.id'
            .') WHERE project_id IS NULL '
            .'AND EXISTS (SELECT 1 FROM project_users pu WHERE pu.user_id = users.id)'
        );

        // Снимаем глобальные unique, чтобы уникальность стала per-project. Идемпотентно
        // (email-unique мог быть снят ранее). На SQLite (тесты) inline unique — auto-index,
        // ALTER для него не поддерживается, поэтому дропы только для реальной СУБД.
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_email_unique');
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_external_id_unique');
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->unique(['project_id', 'email']);
            $table->unique(['project_id', 'login']);
            $table->unique(['project_id', 'external_id']);
        });

        Schema::dropIfExists('project_users');
        Schema::dropIfExists('project_user_roles');
    }

    public function down(): void
    {
        Schema::create('project_users', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('project_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'project_id']);
        });

        Schema::create('project_user_roles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('project_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->timestamps();

            $table->unique(['user_id', 'project_id', 'role']);
        });

        DB::statement(
            'INSERT INTO project_users (user_id, project_id, created_at, updated_at) '
            .'SELECT id, project_id, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP '
            .'FROM users WHERE project_id IS NOT NULL'
        );

        $assignments = DB::table('model_has_roles')
            ->join('users', 'users.id', '=', 'model_has_roles.model_id')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->whereIn('model_has_roles.model_type', [
                'Module\\Users\\Models\\User',
                'App\\Models\\User',
            ])
            ->whereNotNull('users.project_id')
            ->get([
                'users.id as user_id',
                'users.project_id',
                'roles.name as role',
            ]);

        foreach ($assignments as $assignment) {
            DB::table('project_user_roles')->insertOrIgnore([
                'user_id' => $assignment->user_id,
                'project_id' => $assignment->project_id,
                'role' => $assignment->role,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['project_id', 'email']);
            $table->dropUnique(['project_id', 'login']);
            $table->dropUnique(['project_id', 'external_id']);

            // email-unique восстанавливает миграция update_users_table_email_column; здесь только external_id.
            $table->unique('external_id');
        });
    }
};
