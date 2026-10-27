<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('process_definitions', function (Blueprint $table) {
            $table->json('gateway_routes')->nullable()->after('task_form_schemas');
        });
    }

    public function down(): void
    {
        Schema::table('process_definitions', function (Blueprint $table) {
            $table->dropColumn('gateway_routes');
        });
    }
};
