<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_items', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number')->unique();
            $table->foreignId('work_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workflow_status_id')->constrained('workflow_statuses')->restrictOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('priority')->default('normal');
            $table->foreignId('requester_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->json('custom_fields')->nullable();
            $table->boolean('confidential')->default(false);
            $table->timestamps();

            $table->index(['assignee_id', 'workflow_status_id']);
            $table->index(['workspace_id', 'workflow_status_id']);
            $table->index(['department_id', 'due_at']);
            $table->index(['work_type_id', 'created_at']);
        });

        Schema::create('work_item_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_by_id')->constrained('users')->cascadeOnDelete();
            $table->text('reason')->nullable();
            $table->timestamps();
        });

        Schema::create('activity_events', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->foreignId('causer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event');
            $table->json('properties')->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_events');
        Schema::dropIfExists('work_item_assignments');
        Schema::dropIfExists('work_items');
    }
};
