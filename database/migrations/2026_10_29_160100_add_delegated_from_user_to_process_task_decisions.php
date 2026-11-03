<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('process_task_decisions', function (Blueprint $table): void {
            $table->foreignId('delegated_from_user_id')
                ->nullable()
                ->after('decided_by')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('process_task_decisions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('delegated_from_user_id');
        });
    }
};
