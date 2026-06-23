<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $map = [
            'webhook_view'   => 'proxy_view',
            'webhook_create' => 'proxy_create',
            'webhook_delete' => 'proxy_delete',
        ];

        foreach ($map as $old => $new) {
            DB::table('permissions')->where('name', $old)->update(['name' => $new]);
        }
    }

    public function down(): void
    {
        $map = [
            'proxy_view'   => 'webhook_view',
            'proxy_create' => 'webhook_create',
            'proxy_delete' => 'webhook_delete',
        ];

        foreach ($map as $old => $new) {
            DB::table('permissions')->where('name', $old)->update(['name' => $new]);
        }
    }
};
