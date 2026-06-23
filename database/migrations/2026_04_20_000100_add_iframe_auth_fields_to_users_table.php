<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('sitekey')->nullable()->after('password');
            $table->string('host')->nullable()->after('sitekey');
            $table->string('login')->nullable()->after('host');
            $table->string('role')->nullable()->after('login');

            $table->index(['sitekey', 'login']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['sitekey', 'login']);
            $table->dropColumn(['sitekey', 'host', 'login', 'role']);
        });
    }
};
