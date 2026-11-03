<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_types', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('reference_prefix', 10);
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->json('form_schema')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('workflows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_type_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_default')->default(true);
            $table->timestamps();
        });

        Schema::create('workflow_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained()->cascadeOnDelete();
            $table->string('slug');
            $table->string('label');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('category')->default('todo');
            $table->boolean('is_terminal')->default(false);
            $table->timestamps();

            $table->unique(['workflow_id', 'slug']);
        });

        Schema::create('workflow_transitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_status_id')->constrained('workflow_statuses')->cascadeOnDelete();
            $table->foreignId('to_status_id')->constrained('workflow_statuses')->cascadeOnDelete();
            $table->string('slug');
            $table->string('label');
            $table->string('required_permission')->nullable();
            $table->boolean('requires_comment')->default(false);
            $table->timestamps();

            $table->unique(['workflow_id', 'slug']);
        });

        Schema::create('reference_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_type_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['work_type_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reference_sequences');
        Schema::dropIfExists('workflow_transitions');
        Schema::dropIfExists('workflow_statuses');
        Schema::dropIfExists('workflows');
        Schema::dropIfExists('work_types');
    }
};
