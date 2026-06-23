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
        Schema::table('scenarios', static function (Blueprint $table): void {
            $table->json('tags')->nullable()->after('name');
            $table->string('alias')->nullable()->after('name');
        });

        foreach (DB::table('scenarios')->select('id', 'tag', 'aliases')->cursor() as $row) {
            $aliases = is_string($row->aliases) ? json_decode($row->aliases, true) : null;
            $alias = is_array($aliases) && isset($aliases[0]) && is_string($aliases[0]) ? $aliases[0] : null;

            DB::table('scenarios')
                ->where('id', $row->id)
                ->update([
                    'alias' => $alias,
                    'tags'  => $row->tag !== null && $row->tag !== '' ? json_encode([$row->tag]) : null,
                ]);
        }

        Schema::table('scenarios', static function (Blueprint $table): void {
            $table->dropColumn(['tag', 'aliases']);
            $table->unique('alias', 'scenarios_alias_unique');
        });
    }

    public function down(): void
    {
        Schema::table('scenarios', static function (Blueprint $table): void {
            $table->string('tag')->nullable()->after('name');
            $table->json('aliases')->nullable()->after('name');
        });

        foreach (DB::table('scenarios')->select('id', 'alias', 'tags')->cursor() as $row) {
            $tags = is_string($row->tags) ? json_decode($row->tags, true) : null;
            $tag = is_array($tags) && isset($tags[0]) && is_string($tags[0]) ? $tags[0] : null;

            DB::table('scenarios')
                ->where('id', $row->id)
                ->update([
                    'tag'     => $tag,
                    'aliases' => $row->alias !== null && $row->alias !== '' ? json_encode([$row->alias]) : null,
                ]);
        }

        Schema::table('scenarios', static function (Blueprint $table): void {
            $table->dropUnique('scenarios_alias_unique');
            $table->dropColumn(['alias', 'tags']);
        });
    }
};
