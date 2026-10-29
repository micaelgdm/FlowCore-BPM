<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_notification_outbox', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('process_instance_id')->constrained()->cascadeOnDelete();
            $table->string('flowable_task_id', 120);
            $table->foreignId('recipient_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('recipient_email');
            $table->string('recipient_name');
            $table->string('process_name');
            $table->string('task_name');
            $table->string('request_code', 32);
            $table->string('status', 20)->default('queued')->index();
            $table->timestamp('sent_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->unique(['flowable_task_id', 'recipient_user_id'], 'task_notification_recipient_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_notification_outbox');
    }
};
