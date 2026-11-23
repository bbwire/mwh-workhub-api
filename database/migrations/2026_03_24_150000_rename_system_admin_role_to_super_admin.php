<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        DB::table('roles')
            ->where('name', 'system_admin')
            ->where('guard_name', 'web')
            ->update(['name' => 'super_admin']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        DB::table('roles')
            ->where('name', 'super_admin')
            ->where('guard_name', 'web')
            ->update(['name' => 'system_admin']);
    }
};
