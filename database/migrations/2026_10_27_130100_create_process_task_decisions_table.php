<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('process_task_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('process_instance_id')->constrained()->cascadeOnDelete();
            $table->string('flowable_task_id')->unique();
            $table->string('task_name', 255);
            $table->foreignId('decided_by')->constrained('users')->restrictOnDelete();
            $table->string('decision', 20)->default('approved');
            $table->text('justification');
            $table->timestamp('flowable_completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('process_task_decisions');
    }
};
