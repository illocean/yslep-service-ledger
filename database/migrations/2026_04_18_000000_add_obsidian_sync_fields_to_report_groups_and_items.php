<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_groups', function (Blueprint $table): void {
            $table->string('obsidian_directory', 2048)->nullable()->after('title');
            $table->string('obsidian_index_note_path', 2048)->nullable()->after('obsidian_directory');
            $table->timestamp('obsidian_last_synced_at')->nullable()->after('obsidian_index_note_path');
        });

        Schema::table('report_group_items', function (Blueprint $table): void {
            $table->uuid('obsidian_record_uuid')->nullable()->after('source_order');
            $table->string('obsidian_note_path', 2048)->nullable()->after('obsidian_record_uuid');
            $table->string('obsidian_note_hash', 64)->nullable()->after('obsidian_note_path');
            $table->timestamp('obsidian_last_synced_at')->nullable()->after('obsidian_note_hash');

            $table->unique('obsidian_record_uuid');
            $table->unique('obsidian_note_path');
        });
    }

    public function down(): void
    {
        Schema::table('report_group_items', function (Blueprint $table): void {
            $table->dropUnique(['obsidian_record_uuid']);
            $table->dropUnique(['obsidian_note_path']);
            $table->dropColumn([
                'obsidian_record_uuid',
                'obsidian_note_path',
                'obsidian_note_hash',
                'obsidian_last_synced_at',
            ]);
        });

        Schema::table('report_groups', function (Blueprint $table): void {
            $table->dropColumn([
                'obsidian_directory',
                'obsidian_index_note_path',
                'obsidian_last_synced_at',
            ]);
        });
    }
};
