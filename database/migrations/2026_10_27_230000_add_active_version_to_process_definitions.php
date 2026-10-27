<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('process_definitions', function (Blueprint $table): void {
            $table->boolean('is_active')->default(false)->after('status')->index();
        });

        $latestPublishedIds = DB::table('process_definitions')
            ->selectRaw('MAX(id) as id')
            ->where('status', 'published')
            ->groupBy('family_uuid')
            ->pluck('id');

        DB::table('process_definitions')->whereIn('id', $latestPublishedIds)->update(['is_active' => true]);
    }

    public function down(): void
    {
        Schema::table('process_definitions', function (Blueprint $table): void {
            $table->dropColumn('is_active');
        });
    }
};
