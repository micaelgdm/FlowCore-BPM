<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_task_delegations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('delegate_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'starts_at', 'ends_at'], 'user_task_delegations_window_index');
        });

        Schema::create('task_delegation_transfers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_task_delegation_id')->constrained()->restrictOnDelete();
            $table->foreignId('process_instance_id')->constrained()->cascadeOnDelete();
            $table->string('flowable_task_id', 120)->unique();
            $table->foreignId('original_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('delegate_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('transferred_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['delegate_user_id', 'completed_at'], 'task_delegation_delegate_open_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_delegation_transfers');
        Schema::dropIfExists('user_task_delegations');
    }
};
