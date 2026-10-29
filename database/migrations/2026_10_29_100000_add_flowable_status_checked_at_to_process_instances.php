<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('process_instances', function (Blueprint $table): void {
            $table->timestamp('flowable_status_checked_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('process_instances', function (Blueprint $table): void {
            $table->dropColumn('flowable_status_checked_at');
        });
    }
};
