<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_notification_outbox', function (Blueprint $table): void {
            $table->dropUnique('task_notification_recipient_unique');
            $table->string('notification_type', 24)->default('assigned')->after('flowable_task_id');
            $table->timestamp('due_at')->nullable()->after('task_name');
            $table->unique(['flowable_task_id', 'recipient_user_id', 'notification_type'], 'task_notification_event_unique');
        });

        Schema::table('process_instances', function (Blueprint $table): void {
            $table->timestamp('task_deadline_notifications_checked_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('process_instances', function (Blueprint $table): void {
            $table->dropColumn('task_deadline_notifications_checked_at');
        });

        Schema::table('task_notification_outbox', function (Blueprint $table): void {
            $table->dropUnique('task_notification_event_unique');
            $table->dropColumn(['notification_type', 'due_at']);
            $table->unique(['flowable_task_id', 'recipient_user_id'], 'task_notification_recipient_unique');
        });
    }
};
