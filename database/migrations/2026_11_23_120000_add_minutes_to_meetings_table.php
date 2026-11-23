<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->longText('minutes')->nullable()->after('notes');
        });

        DB::table('meetings')
            ->whereNotNull('notes')
            ->where('notes', '!=', '')
            ->update(['minutes' => DB::raw('notes')]);
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropColumn('minutes');
        });
    }
};
