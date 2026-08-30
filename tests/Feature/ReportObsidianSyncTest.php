<?php

namespace Tests\Feature;

use App\Models\FormationEntry;
use App\Models\ReportGroup;
use App\Models\ReportGroupItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ReportObsidianSyncTest extends TestCase
{
    use RefreshDatabase;

    private string $vaultPath = '';

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_pgsql')) {
            $this->markTestSkipped('The pdo_pgsql extension is required for the PostgreSQL test database.');
        }

        parent::setUp();

        $this->vaultPath = storage_path('framework/testing/obsidian-report-sync');

        File::deleteDirectory($this->vaultPath);
        File::ensureDirectoryExists($this->vaultPath);

        config()->set('obsidian.vault_path', $this->vaultPath);
        config()->set('obsidian.report_groups_file', 'REPORT GROUPS.md');
        config()->set('obsidian.academic_year_snapshots_file', 'ACADEMIC YEAR SNAPSHOTS.md');
        config()->set('obsidian.report_notes_directory', 'REPORTS');
        config()->set('obsidian.report_index_file', 'index.md');

        File::put($this->vaultPath.DIRECTORY_SEPARATOR.'FORMATION.md', <<<'MD'
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

| Date | Cycle No. | Module No. | Title | Time In | Time Out |
| --- | --- | --- | --- | --- | --- |
| September 21, 2025 | C2 | M1 | I Belong to a Family | 3:00 PM | 4:00 PM |
MD);

        File::put($this->vaultPath.DIRECTORY_SEPARATOR.'PARISH INVOLVEMENT.md', <<<'MD'
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

| Date | Time In | Time Out |
| --- | --- | --- |
| September 7, 2025 | 6:30 PM | 7:30 PM |
MD);

        File::put($this->vaultPath.DIRECTORY_SEPARATOR.'SOCIAL APOSTOLATE.md', <<<'MD'
---
index: social_apostolate
card_title: Social Apostolate Index Card
profile:
  school_year: "2025-2026"
entry_options:
  academic_years:
    - "2025-2026"
  activities:
    - Creating MAV slides
---
# Social Apostolate Index Card

## Service Records

| Date | Activity | Time In | Time Out |
| --- | --- | --- | --- |
| September 8, 2025 | Creating MAV slides | 3:00 PM | 4:00 PM |
MD);
    }

    protected function tearDown(): void
    {
        if ($this->vaultPath !== '') {
            File::deleteDirectory($this->vaultPath);
        }

        parent::tearDown();
    }

    public function test_saved_report_page_can_create_update_and_delete_records_with_obsidian_note_sync(): void
    {
        $this->get('/')->assertOk();

        $this->post(route('report-groups.store'), [
            'title' => 'Editable report',
            'selected_entries' => [
                'formation:'.FormationEntry::query()->firstOrFail()->id,
            ],
        ])->assertRedirect();

        $reportGroup = ReportGroup::query()->firstOrFail();

        $reportPage = $this->get('/reports/'.$reportGroup->tag);

        $reportPage->assertOk();
        $reportPage->assertSee('Formation');
        $reportPage->assertSee('Parish Involvement');
        $reportPage->assertSee('Social Apostolate');

        $createResponse = $this->post('/reports/'.$reportGroup->tag.'/records', [
            'index_type' => 'social_apostolate',
            'served_on' => '2025-10-03',
            'about' => 'Prepared outreach materials',
            'time_start' => '09:00',
            'time_end' => '10:30',
        ]);

        $createResponse->assertRedirect('/reports/'.$reportGroup->tag);

        $createdItem = ReportGroupItem::query()
            ->where('report_group_id', $reportGroup->id)
            ->where('index_type', 'social_apostolate')
            ->where('about', 'Prepared outreach materials')
            ->first();

        $this->assertNotNull($createdItem);
        $this->assertNotNull($createdItem?->obsidian_record_uuid);
        $this->assertNotNull($createdItem?->obsidian_note_path);
        $this->assertTrue(File::exists($createdItem->obsidian_note_path));
        $this->assertStringContainsString('report_group_item_id: '.$createdItem->id, File::get($createdItem->obsidian_note_path));
        $this->assertStringContainsString('index_type: social_apostolate', File::get($createdItem->obsidian_note_path));

        $updateResponse = $this->patch('/reports/'.$reportGroup->tag.'/records/'.$createdItem->id, [
            'index_type' => 'social_apostolate',
            'served_on' => '2025-10-03',
            'about' => 'Prepared updated outreach materials',
            'time_start' => '10:00',
            'time_end' => '12:00',
        ]);

        $updateResponse->assertRedirect('/reports/'.$reportGroup->tag);

        $this->assertDatabaseHas('report_group_items', [
            'id' => $createdItem->id,
            'about' => 'Prepared updated outreach materials',
            'time_start' => '10:00:00',
            'time_end' => '12:00:00',
        ]);
        $this->assertStringContainsString('Prepared updated outreach materials', File::get($createdItem->fresh()->obsidian_note_path));

        $deletePath = $createdItem->fresh()->obsidian_note_path;

        $deleteResponse = $this->delete('/reports/'.$reportGroup->tag.'/records/'.$createdItem->id);

        $deleteResponse->assertRedirect('/reports/'.$reportGroup->tag);

        $this->assertSoftDeleted('report_group_items', [
            'id' => $createdItem->id,
        ]);
        $this->assertFalse(File::exists($deletePath));
    }

    public function test_report_records_can_sync_back_from_obsidian_notes(): void
    {
        $this->get('/')->assertOk();

        $this->post(route('report-groups.store'), [
            'title' => 'Vault editable report',
            'selected_entries' => [
                'formation:'.FormationEntry::query()->firstOrFail()->id,
            ],
        ])->assertRedirect();

        $reportGroup = ReportGroup::query()->firstOrFail();
        $existingItem = $reportGroup->items()->firstOrFail();

        $this->assertNotNull($existingItem->obsidian_note_path);

        File::put($existingItem->obsidian_note_path, <<<MD
---
report_group_id: {$reportGroup->id}
report_group_tag: {$reportGroup->tag}
report_group_item_id: {$existingItem->id}
record_uuid: {$existingItem->obsidian_record_uuid}
index_type: formation
source_entry_id: {$existingItem->source_entry_id}
served_on: 2025-09-21
time_start: '16:00:00'
time_end: '18:00:00'
cycle_code: C2
module_code: M1
title: Updated from Obsidian
source_order: 1
created_at: {$existingItem->created_at?->toIso8601String()}
updated_at: {$existingItem->updated_at?->toIso8601String()}
---
# Updated from Obsidian
MD);

        $reportDirectory = dirname(dirname($existingItem->obsidian_note_path));
        $newNotePath = $reportDirectory.DIRECTORY_SEPARATOR.'records'.DIRECTORY_SEPARATOR.'manual-social-note.md';

        File::ensureDirectoryExists(dirname($newNotePath));
        File::put($newNotePath, <<<MD
---
report_group_id: {$reportGroup->id}
report_group_tag: {$reportGroup->tag}
index_type: social_apostolate
served_on: 2025-10-04
time_start: '08:00:00'
time_end: '09:15:00'
about: Created a note directly in Obsidian
source_order: 99
---
# Manual social note
MD);

        $syncResponse = $this->post('/reports/sync-from-obsidian');

        $syncResponse->assertRedirect('/reports');

        $this->assertDatabaseHas('report_group_items', [
            'id' => $existingItem->id,
            'title' => 'Updated from Obsidian',
            'time_start' => '16:00:00',
            'time_end' => '18:00:00',
        ]);

        $createdFromVault = ReportGroupItem::query()
            ->where('report_group_id', $reportGroup->id)
            ->where('index_type', 'social_apostolate')
            ->where('about', 'Created a note directly in Obsidian')
            ->first();

        $this->assertNotNull($createdFromVault);
        $this->assertNotNull($createdFromVault?->obsidian_record_uuid);
        $this->assertStringContainsString('report_group_item_id: '.$createdFromVault->id, File::get($createdFromVault->obsidian_note_path));

        File::delete($createdFromVault->obsidian_note_path);

        $this->post('/reports/sync-from-obsidian')->assertRedirect('/reports');
        $this->post('/reports/sync-from-obsidian')->assertRedirect('/reports');

        $this->assertSoftDeleted('report_group_items', [
            'id' => $createdFromVault->id,
        ]);
    }

    public function test_resolve_conflict_accept_vault(): void
    {
        $this->get('/')->assertOk();

        $this->post(route('report-groups.store'), [
            'title' => 'Conflict test report',
            'selected_entries' => [
                'formation:'.FormationEntry::query()->firstOrFail()->id,
            ],
        ])->assertRedirect();

        $reportGroup = ReportGroup::query()->firstOrFail();
        $existingItem = $reportGroup->items()->firstOrFail();

        $this->assertNotNull($existingItem->obsidian_note_path);

        // Store original hash for conflict detection
        $originalHash = $existingItem->obsidian_note_hash;

        // Vault changes: edit the note file directly
        File::put($existingItem->obsidian_note_path, <<<MD
---
report_group_id: {$reportGroup->id}
report_group_tag: {$reportGroup->tag}
report_group_item_id: {$existingItem->id}
record_uuid: {$existingItem->obsidian_record_uuid}
index_type: formation
source_entry_id: {$existingItem->source_entry_id}
served_on: 2025-09-21
time_start: '16:00:00'
time_end: '18:00:00'
cycle_code: C2
module_code: M1
title: Vault Edited Title
source_order: 1
created_at: {$existingItem->created_at?->toIso8601String()}
updated_at: {$existingItem->updated_at?->toIso8601String()}
---
# Vault Edited Title
MD);

        // DB changes: update the item
        $existingItem->forceFill([
            'title' => 'DB Edited Title',
            'obsidian_note_hash' => $originalHash,
            'updated_at' => now()->subMinute(),
        ])->save();

        // Sync to create conflict
        $this->post('/reports/sync-from-obsidian')->assertRedirect('/reports');

        $existingItem = $existingItem->fresh();
        $this->assertTrue($existingItem->obsidian_conflict, 'Conflict should be detected');

        // Resolve by accepting vault version
        $response = $this->post(route('reports.records.conflict.accept-vault', [
            'reportGroup' => $reportGroup->tag,
            'reportGroupItem' => $existingItem->id,
        ]));

        $response->assertRedirect();

        $existingItem = $existingItem->fresh();
        $this->assertFalse($existingItem->obsidian_conflict, 'Conflict should be resolved');
        $this->assertSame('Vault Edited Title', $existingItem->title, 'Should have vault value after resolution');
    }

    public function test_resolve_conflict_accept_db(): void
    {
        $this->get('/')->assertOk();

        $this->post(route('report-groups.store'), [
            'title' => 'Conflict test report 2',
            'selected_entries' => [
                'formation:'.FormationEntry::query()->firstOrFail()->id,
            ],
        ])->assertRedirect();

        $reportGroup = ReportGroup::query()->firstOrFail();
        $existingItem = $reportGroup->items()->firstOrFail();

        $this->assertNotNull($existingItem->obsidian_note_path);

        // Store original hash for conflict detection
        $originalHash = $existingItem->obsidian_note_hash;

        // Vault changes: edit the note file directly
        File::put($existingItem->obsidian_note_path, <<<MD
---
report_group_id: {$reportGroup->id}
report_group_tag: {$reportGroup->tag}
report_group_item_id: {$existingItem->id}
record_uuid: {$existingItem->obsidian_record_uuid}
index_type: formation
source_entry_id: {$existingItem->source_entry_id}
served_on: 2025-09-21
time_start: '16:00:00'
time_end: '18:00:00'
cycle_code: C2
module_code: M1
title: Vault Edited Title
source_order: 1
created_at: {$existingItem->created_at?->toIso8601String()}
updated_at: {$existingItem->updated_at?->toIso8601String()}
---
# Vault Edited Title
MD);

        // DB changes: update the item
        $existingItem->forceFill([
            'title' => 'DB Edited Title',
            'obsidian_note_hash' => $originalHash,
            'updated_at' => now()->subMinute(),
        ])->save();

        // Sync to create conflict
        $this->post('/reports/sync-from-obsidian')->assertRedirect('/reports');

        $existingItem = $existingItem->fresh();
        $this->assertTrue($existingItem->obsidian_conflict, 'Conflict should be detected');

        // Resolve by accepting database version
        $response = $this->post(route('reports.records.conflict.accept-db', [
            'reportGroup' => $reportGroup->tag,
            'reportGroupItem' => $existingItem->id,
        ]));

        $response->assertRedirect();

        $existingItem = $existingItem->fresh();
        $this->assertFalse($existingItem->obsidian_conflict, 'Conflict should be resolved');
        $this->assertSame('DB Edited Title', $existingItem->title, 'Should have DB value after resolution');
    }

    public function test_resolve_conflict_merge(): void
    {
        $this->get('/')->assertOk();

        $this->post(route('report-groups.store'), [
            'title' => 'Conflict test report 3',
            'selected_entries' => [
                'formation:'.FormationEntry::query()->firstOrFail()->id,
            ],
        ])->assertRedirect();

        $reportGroup = ReportGroup::query()->firstOrFail();
        $existingItem = $reportGroup->items()->firstOrFail();

        $this->assertNotNull($existingItem->obsidian_note_path);

        // Store original hash for conflict detection
        $originalHash = $existingItem->obsidian_note_hash;

        // Vault changes: edit the note file directly
        File::put($existingItem->obsidian_note_path, <<<MD
---
report_group_id: {$reportGroup->id}
report_group_tag: {$reportGroup->tag}
report_group_item_id: {$existingItem->id}
record_uuid: {$existingItem->obsidian_record_uuid}
index_type: formation
source_entry_id: {$existingItem->source_entry_id}
served_on: 2025-09-21
time_start: '16:00:00'
time_end: '18:00:00'
cycle_code: C2
module_code: M1
title: Vault Edited Title
source_order: 1
created_at: {$existingItem->created_at?->toIso8601String()}
updated_at: {$existingItem->updated_at?->toIso8601String()}
---
# Vault Edited Title
MD);

        // DB changes: update the item
        $existingItem->forceFill([
            'title' => 'DB Edited Title',
            'obsidian_note_hash' => $originalHash,
            'updated_at' => now()->subMinute(),
        ])->save();

        // Sync to create conflict
        $this->post('/reports/sync-from-obsidian')->assertRedirect('/reports');

        $existingItem = $existingItem->fresh();
        $this->assertTrue($existingItem->obsidian_conflict, 'Conflict should be detected');

        // Resolve by manual merge
        $response = $this->post(route('reports.records.conflict.merge', [
            'reportGroup' => $reportGroup->tag,
            'reportGroupItem' => $existingItem->id,
        ]), [
            'index_type' => 'formation',
            'served_on' => '2025-09-21',
            'title' => 'Merged Title',
            'time_start' => '16:00',
            'time_end' => '18:00',
            'cycle_code' => 'C2',
            'module_code' => 'M1',
        ]);

        $response->assertRedirect();

        $existingItem = $existingItem->fresh();
        $this->assertFalse($existingItem->obsidian_conflict, 'Conflict should be resolved');
        $this->assertSame('Merged Title', $existingItem->title, 'Should have merged value after resolution');
    }
}
