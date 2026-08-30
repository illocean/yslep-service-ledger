<?php

namespace Tests\Feature;

use App\Enums\IndexType;
use App\Models\FormationEntry;
use App\Models\ParishInvolvementEntry;
use App\Models\SocialApostolateEntry;
use App\Services\ObsidianSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ObsidianLiveEntrySyncTest extends TestCase
{
    use RefreshDatabase;

    private string $vaultPath = '';
    private ObsidianSyncService $syncService;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_pgsql')) {
            $this->markTestSkipped('The pdo_pgsql extension is required for the PostgreSQL test database.');
        }

        parent::setUp();

        $this->vaultPath = storage_path('framework/testing/obsidian-live-entry-sync');
        $this->syncService = app(ObsidianSyncService::class);

        File::deleteDirectory($this->vaultPath);
        File::ensureDirectoryExists($this->vaultPath);

        config()->set('obsidian.vault_path', $this->vaultPath);
        config()->set('obsidian.report_groups_file', 'REPORT GROUPS.md');
        config()->set('obsidian.academic_year_snapshots_file', 'ACADEMIC YEAR SNAPSHOTS.md');
        config()->set('obsidian.report_notes_directory', 'REPORTS');
        config()->set('obsidian.report_index_file', 'index.md');
    }

    protected function tearDown(): void
    {
        if ($this->vaultPath !== '') {
            File::deleteDirectory($this->vaultPath);
        }

        parent::tearDown();
    }

    private function formationVault(string $body): string
    {
        return <<<MD
---
index: formation
card_title: Formation Index Card
profile:
  school_year: "2025-2026"
entry_options:
  academic_years:
    - "2025-2026"
---
# Formation Index Card

## Service Records

{$body}
MD;
    }

    private function socialVault(string $body): string
    {
        return <<<MD
---
index: social_apostolate
card_title: Social Apostolate Index Card
profile:
  school_year: "2025-2026"
entry_options:
  academic_years:
    - "2025-2026"
  activities:
    - Test Activity
    - Updated Activity
---
# Social Apostolate Index Card

## Service Records

{$body}
MD;
    }

    private function parishVault(string $body): string
    {
        return <<<MD
---
index: parish_involvement
card_title: Parish Involvement Index Card
profile:
  school_year: "2025-2026"
entry_options:
  academic_years:
    - "2025-2026"
---
# Parish Involvement Index Card

## Service Records

{$body}
MD;
    }

    private function formationFile(): string
    {
        return $this->vaultPath.DIRECTORY_SEPARATOR.'FORMATION.md';
    }

    private function socialFile(): string
    {
        return $this->vaultPath.DIRECTORY_SEPARATOR.'SOCIAL APOSTOLATE.md';
    }

    private function parishFile(): string
    {
        return $this->vaultPath.DIRECTORY_SEPARATOR.'PARISH INVOLVEMENT.md';
    }

    public function test_it_pulls_new_entries_from_vault_into_database(): void
    {
        File::put($this->formationFile(), $this->formationVault(<<<'MD'
| Date | Cycle No. | Module No. | Title | Time In | Time Out |
| --- | --- | --- | --- | --- | --- |
| September 21, 2025 | C2 | M1 | I Belong to a Family | 3:00 PM | 4:00 PM |
MD));

        $this->syncService->syncIndex(IndexType::Formation);

        $this->assertCount(1, FormationEntry::withTrashed()->get());
        $entry = FormationEntry::query()->firstOrFail();
        $this->assertSame('I Belong to a Family', $entry->title);
        $this->assertSame('C2', $entry->cycle_code);
        $this->assertSame('M1', $entry->module_code);
        $this->assertNotNull($entry->obsidian_record_uuid);
        $this->assertNotNull($entry->obsidian_last_synced_at);
        $this->assertSame('vault', $entry->obsidian_last_source);
        $this->assertNotNull($entry->obsidian_content_hash);
    }

    public function test_it_updates_existing_entry_when_vault_content_changes(): void
    {
        // Seed vault with front-matter records (avoids the body-parser shortcut
        // the service uses when front-matter is absent)
        File::put($this->socialFile(), <<<'MD'
---
index: social_apostolate
card_title: Social Apostolate Index Card
profile:
  school_year: "2025-2026"
entry_options:
  academic_years:
    - "2025-2026"
  activities:
    - Test Activity
    - Updated Activity
records:
  - served_on: '2025-09-08'
    about: 'Test Activity'
    time_start: '15:00:00'
    time_end: '16:00:00'
---
# Social Apostolate Index Card

## Service Records

MD);

        $this->syncService->syncIndex(IndexType::SocialApostolate);

        $entry = SocialApostolateEntry::query()->firstOrFail();
        $originalHash = $entry->obsidian_content_hash;
        $originalUuid = $entry->obsidian_record_uuid;

        // Modify the vault record (change activity name and time) but
        // preserve the record_uuid so we exercise the update path rather
        // than create a new entry and soft-delete the old.
        File::put($this->socialFile(), <<<MD
---
index: social_apostolate
card_title: Social Apostolate Index Card
profile:
  school_year: "2025-2026"
entry_options:
  academic_years:
    - "2025-2026"
  activities:
    - Test Activity
    - Updated Activity
records:
  - record_uuid: '{$originalUuid}'
    served_on: '2025-09-08'
    about: 'Updated Activity'
    time_start: '16:00:00'
    time_end: '17:00:00'
---
# Social Apostolate Index Card

## Service Records

MD);

        $this->syncService->syncIndex(IndexType::SocialApostolate);

        $entry = $entry->fresh();
        $this->assertSame('Updated Activity', $entry->about);
        $this->assertSame('16:00:00', $entry->time_start);
        $this->assertSame('17:00:00', $entry->time_end);
        $this->assertSame($originalUuid, $entry->obsidian_record_uuid);
        $this->assertNotSame($originalHash, $entry->obsidian_content_hash);
        $this->assertNotNull($entry->obsidian_last_synced_at);
    }

    public function test_it_is_idempotent_when_vault_unchanged(): void
    {
        File::put($this->parishFile(), $this->parishVault(<<<'MD'
| Date | Time In | Time Out |
| --- | --- | --- |
| September 7, 2025 | 6:30 PM | 7:30 PM |
MD));

        $this->syncService->syncIndex(IndexType::ParishInvolvement);

        $entry = ParishInvolvementEntry::query()->firstOrFail();
        $firstHash = $entry->obsidian_content_hash;
        $firstSyncedAt = $entry->obsidian_last_synced_at;

        // Sync again without changes
        $this->syncService->syncIndex(IndexType::ParishInvolvement);

        $entry = $entry->fresh();
        $this->assertSame($firstHash, $entry->obsidian_content_hash);
        $this->assertSame($firstSyncedAt?->format('Y-m-d H:i:s'), $entry->obsidian_last_synced_at?->format('Y-m-d H:i:s'));
    }

    public function test_it_soft_deletes_orphaned_entries_when_removed_from_vault(): void
    {
        File::put($this->formationFile(), $this->formationVault(<<<'MD'
| Date | Cycle No. | Module No. | Title | Time In | Time Out |
| --- | --- | --- | --- | --- | --- |
| September 21, 2025 | C2 | M1 | To Be Deleted | 3:00 PM | 4:00 PM |
| September 28, 2025 | C2 | M2 | Keep Me | 2:00 PM | 4:00 PM |
MD));

        $this->syncService->syncIndex(IndexType::Formation);

        $this->assertCount(2, FormationEntry::withTrashed()->get());

        // Remove the first row from vault (and add a "no records" empty table)
        File::put($this->formationFile(), $this->formationVault(<<<'MD'
| Date | Cycle No. | Module No. | Title | Time In | Time Out |
| --- | --- | --- | --- | --- | --- |
MD));

        $this->syncService->syncIndex(IndexType::Formation);

        $this->assertCount(0, FormationEntry::query()->get());
        $this->assertCount(2, FormationEntry::withTrashed()->get());
        $this->assertSoftDeleted('formation_entries', [
            'title' => 'To Be Deleted',
        ]);

        $deletedEntry = FormationEntry::onlyTrashed()->where('title', 'To Be Deleted')->firstOrFail();
        $this->assertSame('vault', $deletedEntry->obsidian_last_source);
        $this->assertNotNull($deletedEntry->obsidian_last_synced_at);
    }

    public function test_it_resurrects_soft_deleted_entry_when_vault_reintroduces_same_uuid(): void
    {
        // Start with one entry in vault
        File::put($this->parishFile(), $this->parishVault(<<<'MD'
| Date | Time In | Time Out |
| --- | --- | --- |
| September 7, 2025 | 6:30 PM | 7:30 PM |
MD));

        $this->syncService->syncIndex(IndexType::ParishInvolvement);

        $originalEntry = ParishInvolvementEntry::query()->firstOrFail();
        $uuid = $originalEntry->obsidian_record_uuid;
        $this->assertNotNull($uuid);

        // Remove it from vault (soft-deletes in DB)
        File::put($this->parishFile(), $this->parishVault(<<<'MD'
| Date | Time In | Time Out |
| --- | --- | --- |
MD));

        $this->syncService->syncIndex(IndexType::ParishInvolvement);

        $this->assertSoftDeleted('parish_involvement_entries', ['id' => $originalEntry->id]);
        $this->assertCount(0, ParishInvolvementEntry::query()->get());

        // Re-add the SAME entry by writing a vault with the matching front-matter record
        // (simulating user undeleting the note with same UUID)
        $preservedUuid = $uuid;
        File::put($this->parishFile(), <<<MD
---
index: parish_involvement
card_title: Parish Involvement Index Card
profile:
  school_year: "2025-2026"
entry_options:
  academic_years:
    - "2025-2026"
records:
  - record_uuid: '{$preservedUuid}'
    served_on: '2025-09-07'
    time_start: '18:30:00'
    time_end: '19:30:00'
---
# Parish Involvement Index Card

## Service Records

MD);

        $this->syncService->syncIndex(IndexType::ParishInvolvement);

        $this->assertCount(1, ParishInvolvementEntry::query()->get());
        $this->assertCount(0, ParishInvolvementEntry::onlyTrashed()->get());

        $resurrected = ParishInvolvementEntry::query()->where('obsidian_record_uuid', $uuid)->firstOrFail();
        $this->assertSame($originalEntry->id, $resurrected->id); // same row ID restored
        $this->assertNull($resurrected->deleted_at);
    }

    public function test_it_backfills_hash_and_timestamps_on_next_sync_for_existing_entries(): void
    {
        // Simulate existing entries that pre-date the sync-state columns
        $entry = FormationEntry::query()->create([
            'served_on' => '2025-09-21',
            'cycle_no' => 'C1',
            'module_no' => 'M1',
            'title' => 'Legacy Entry',
            'time_in' => '15:00:00',
            'time_out' => '16:00:00',
            'obsidian_record_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'obsidian_content_hash' => null,
            'obsidian_last_synced_at' => null,
            'obsidian_last_source' => null,
        ]);

        // Vault has matching UUID via front-matter records
        $uuid = $entry->obsidian_record_uuid;
        File::put($this->formationFile(), <<<MD
---
index: formation
card_title: Formation Index Card
profile:
  school_year: "2025-2026"
entry_options:
  academic_years:
    - "2025-2026"
records:
  - record_uuid: '{$uuid}'
    served_on: '2025-09-21'
    cycle_code: C1
    module_code: M1
    title: Legacy Entry
    time_start: '15:00:00'
    time_end: '16:00:00'
---
# Formation Index Card

## Service Records

MD);

        $this->syncService->syncIndex(IndexType::Formation);

        $entry = $entry->fresh();
        $this->assertNotNull($entry->obsidian_content_hash);
        $this->assertNotNull($entry->obsidian_last_synced_at);
        $this->assertSame('vault', $entry->obsidian_last_source);
    }

    public function test_role_in_activity_round_trips_through_vault(): void
    {
        File::put($this->socialFile(), <<<'MD'
---
index: social_apostolate
card_title: Social Apostolate Index Card
profile:
  school_year: "2025-2026"
entry_options:
  academic_years:
    - "2025-2026"
records:
  - served_on: '2025-09-08'
    about: 'Test Activity'
    role_in_activity: 'Volunteer'
    time_start: '15:00:00'
    time_end: '16:00:00'
---
# Social Apostolate Index Card

## Service Records

MD);

        $this->syncService->syncIndex(IndexType::SocialApostolate);

        $entry = SocialApostolateEntry::query()->firstOrFail();
        $this->assertSame('Test Activity', $entry->about);
        $this->assertSame('Volunteer', $entry->role_in_activity);
        $this->assertNotNull($entry->obsidian_record_uuid);

        $content = File::get($this->socialFile());
        $this->assertStringContainsString('Volunteer', $content);
    }

    public function test_conflict_detected_when_both_sides_change(): void
    {
        // 1. Initial sync: create entry in vault and sync to DB
        File::put($this->socialFile(), <<<'MD'
---
index: social_apostolate
card_title: Social Apostolate Index Card
profile:
  school_year: "2025-2026"
entry_options:
  academic_years:
    - "2025-2026"
records:
  - served_on: '2025-09-08'
    about: 'Original Activity'
    time_start: '15:00:00'
    time_end: '16:00:00'
---
# Social Apostolate Index Card

## Service Records

MD);

        $this->syncService->syncIndex(IndexType::SocialApostolate);

        $entry = SocialApostolateEntry::query()->firstOrFail();
        $uuid = $entry->obsidian_record_uuid;
        $originalHash = $entry->obsidian_content_hash;
        $this->assertFalse($entry->obsidian_conflict);

        // 2. Simulate external vault edit (user edits in Obsidian)
        // Change the activity in vault
        File::put($this->socialFile(), <<<MD
---
index: social_apostolate
card_title: Social Apostolate Index Card
profile:
  school_year: "2025-2026"
entry_options:
  academic_years:
    - "2025-2026"
records:
  - record_uuid: '{$uuid}'
    served_on: '2025-09-08'
    about: 'Vault Edited Activity'
    time_start: '15:00:00'
    time_end: '16:00:00'
---
# Social Apostolate Index Card

## Service Records

MD);

        // 3. Simulate DB edit (user edits in web UI) BEFORE syncing vault changes
        // This simulates: user edits in web UI, which writes to vault and syncs
        // But we'll directly edit the DB to simulate a race condition
        $entry->forceFill([
            'about' => 'DB Edited Activity',
            'obsidian_content_hash' => $originalHash, // Keep original hash to simulate unsynced DB change
            'updated_at' => now()->subMinute(), // DB updated after last sync
        ])->save();

        // 4. Now sync - both sides have changed since last sync
        $this->syncService->syncIndex(IndexType::SocialApostolate);

        $entry = $entry->fresh();
        $this->assertTrue($entry->obsidian_conflict, 'Should be marked as conflicted');
        $this->assertSame('DB Edited Activity', $entry->about, 'DB value should be preserved');
        $this->assertSame($originalHash, $entry->obsidian_content_hash, 'Hash should not change on conflict');
    }

    public function test_no_conflict_when_only_vault_changes(): void
    {
        File::put($this->socialFile(), <<<'MD'
---
index: social_apostolate
card_title: Social Apostolate Index Card
profile:
  school_year: "2025-2026"
entry_options:
  academic_years:
    - "2025-2026"
records:
  - served_on: '2025-09-08'
    about: 'Original Activity'
    time_start: '15:00:00'
    time_end: '16:00:00'
---
# Social Apostolate Index Card

## Service Records

MD);

        $this->syncService->syncIndex(IndexType::SocialApostolate);

        $entry = SocialApostolateEntry::query()->firstOrFail();
        $uuid = $entry->obsidian_record_uuid;

        // Only vault changes
        File::put($this->socialFile(), <<<MD
---
index: social_apostolate
card_title: Social Apostolate Index Card
profile:
  school_year: "2025-2026"
entry_options:
  academic_years:
    - "2025-2026"
records:
  - record_uuid: '{$uuid}'
    served_on: '2025-09-08'
    about: 'Vault Edited Activity'
    time_start: '15:00:00'
    time_end: '16:00:00'
---
# Social Apostolate Index Card

## Service Records

MD);

        $this->syncService->syncIndex(IndexType::SocialApostolate);

        $entry = $entry->fresh();
        $this->assertFalse($entry->obsidian_conflict);
        $this->assertSame('Vault Edited Activity', $entry->about);
    }

    public function test_no_conflict_when_only_db_changes(): void
    {
        File::put($this->socialFile(), <<<'MD'
---
index: social_apostolate
card_title: Social Apostolate Index Card
profile:
  school_year: "2025-2026"
entry_options:
  academic_years:
    - "2025-2026"
records:
  - served_on: '2025-09-08'
    about: 'Original Activity'
    time_start: '15:00:00'
    time_end: '16:00:00'
---
# Social Apostolate Index Card

## Service Records

MD);

        $this->syncService->syncIndex(IndexType::SocialApostolate);

        $entry = SocialApostolateEntry::query()->firstOrFail();

        // Only DB changes (simulate web UI edit that hasn't been synced to vault yet)
        // In practice, web UI edits write to vault first then sync, so this is a race condition
        // But we test the logic directly
        $entry->forceFill([
            'about' => 'DB Edited Activity',
            'updated_at' => now()->subMinute(),
        ])->save();

        $this->syncService->syncIndex(IndexType::SocialApostolate);

        $entry = $entry->fresh();
        $this->assertFalse($entry->obsidian_conflict);
        // Vault should win (or DB preserved - depends on policy)
        // Current implementation: vault wins when only vault changes, DB wins when only DB changes
    }

    public function test_resolve_conflict_accept_vault(): void
    {
        // Set up a conflict
        File::put($this->socialFile(), <<<'MD'
---
index: social_apostolate
card_title: Social Apostolate Index Card
profile:
  school_year: "2025-2026"
entry_options:
  academic_years:
    - "2025-2026"
records:
  - served_on: '2025-09-08'
    about: 'Original Activity'
    time_start: '15:00:00'
    time_end: '16:00:00'
---
# Social Apostolate Index Card

## Service Records

MD);

        $this->syncService->syncIndex(IndexType::SocialApostolate);

        $entry = SocialApostolateEntry::query()->firstOrFail();
        $uuid = $entry->obsidian_record_uuid;
        $originalHash = $entry->obsidian_content_hash;

        // Vault changes
        File::put($this->socialFile(), <<<MD
---
index: social_apostolate
card_title: Social Apostolate Index Card
profile:
  school_year: "2025-2026"
entry_options:
  academic_years:
    - "2025-2026"
records:
  - record_uuid: '{$uuid}'
    served_on: '2025-09-08'
    about: 'Vault Edited Activity'
    time_start: '15:00:00'
    time_end: '16:00:00'
---
# Social Apostolate Index Card

## Service Records

MD);

        // DB changes
        $entry->forceFill([
            'about' => 'DB Edited Activity',
            'obsidian_content_hash' => $originalHash,
            'updated_at' => now()->subMinute(),
        ])->save();

        // Sync to create conflict
        $this->syncService->syncIndex(IndexType::SocialApostolate);

        $entry = $entry->fresh();
        $this->assertTrue($entry->obsidian_conflict);
        $this->assertSame('DB Edited Activity', $entry->about);

        // Resolve by accepting vault
        $response = $this->post(route('entries.conflict.accept-vault', ['entry' => $entry->id]), [
            'type' => 'social_apostolate',
            'scope' => 'all',
        ]);

        $response->assertRedirect();

        $entry = $entry->fresh();
        $this->assertFalse($entry->obsidian_conflict, 'Conflict should be resolved');
        $this->assertSame('Vault Edited Activity', $entry->about, 'Should have vault value after resolution');
    }

    public function test_resolve_conflict_accept_db(): void
    {
        // Set up a conflict
        File::put($this->socialFile(), <<<'MD'
---
index: social_apostolate
card_title: Social Apostolate Index Card
profile:
  school_year: "2025-2026"
entry_options:
  academic_years:
    - "2025-2026"
records:
  - served_on: '2025-09-08'
    about: 'Original Activity'
    time_start: '15:00:00'
    time_end: '16:00:00'
---
# Social Apostolate Index Card

## Service Records

MD);

        $this->syncService->syncIndex(IndexType::SocialApostolate);

        $entry = SocialApostolateEntry::query()->firstOrFail();
        $uuid = $entry->obsidian_record_uuid;
        $originalHash = $entry->obsidian_content_hash;

        // Vault changes
        File::put($this->socialFile(), <<<MD
---
index: social_apostolate
card_title: Social Apostolate Index Card
profile:
  school_year: "2025-2026"
entry_options:
  academic_years:
    - "2025-2026"
records:
  - record_uuid: '{$uuid}'
    served_on: '2025-09-08'
    about: 'Vault Edited Activity'
    time_start: '15:00:00'
    time_end: '16:00:00'
---
# Social Apostolate Index Card

## Service Records

MD);

        // DB changes
        $entry->forceFill([
            'about' => 'DB Edited Activity',
            'obsidian_content_hash' => $originalHash,
            'updated_at' => now()->subMinute(),
        ])->save();

        // Sync to create conflict
        $this->syncService->syncIndex(IndexType::SocialApostolate);

        $entry = $entry->fresh();
        $this->assertTrue($entry->obsidian_conflict);
        $this->assertSame('DB Edited Activity', $entry->about);

        // Resolve by accepting database (write DB to vault)
        $response = $this->post(route('entries.conflict.accept-db', ['entry' => $entry->id]), [
            'type' => 'social_apostolate',
            'scope' => 'all',
        ]);

        $response->assertRedirect();

        $entry = $entry->fresh();
        $this->assertFalse($entry->obsidian_conflict, 'Conflict should be resolved');
        // DB value should be preserved and written to vault
        $this->assertSame('DB Edited Activity', $entry->about, 'Should keep DB value after resolution');
    }

    public function test_resolve_conflict_merge(): void
    {
        // Set up a conflict
        File::put($this->socialFile(), <<<'MD'
---
index: social_apostolate
card_title: Social Apostolate Index Card
profile:
  school_year: "2025-2026"
entry_options:
  academic_years:
    - "2025-2026"
records:
  - served_on: '2025-09-08'
    about: 'Original Activity'
    time_start: '15:00:00'
    time_end: '16:00:00'
---
# Social Apostolate Index Card

## Service Records

MD);

        $this->syncService->syncIndex(IndexType::SocialApostolate);

        $entry = SocialApostolateEntry::query()->firstOrFail();
        $uuid = $entry->obsidian_record_uuid;
        $originalHash = $entry->obsidian_content_hash;

        // Vault changes
        File::put($this->socialFile(), <<<MD
---
index: social_apostolate
card_title: Social Apostolate Index Card
profile:
  school_year: "2025-2026"
entry_options:
  academic_years:
    - "2025-2026"
records:
  - record_uuid: '{$uuid}'
    served_on: '2025-09-08'
    about: 'Vault Edited Activity'
    time_start: '15:00:00'
    time_end: '16:00:00'
---
# Social Apostolate Index Card

## Service Records

MD);

        // DB changes
        $entry->forceFill([
            'about' => 'DB Edited Activity',
            'obsidian_content_hash' => $originalHash,
            'updated_at' => now()->subMinute(),
        ])->save();

        // Sync to create conflict
        $this->syncService->syncIndex(IndexType::SocialApostolate);

        $entry = $entry->fresh();
        $this->assertTrue($entry->obsidian_conflict);

        // Resolve by manual merge
        $response = $this->post(route('entries.conflict.merge', ['entry' => $entry->id]), [
            'type' => 'social_apostolate',
            'scope' => 'all',
            'served_on' => '2025-09-08',
            'about' => 'Merged Activity',
            'time_start' => '15:00',
            'time_end' => '16:00',
        ]);

        $response->assertRedirect();

        $entry = $entry->fresh();
        $this->assertFalse($entry->obsidian_conflict, 'Conflict should be resolved');
        $this->assertSame('Merged Activity', $entry->about, 'Should have merged value after resolution');
    }
}