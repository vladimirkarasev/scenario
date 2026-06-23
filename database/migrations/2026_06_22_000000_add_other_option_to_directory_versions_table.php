<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('directory_versions', static function (Blueprint $table): void {
            $table->boolean('allow_other')->default(false)->after('sync_options');
            $table->string('other_label')->nullable()->after('allow_other');
            $table->string('other_external_key')->nullable()->after('other_label');
        });
    }

    public function down(): void
    {
        Schema::table('directory_versions', static function (Blueprint $table): void {
            $table->dropColumn(['allow_other', 'other_label', 'other_external_key']);
        });
    }
};
