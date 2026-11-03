<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('notes')->nullable();
            $table->timestamp('held_at');
            $table->foreignId('created_by_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('meeting_work_item', function (Blueprint $table) {
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_item_id')->constrained()->cascadeOnDelete();
            $table->primary(['meeting_id', 'work_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_work_item');
        Schema::dropIfExists('meetings');
    }
};
