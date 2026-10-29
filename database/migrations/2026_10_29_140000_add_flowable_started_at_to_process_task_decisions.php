<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('process_task_decisions', function (Blueprint $table): void {
            $table->timestamp('flowable_started_at')->nullable()->after('flowable_completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('process_task_decisions', function (Blueprint $table): void {
            $table->dropColumn('flowable_started_at');
        });
    }
};
