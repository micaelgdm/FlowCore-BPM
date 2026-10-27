<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('process_instances', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('idempotency_key')->unique();
            $table->foreignId('process_definition_id')->constrained()->restrictOnDelete();
            $table->foreignId('started_by')->constrained('users')->restrictOnDelete();
            $table->string('flowable_process_instance_id')->nullable()->unique();
            $table->string('status', 20)->default('starting')->index();
            $table->json('form_data');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('process_instances');
    }
};
