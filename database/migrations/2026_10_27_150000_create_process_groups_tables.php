<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('process_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->string('flowable_group_id', 80)->unique();
            $table->string('description', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('process_group_user', function (Blueprint $table) {
            $table->foreignId('process_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['process_group_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('process_group_user');
        Schema::dropIfExists('process_groups');
    }
};
