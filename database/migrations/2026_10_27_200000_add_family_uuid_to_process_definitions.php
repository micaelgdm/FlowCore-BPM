<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('process_definitions', function (Blueprint $table) {
            $table->uuid('family_uuid')->nullable()->after('version')->index();
        });

        DB::table('process_definitions')->whereNull('family_uuid')->orderBy('id')->get(['id'])->each(function (object $process): void {
            DB::table('process_definitions')->where('id', $process->id)->update(['family_uuid' => (string) Str::uuid()]);
        });
    }

    public function down(): void
    {
        Schema::table('process_definitions', function (Blueprint $table) {
            $table->dropIndex(['family_uuid']);
            $table->dropColumn('family_uuid');
        });
    }
};
