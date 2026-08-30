<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 0.2 — sync-state metadata for live entries.
     *
     * Adds three columns used by the future bidirectional sync layer to
     * track how a live record relates to the Obsidian vault. Existing rows
     * are NOT backfilled here — the next sync will populate them. This
     * keeps the migration cheap and side-effect free.
     *
     * Columns:
     *   - obsidian_content_hash    sha256 of the rendered vault body cell
     *                              (nullable; the next pull fills it)
     *   - obsidian_last_synced_at  timestamp of the most recent successful
     *                              reconcile against the vault (nullable)
     *   - obsidian_last_source     'laravel' | 'vault' | null — which side
     *                              was the most recent authoritative change
     */
    public function up(): void
    {
        foreach (['formation_entries', 'parish_involvement_entries', 'social_apostolate_entries'] as $table) {
            Schema::table($table, function (Blueprint $schema) use ($table): void {
                $schema->string('obsidian_content_hash', 64)->nullable()->after('obsidian_record_uuid');
                $schema->timestamp('obsidian_last_synced_at')->nullable()->after('obsidian_content_hash');
                $schema->string('obsidian_last_source', 16)->nullable()->after('obsidian_last_synced_at');
                $schema->index('obsidian_last_synced_at', $table.'_obsidian_last_synced_at_index');
            });
        }
    }

    public function down(): void
    {
        foreach (['formation_entries', 'parish_involvement_entries', 'social_apostolate_entries'] as $table) {
            Schema::table($table, function (Blueprint $schema) use ($table): void {
                $schema->dropIndex($table.'_obsidian_last_synced_at_index');
                $schema->dropColumn(['obsidian_content_hash', 'obsidian_last_synced_at', 'obsidian_last_source']);
            });
        }
    }
};
