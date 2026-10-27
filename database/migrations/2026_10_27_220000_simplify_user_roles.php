<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->whereIn('role', ['requester', 'process_admin', 'approver', 'consultant'])
            ->update(['role' => 'user']);

        Schema::table('users', function (Blueprint $table): void {
            $table->string('role', 40)->default('user')->change();
        });
    }

    public function down(): void
    {
        DB::table('users')->where('role', 'user')->update(['role' => 'requester']);

        Schema::table('users', function (Blueprint $table): void {
            $table->string('role', 40)->default('requester')->change();
        });
    }
};
