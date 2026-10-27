<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('process_definitions', function (Blueprint $table) {
            $table->json('task_form_schemas')->nullable()->after('task_group_assignments');
        });

        Schema::table('process_task_decisions', function (Blueprint $table) {
            $table->string('task_definition_key', 255)->nullable()->after('task_name');
            $table->json('form_data')->nullable()->after('justification');
        });
    }

    public function down(): void
    {
        Schema::table('process_task_decisions', function (Blueprint $table) {
            $table->dropColumn(['task_definition_key', 'form_data']);
        });

        Schema::table('process_definitions', function (Blueprint $table) {
            $table->dropColumn('task_form_schemas');
        });
    }
};
