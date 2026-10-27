<?php

/**
 * Recovery script: Import report groups and academic year snapshots from the Obsidian vault.
 *
 * Usage: php artisan recover:vault-data
 */

namespace App\Console\Commands;

use App\Enums\IndexType;
use App\Models\AcademicYearSnapshot;
use App\Models\AcademicYearSnapshotItem;
use App\Models\ReportGroup;
use App\Models\ReportGroupItem;
use App\Services\ObsidianSyncService;
use App\Services\ReportGroupService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Spatie\YamlFrontMatter\YamlFrontMatter;

class RecoverVaultDataCommand extends Command
{
    protected $signature = 'recover:vault-data';

    protected $description = 'Import report groups and academic year snapshots from the Obsidian vault.';

    public function handle(): int
    {
        $vaultPath = config('obsidian.vault_path');
        $reportsDir = $vaultPath.'/REPORTS';
        $snapshotsFile = $vaultPath.'/ACADEMIC YEAR SNAPSHOTS.md';

        $this->info('Starting vault data recovery...');
        $this->info('Vault path: '.$vaultPath);

        // --- Recover Report Groups ---
        if (! is_dir($reportsDir)) {
            $this->error('Reports directory not found: '.$reportsDir);
        } else {
            $directories = array_filter(glob($reportsDir.'/*'), 'is_dir');
            $this->info('Found '.count($directories).' report group directories.');

            foreach (array_reverse($directories) as $directory) {
                $this->recoverReportGroup($directory);
            }
        }

        // --- Sync live entries from vault markdown into the database ---
        $this->info('Syncing live entries from vault markdown...');
        app(ObsidianSyncService::class)->syncAll();

        // --- Create live entries for report-group items that have no matching entry ---
        // Some entries exist only in report-group record files (not in the main
        // index files).  syncAll() imports from the main index files only, so
        // those entries are absent from the live tables.  Create them here so
        // every RGI item can be linked.
        $this->info('Creating missing live entries from report-group items...');
        $this->createMissingLiveEntriesFromReportGroupItems();

        // --- Re-link Report Group Items to live entries ---
        $this->info('Linking report group items to live entries...');
        $this->linkReportGroupItemsToEntries();
        $this->info('Linking complete.');

        // --- Recover Academic Year Snapshots ---
        if (! file_exists($snapshotsFile)) {
            $this->error('Snapshots file not found: '.$snapshotsFile);
        } else {
            $this->info('Found academic year snapshots file.');
            $this->recoverSnapshots($snapshotsFile);
        }

        $this->info('');
        $this->info('=== Recovery Summary ===');
        $this->info('Report Groups: '.ReportGroup::count());
        $this->info('Report Group Items: '.ReportGroupItem::count());
        $this->info('Academic Year Snapshots: '.AcademicYearSnapshot::count());
        $this->info('Snapshot Items: '.AcademicYearSnapshotItem::count());
        $this->info('');

        // --- Re-sync markdown to ensure vault is up to date ---
        $this->info('Re-syncing markdown to vault...');
        app(ReportGroupService::class)->syncMarkdown();
        $this->info('Markdown sync complete.');

        $this->info('');
        $this->info('Recovery complete!');

        return self::SUCCESS;
    }

    private function linkReportGroupItemsToEntries(): void
    {
        $mappings = [
            IndexType::Formation->value => 'formation_entries',
            IndexType::ParishInvolvement->value => 'parish_involvement_entries',
            IndexType::SocialApostolate->value => 'social_apostolate_entries',
        ];

        // Build lookup of live entries keyed by signature, matching the format
        // used by ObsidianSyncService::recordSignature().
        $entries = [];
        foreach ($mappings as $type => $table) {
            $rows = DB::table($table)->get();
            foreach ($rows as $row) {
                $date = $row->served_on;
                if (is_object($date)) {
                    $date = $date->format('Y-m-d');
                }

                $typeEnum = IndexType::from($type);
                $key = $this->signatureKey($typeEnum, $date, $row->time_start, $row->time_end, $row->cycle_code ?? null, $row->module_code ?? null, $row->title ?? null, $row->about ?? null, $row->role_in_activity ?? null);

                if (! isset($entries[$key])) {
                    $entries[$key] = [];
                }
                $entries[$key][] = $row->id;
            }
        }

        $this->info('  Found '.count($entries).' unique live entries.');

        // Collect RGI items ordered by report group created_at DESC so the most recent
        // report group gets priority for the unique source_entry_id constraint.
        $items = ReportGroupItem::query()
            ->join('report_groups', 'report_groups.id', 'report_group_items.report_group_id')
            ->select('report_group_items.*')
            ->orderByDesc('report_groups.created_at')
            ->orderBy('report_group_items.id')
            ->get();

        $linked = 0;
        $skipped = 0;
        $unmatched = 0;
        $usedEntryIds = []; // Tracks entry IDs already assigned to avoid unique-constraint violations.

        foreach ($items as $item) {
            if ($item->source_entry_id !== null) {
                $usedEntryIds[$item->index_type.'|'.$item->source_entry_id] = true;

                continue;
            }

            $date = $item->served_on;
            if (is_object($date)) {
                $date = $date->format('Y-m-d');
            }

            $typeEnum = IndexType::from($item->index_type);
            $key = $this->signatureKey(
                $typeEnum,
                $date,
                $item->time_start,
                $item->time_end,
                $item->cycle_code,
                $item->module_code,
                $item->title,
                $item->about,
                $item->role_in_activity
            );

            if (isset($entries[$key])) {
                $assigned = false;
                foreach ($entries[$key] as $entryId) {
                    $entryKey = $item->index_type.'|'.$entryId;
                    if (! isset($usedEntryIds[$entryKey])) {
                        $usedEntryIds[$entryKey] = true;
                        ReportGroupItem::where('id', $item->id)
                            ->update(['source_entry_id' => $entryId]);
                        $linked++;
                        $assigned = true;
                        break;
                    }
                }
                if (! $assigned) {
                    $skipped++;
                }
            } else {
                $unmatched++;
            }
        }

        $this->line("  Linked: {$linked}");
        $this->line("  Skipped (already assigned): {$skipped}");
        $this->line("  Unmatched (no corresponding live entry): {$unmatched}");
    }

    /**
     * For every RGI item that still has no source_entry_id after the initial
     * sync + link pass, check whether a matching live entry exists.  If the
     * entry is genuinely absent from the live tables (because it was only ever
     * stored in a report-group record file), create it from the RGI item's
     * own snapshot data so that the item can then be linked.
     */
    private function createMissingLiveEntriesFromReportGroupItems(): void
    {
        $tables = [
            IndexType::Formation->value => 'formation_entries',
            IndexType::ParishInvolvement->value => 'parish_involvement_entries',
            IndexType::SocialApostolate->value => 'social_apostolate_entries',
        ];

        // Build a lookup of existing entry signatures → entry IDs.
        $existing = [];
        foreach ($tables as $type => $table) {
            $rows = DB::table($table)->get();
            foreach ($rows as $row) {
                $date = is_object($row->served_on) ? $row->served_on->format('Y-m-d') : $row->served_on;
                $typeEnum = IndexType::from($type);
                $key = $this->signatureKey(
                    $typeEnum,
                    $date,
                    $row->time_start,
                    $row->time_end,
                    $row->cycle_code ?? null,
                    $row->module_code ?? null,
                    $row->title ?? null,
                    $row->about ?? null,
                    $row->role_in_activity ?? null
                );
                $existing[$key] = $row->id;
            }
        }

        // Track entry IDs already assigned to another RGI item so we don't
        // try to link a second item to the same entry (shared entries).
        $usedEntryIds = [];
        foreach (ReportGroupItem::whereNotNull('source_entry_id')->get() as $assigned) {
            $usedEntryIds[$assigned->index_type.'|'.$assigned->source_entry_id] = true;
        }

        // Find unmatched RGI items.
        $unmatched = ReportGroupItem::whereNull('source_entry_id')->get();

        $created = 0;
        foreach ($unmatched as $item) {
            $date = $item->served_on;
            if (is_object($date)) {
                $date = $date->format('Y-m-d');
            }

            $typeEnum = IndexType::from($item->index_type);
            $key = $this->signatureKey(
                $typeEnum,
                $date,
                $item->time_start,
                $item->time_end,
                $item->cycle_code,
                $item->module_code,
                $item->title,
                $item->about,
                $item->role_in_activity
            );

            // If a matching entry already exists AND hasn't been assigned
            // to another RGI item, link the item to it.
            if (isset($existing[$key])) {
                $entryId = $existing[$key];
                $entryKey = $item->index_type.'|'.$entryId;
                if (! isset($usedEntryIds[$entryKey])) {
                    $usedEntryIds[$entryKey] = true;
                    ReportGroupItem::where('id', $item->id)
                        ->update(['source_entry_id' => $entryId]);
                    $created++;

                    continue;
                }
                // Entry already assigned to another RGI item — fall through
                // to create a duplicate.
            }

            // Entry genuinely missing — create it from the RGI item.
            $table = $tables[$item->index_type];

            $payload = [
                'served_on' => $item->served_on,
                'academic_year' => $item->academic_year,
                'time_start' => $item->time_start,
                'time_end' => $item->time_end,
                'source_order' => $item->source_order,
                'obsidian_record_uuid' => $item->obsidian_record_uuid,
                'obsidian_last_synced_at' => now(),
                'obsidian_last_source' => 'recovery',
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if ($item->index_type === IndexType::Formation->value) {
                $payload['cycle_code'] = $item->cycle_code;
                $payload['module_code'] = $item->module_code;
                $payload['title'] = $item->title;
            } else {
                $payload['about'] = $item->about;
                $payload['role_in_activity'] = $item->role_in_activity;
            }

            $entryId = DB::table($table)->insertGetId($payload);
            $existing[$key] = $entryId;
            $usedEntryIds[$item->index_type.'|'.$entryId] = true;
            ReportGroupItem::where('id', $item->id)
                ->update(['source_entry_id' => $entryId]);
            $created++;
        }

        $this->line("  Created/Linked: {$created}");
    }

    private function signatureKey(
        IndexType $type,
        string $date,
        ?string $timeStart,
        ?string $timeEnd,
        ?string $cycleCode,
        ?string $moduleCode,
        ?string $title,
        ?string $about,
        ?string $roleInActivity
    ): string {
        if ($type === IndexType::Formation) {
            return $type->value.'|'.implode('|', [
                $date,
                $timeStart ?? '',
                $timeEnd ?? '',
                $cycleCode ?? '',
                $moduleCode ?? '',
                $title ?? '',
            ]);
        }

        // ParishInvolvement and SocialApostolate share the same signature format.
        return $type->value.'|'.implode('|', [
            $date,
            $timeStart ?? '',
            $timeEnd ?? '',
            $about ?? '',
            $roleInActivity ?? '',
        ]);
    }

    private function recoverReportGroup(string $directory): void
    {
        $indexPath = $directory.'/index.md';

        if (! file_exists($indexPath)) {
            $this->warn("No index.md found in: $directory");

            return;
        }

        $document = YamlFrontMatter::parseFile($indexPath);
        $matter = $document->matter();

        $tag = $matter['report_group_tag'] ?? null;
        $title = $matter['report_group_title'] ?? null;
        $groupId = $matter['report_group_id'] ?? null;

        if (! $tag) {
            $this->warn("No tag found in index.md: $indexPath");

            return;
        }

        $existing = ReportGroup::where('tag', $tag)->first();

        if ($existing) {
            $this->line("  = Already exists: {$tag} ({$title})");
            $reportGroup = $existing;
        } else {
            $reportGroup = new ReportGroup;
            if ($groupId) {
                $reportGroup->id = $groupId;
            }
            $reportGroup->tag = $tag;
            $reportGroup->title = $title;
            $reportGroup->created_at = $matter['created_at'] ?? now();
            $reportGroup->updated_at = $matter['updated_at'] ?? now();
            $reportGroup->obsidian_directory = $directory;
            $reportGroup->obsidian_index_note_path = $indexPath;
            $reportGroup->obsidian_last_synced_at = now();
            $reportGroup->save();
            $this->line("  + Restored: {$tag} ({$title})");
        }

        $recordsDir = $directory.'/records';
        if (! is_dir($recordsDir)) {
            return;
        }

        $recordFiles = array_filter(glob($recordsDir.'/*.md'), 'is_file');
        $itemOrder = 0;

        foreach ($recordFiles as $recordFile) {
            $this->recoverReportGroupItem($recordFile, $reportGroup, $itemOrder++);
        }
    }

    private function recoverReportGroupItem(string $path, ReportGroup $reportGroup, int $order): void
    {
        $document = YamlFrontMatter::parseFile($path);
        $matter = $document->matter();

        $uuid = $matter['record_uuid'] ?? null;
        $itemModelId = $matter['report_group_item_id'] ?? null;

        $exists = ReportGroupItem::where('report_group_id', $reportGroup->id)
            ->where(function ($query) use ($uuid, $path) {
                if ($uuid) {
                    $query->orWhere('obsidian_record_uuid', $uuid);
                }
                $query->orWhere('obsidian_note_path', $path);
            })
            ->exists();

        if ($exists) {
            $this->line('    = Item already exists: '.($matter['title'] ?? $matter['about'] ?? 'item'));

            return;
        }

        $item = new ReportGroupItem;
        if ($itemModelId) {
            $item->id = $itemModelId;
        }
        $item->report_group_id = $reportGroup->id;
        $item->index_type = $matter['index_type'] ?? IndexType::Formation->value;
        $item->served_on = $matter['served_on'] ?? null;
        $item->time_start = $matter['time_start'] ?? null;
        $item->time_end = $matter['time_end'] ?? null;
        $item->cycle_code = $matter['cycle_code'] ?? null;
        $item->module_code = $matter['module_code'] ?? null;
        $item->title = $matter['title'] ?? null;
        $item->about = $matter['about'] ?? null;
        $item->role_in_activity = $matter['role_in_activity'] ?? null;
        $item->source_order = $matter['source_order'] ?? $order;
        // Don't restore source_entry_id — a live entry can only be assigned to
        // one active report group at a time (partial unique index on
        // index_type + source_entry_id WHERE deleted_at IS NULL).
        // The vault preserves the snapshot data regardless of live assignment.
        $item->source_entry_id = null;
        $item->academic_year = $matter['academic_year'] ?? null;

        $item->obsidian_record_uuid = $uuid ?? null;
        $item->obsidian_note_path = $path;
        $item->obsidian_note_hash = hash('sha256', File::get($path));
        $item->obsidian_last_synced_at = now();

        $item->created_at = $matter['created_at'] ?? now();
        $item->updated_at = $matter['updated_at'] ?? now();

        $item->save();

        $title = $matter['title'] ?? ($matter['about'] ?? 'item');
        $this->line("    + Item: {$title} [{$matter['index_type']}]");
    }

    private function recoverSnapshots(string $filePath): void
    {
        $content = File::get($filePath);
        $parsed = YamlFrontMatter::parse($content);
        $body = $parsed->body();

        // Normalize line endings
        $body = str_replace("\r\n", "\n", $body);
        $body = str_replace("\r", "\n", $body);

        // Split on horizontal rules (--- on own line)
        $sections = preg_split('/\n---\n/', $body, -1, PREG_SPLIT_NO_EMPTY);

        foreach ($sections as $section) {
            if (! preg_match('/##\s+(.+)\n\s*- Academic Year:\s*(.+)\n\s*- Tag:\s*`(.+)`\n\s*- Created:\s*(.+)/', $section, $matches)) {
                continue;
            }

            $title = trim($matches[1]);
            $academicYear = trim($matches[2]);
            $tag = trim($matches[3]);
            $createdStr = trim($matches[4]);

            $existing = AcademicYearSnapshot::where('tag', $tag)->first();
            if ($existing) {
                $this->line("  = Already exists: {$tag} ({$title})");

                continue;
            }

            $createdAt = Carbon::parse($createdStr);

            DB::transaction(function () use ($tag, $title, $academicYear, $createdAt, $section): void {
                $snapshot = new AcademicYearSnapshot;
                $snapshot->tag = $tag;
                $snapshot->academic_year = $academicYear;
                $snapshot->title = $title;
                $snapshot->created_at = $createdAt;
                $snapshot->updated_at = $createdAt;
                $snapshot->save();

                $this->line("  + Restored: {$tag} ({$title})");

                // Parse included reports for source_report_tag lookup
                $includedReports = [];
                if (preg_match('/### Included Saved Reports\n\n((?:- .+\n?)*)/', $section, $reportMatches)) {
                    $reportLines = array_filter(explode("\n", $reportMatches[1]));
                    foreach ($reportLines as $line) {
                        if (preg_match('/- (.+?) \(`(.+)`\)/', $line, $r)) {
                            $includedReports[$r[2]] = $r[1];
                        }
                    }
                }

                // Parse tables for each index type
                $tables = preg_split('/### (Formation|Parish Involvement|Social Apostolate)/', $section, -1, PREG_SPLIT_DELIM_CAPTURE);

                $sourceOrder = 0;

                for ($i = 1; $i < count($tables); $i += 2) {
                    $indexType = trim($tables[$i]);
                    $tableContent = $tables[$i + 1] ?? '';

                    $type = match ($indexType) {
                        'Formation' => IndexType::Formation,
                        'Parish Involvement' => IndexType::ParishInvolvement,
                        'Social Apostolate' => IndexType::SocialApostolate,
                        default => null,
                    };

                    if (! $type) {
                        continue;
                    }

                    $lines = array_filter(array_map('trim', explode("\n", $tableContent)));
                    $inTable = false;
                    $headerCols = [];

                    foreach ($lines as $line) {
                        if (preg_match('/^\|/', $line)) {
                            if (! $inTable) {
                                $inTable = true;
                                $headerCols = array_map('trim', explode('|', trim($line, '|')));

                                continue;
                            }

                            if (preg_match('/^\|[\\s\\-|]+\|$/', $line)) {
                                continue;
                            }

                            $cols = array_map('trim', explode('|', trim($line, '|')));
                            if (count($cols) < count($headerCols)) {
                                continue;
                            }

                            $row = array_combine($headerCols, $cols);

                            $dateStr = $row['Date'] ?? '';
                            $timeIn = $row['Time In'] ?? '';
                            $timeOut = $row['Time Out'] ?? '';
                            $reportLabel = $row['Saved Report'] ?? '';

                            $sourceTag = null;
                            foreach ($includedReports as $rTag => $rLabel) {
                                if (strtolower($rLabel) === strtolower($reportLabel)) {
                                    $sourceTag = $rTag;
                                    break;
                                }
                            }

                            $item = new AcademicYearSnapshotItem;
                            $item->academic_year_snapshot_id = $snapshot->id;
                            $item->index_type = $type->value;
                            $item->served_on = Carbon::parse($dateStr)->format('Y-m-d');
                            $item->time_start = $this->parseTime($timeIn);
                            $item->time_end = $this->parseTime($timeOut);
                            $item->cycle_code = $row['Cycle No.'] ?? null;
                            $item->module_code = $row['Module No.'] ?? null;
                            $item->title = $row['Title'] ?? null;
                            $item->about = $row['Activity'] ?? null;
                            $item->role_in_activity = ($row['Role'] ?? '') ?: null;
                            $item->source_order = $sourceOrder++;
                            $item->source_report_tag = $sourceTag;
                            $item->source_report_label = $reportLabel;
                            $item->academic_year = $academicYear;
                            $item->created_at = now();
                            $item->updated_at = now();

                            if ($sourceTag) {
                                $rg = ReportGroup::where('tag', $sourceTag)->first();
                                if ($rg) {
                                    $item->report_group_id = $rg->id;
                                }
                            }

                            $item->save();
                        }
                    }
                }
            });
        }

        $this->line('  = Snapshot recovery complete.');
    }

    private function parseTime(string $timeStr): ?string
    {
        $timeStr = trim($timeStr);
        if ($timeStr === '') {
            return null;
        }

        $dt = Carbon::createFromFormat('g:i A', $timeStr);
        if ($dt) {
            return $dt->format('H:i:s');
        }

        $dt = Carbon::createFromFormat('H:i', $timeStr);
        if ($dt) {
            return $dt->format('H:i:s');
        }

        return null;
    }
}
