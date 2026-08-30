<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 0.1 — soft-delete on every syncable table.
     *
     * Adds `deleted_at` to the 3 live-entry tables, report_group_items, and
     * academic_year_snapshot_items. Existing live queries are unaffected
     * because Laravel's SoftDeletes trait scopes default queries to
     * `WHERE deleted_at IS NULL` only when explicitly used; existing code
     * that does not use SoftDeletes continues to see all rows.
     *
     * For the 3 live-entry tables and report_group_items, the existing
     * UNIQUE constraint on `obsidian_record_uuid` is replaced with a
     * PARTIAL UNIQUE INDEX that only applies to live rows. This allows a
     * soft-deleted record's UUID to be safely re-introduced from the vault
     * (resurrection semantics — the same row comes back to life).
     */
    public function up(): void
    {
        $this->addSoftDeleteToLiveEntries();
        $this->addSoftDeleteToReportGroupItems();
        $this->addSoftDeleteToAcademicYearSnapshotItems();
    }

    public function down(): void
    {
        $this->revertLiveEntryUniques();
        $this->revertReportGroupItemUniques();
        $this->revertAcademicYearSnapshotItems();

        Schema::table('formation_entries', function (Blueprint $table): void {
            $table->dropIndex('formation_entries_deleted_at_index');
            $table->dropSoftDeletes();
        });

        Schema::table('parish_involvement_entries', function (Blueprint $table): void {
            $table->dropIndex('parish_involvement_entries_deleted_at_index');
            $table->dropSoftDeletes();
        });

        Schema::table('social_apostolate_entries', function (Blueprint $table): void {
            $table->dropIndex('social_apostolate_entries_deleted_at_index');
            $table->dropSoftDeletes();
        });

        Schema::table('report_group_items', function (Blueprint $table): void {
            $table->dropIndex('report_group_items_deleted_at_index');
            $table->dropSoftDeletes();
        });

        Schema::table('academic_year_snapshot_items', function (Blueprint $table): void {
            $table->dropIndex('academic_year_snapshot_items_deleted_at_index');
            $table->dropSoftDeletes();
        });
    }

    private function addSoftDeleteToLiveEntries(): void
    {
        foreach (['formation_entries', 'parish_involvement_entries', 'social_apostolate_entries'] as $table) {
            Schema::table($table, function (Blueprint $schema) use ($table): void {
                $schema->softDeletes();
                $schema->index('deleted_at', $table.'_deleted_at_index');
            });

            $uniqueName = $table.'_obsidian_record_uuid_unique';
            $partialName = $table.'_obsidian_record_uuid_live_unique';

            Schema::table($table, function (Blueprint $schema) use ($uniqueName): void {
                $schema->dropUnique($uniqueName);
            });

            DB::statement(
                "CREATE UNIQUE INDEX {$partialName} ON {$table} (obsidian_record_uuid) WHERE deleted_at IS NULL"
            );
        }
    }

    private function addSoftDeleteToReportGroupItems(): void
    {
        Schema::table('report_group_items', function (Blueprint $schema): void {
            $schema->softDeletes();
            $schema->index('deleted_at', 'report_group_items_deleted_at_index');
        });

        Schema::table('report_group_items', function (Blueprint $schema): void {
            $schema->dropUnique('report_group_items_obsidian_record_uuid_unique');
            $schema->dropUnique('report_group_items_obsidian_note_path_unique');
        });

        DB::statement(
            'CREATE UNIQUE INDEX report_group_items_obsidian_record_uuid_live_unique '
            .'ON report_group_items (obsidian_record_uuid) WHERE deleted_at IS NULL'
        );

        DB::statement(
            'CREATE UNIQUE INDEX report_group_items_obsidian_note_path_live_unique '
            .'ON report_group_items (obsidian_note_path) WHERE deleted_at IS NULL'
        );
    }

    private function addSoftDeleteToAcademicYearSnapshotItems(): void
    {
        Schema::table('academic_year_snapshot_items', function (Blueprint $schema): void {
            $schema->softDeletes();
            $schema->index('deleted_at', 'academic_year_snapshot_items_deleted_at_index');
        });
    }

    private function revertLiveEntryUniques(): void
    {
        foreach (['formation_entries', 'parish_involvement_entries', 'social_apostolate_entries'] as $table) {
            $partialName = $table.'_obsidian_record_uuid_live_unique';
            $uniqueName = $table.'_obsidian_record_uuid_unique';

            DB::statement("DROP INDEX IF EXISTS {$partialName}");

            Schema::table($table, function (Blueprint $schema) use ($uniqueName): void {
                $schema->unique('obsidian_record_uuid', $uniqueName);
            });
        }
    }

    private function revertReportGroupItemUniques(): void
    {
        DB::statement('DROP INDEX IF EXISTS report_group_items_obsidian_record_uuid_live_unique');
        DB::statement('DROP INDEX IF EXISTS report_group_items_obsidian_note_path_live_unique');

        Schema::table('report_group_items', function (Blueprint $schema): void {
            $schema->unique('obsidian_record_uuid', 'report_group_items_obsidian_record_uuid_unique');
            $schema->unique('obsidian_note_path', 'report_group_items_obsidian_note_path_unique');
        });
    }

    private function revertAcademicYearSnapshotItems(): void
    {
        // No unique rewrites needed for this table — only the deleted_at column.
    }
};
