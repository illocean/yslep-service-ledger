<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('formation_entries', function (Blueprint $table): void {
            $table->uuid('obsidian_record_uuid')->nullable()->after('source_order');
            $table->unique('obsidian_record_uuid');
        });

        Schema::table('parish_involvement_entries', function (Blueprint $table): void {
            $table->uuid('obsidian_record_uuid')->nullable()->after('source_order');
            $table->unique('obsidian_record_uuid');
        });

        Schema::table('social_apostolate_entries', function (Blueprint $table): void {
            $table->uuid('obsidian_record_uuid')->nullable()->after('source_order');
            $table->unique('obsidian_record_uuid');
        });
    }

    public function down(): void
    {
        Schema::table('social_apostolate_entries', function (Blueprint $table): void {
            $table->dropUnique(['obsidian_record_uuid']);
            $table->dropColumn('obsidian_record_uuid');
        });

        Schema::table('parish_involvement_entries', function (Blueprint $table): void {
            $table->dropUnique(['obsidian_record_uuid']);
            $table->dropColumn('obsidian_record_uuid');
        });

        Schema::table('formation_entries', function (Blueprint $table): void {
            $table->dropUnique(['obsidian_record_uuid']);
            $table->dropColumn('obsidian_record_uuid');
        });
    }
};
