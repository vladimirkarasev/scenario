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
        Schema::table('actions', static function (Blueprint $table): void {
            $table->string('code')->nullable()->after('key');
        });

        DB::table('actions')->orderBy('id')->each(static function (object $row): void {
            DB::table('actions')->where('id', $row->id)->update([
                'code' => is_string($row->key) ? $row->key : (string) $row->id,
            ]);
        });

        Schema::table('actions', static function (Blueprint $table): void {
            $table->unique('code');
        });
    }

    public function down(): void
    {
        Schema::table('actions', static function (Blueprint $table): void {
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });
    }
};
