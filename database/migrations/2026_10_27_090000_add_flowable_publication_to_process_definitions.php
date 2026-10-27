<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('process_definitions', function (Blueprint $table) {
            $table->string('flowable_deployment_id')->nullable()->after('bpmn_xml');
            $table->string('flowable_process_definition_id')->nullable()->after('flowable_deployment_id');
            $table->char('published_bpmn_hash', 64)->nullable()->after('flowable_process_definition_id');
            $table->timestamp('published_at')->nullable()->after('published_bpmn_hash');
        });
    }

    public function down(): void
    {
        Schema::table('process_definitions', function (Blueprint $table) {
            $table->dropColumn([
                'flowable_deployment_id',
                'flowable_process_definition_id',
                'published_bpmn_hash',
                'published_at',
            ]);
        });
    }
};
