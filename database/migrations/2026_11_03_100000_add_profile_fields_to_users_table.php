<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('email');
            $table->string('employee_id')->nullable()->unique()->after('phone');
            $table->boolean('is_active')->default(true)->after('employee_id');
            $table->foreignId('primary_department_id')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'employee_id', 'is_active', 'primary_department_id']);
        });
    }
};
