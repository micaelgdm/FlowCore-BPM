<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('process_user_access', function (Blueprint $table) {
            $table->boolean('can_view')->default(true)->after('user_id');
            $table->boolean('can_start')->default(true)->after('can_view');
            $table->boolean('can_approve')->default(false)->after('can_start');
        });
    }

    public function down(): void
    {
        Schema::table('process_user_access', function (Blueprint $table) {
            $table->dropColumn(['can_view', 'can_start', 'can_approve']);
        });
    }
};
