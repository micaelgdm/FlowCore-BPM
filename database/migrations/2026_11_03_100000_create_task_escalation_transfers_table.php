<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_escalation_transfers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('process_instance_id')->constrained()->cascadeOnDelete();
            $table->string('flowable_task_id', 120)->unique();
            $table->string('original_assignee')->nullable();
            $table->foreignId('escalated_to_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('transferred_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['escalated_to_user_id', 'completed_at'], 'task_escalation_assignee_open_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_escalation_transfers');
    }
};
