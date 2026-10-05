<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (['formation_entries', 'parish_involvement_entries', 'social_apostolate_entries', 'report_group_items', 'academic_year_snapshot_items'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->string('academic_year', 9)->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['formation_entries', 'parish_involvement_entries', 'social_apostolate_entries', 'report_group_items', 'academic_year_snapshot_items'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropColumn('academic_year');
            });
        }
    }
};
