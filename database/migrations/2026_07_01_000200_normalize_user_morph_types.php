<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const string OLD_TYPE = 'App\\Models\\User';

    private const string NEW_TYPE = 'Module\\Users\\Models\\User';

    public function up(): void
    {
        $this->migratePivotType(
            'model_has_roles',
            ['role_id', 'model_id'],
            self::OLD_TYPE,
            self::NEW_TYPE,
        );
        $this->migratePivotType(
            'model_has_permissions',
            ['permission_id', 'model_id'],
            self::OLD_TYPE,
            self::NEW_TYPE,
        );

        DB::table('personal_access_tokens')
            ->where('tokenable_type', self::OLD_TYPE)
            ->update(['tokenable_type' => self::NEW_TYPE]);
    }

    public function down(): void
    {
        $this->migratePivotType(
            'model_has_roles',
            ['role_id', 'model_id'],
            self::NEW_TYPE,
            self::OLD_TYPE,
        );
        $this->migratePivotType(
            'model_has_permissions',
            ['permission_id', 'model_id'],
            self::NEW_TYPE,
            self::OLD_TYPE,
        );

        DB::table('personal_access_tokens')
            ->where('tokenable_type', self::NEW_TYPE)
            ->update(['tokenable_type' => self::OLD_TYPE]);
    }

    /** @param list<string> $keyColumns */
    private function migratePivotType(
        string $table,
        array $keyColumns,
        string $from,
        string $to,
    ): void {
        $rows = DB::table($table)
            ->where('model_type', $from)
            ->get([...$keyColumns, 'model_type']);

        foreach ($rows as $row) {
            $attributes = ['model_type' => $to];

            foreach ($keyColumns as $column) {
                $attributes[$column] = $row->{$column};
            }

            DB::table($table)->insertOrIgnore($attributes);
        }

        DB::table($table)->where('model_type', $from)->delete();
    }
};
