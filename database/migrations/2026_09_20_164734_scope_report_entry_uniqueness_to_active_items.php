<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('report_group_items', function (Blueprint $table): void {
            $table->dropUnique('report_group_items_source_unique');
        });

        DB::statement('CREATE UNIQUE INDEX report_group_items_source_live_unique ON report_group_items (index_type, source_entry_id) WHERE deleted_at IS NULL');
    }

    /**
     * Restoring the old constraint fails safely if deleted entries have since been reused.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX report_group_items_source_live_unique');

        Schema::table('report_group_items', function (Blueprint $table): void {
            $table->unique(['index_type', 'source_entry_id'], 'report_group_items_source_unique');
        });
    }
};
