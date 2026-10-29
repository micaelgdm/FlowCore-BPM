<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_notification_outbox', function (Blueprint $table): void {
            $table->text('context')->nullable()->after('due_at');
        });
    }

    public function down(): void
    {
        Schema::table('task_notification_outbox', function (Blueprint $table): void {
            $table->dropColumn('context');
        });
    }
};
