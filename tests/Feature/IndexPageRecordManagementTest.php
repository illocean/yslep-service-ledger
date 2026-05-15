<?php

namespace Tests\Feature;

use App\Models\FormationEntry;
use App\Models\ReportGroup;
use App\Models\ReportGroupItem;
use App\Models\SocialApostolateEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class IndexPageRecordManagementTest extends TestCase
{
    use RefreshDatabase;

    private string $vaultPath = '';

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_pgsql')) {
            $this->markTestSkipped('The pdo_pgsql extension is required for the PostgreSQL test database.');
        }

        parent::setUp();

        $this->vaultPath = storage_path('framework/testing/obsidian-index-management');

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
| September 21, 2025 | C2 | M1 | I Belong to a Family | 3:00 PM | 4:00 PM |
| September 28, 2025 | C2 | M2 | Honor Thy Parent | 2:00 PM | 4:00 PM |
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
| September 14, 2025 | 6:00 PM | 7:30 PM |
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
    - Creating SocCom slides
---
# Social Apostolate Index Card

## Service Records

| Date | Activity | Time In | Time Out |
| --- | --- | --- | --- |
| September 8, 2025 | Creating MAV slides | 3:00 PM | 4:00 PM |
| September 25, 2025 | Creating SocCom slides | 9:00 AM | 11:00 AM |
MD);
    }

    protected function tearDown(): void
    {
        if ($this->vaultPath !== '') {
            File::deleteDirectory($this->vaultPath);
        }

        parent::tearDown();
    }

    public function test_unsaved_scope_only_shows_live_rows_that_are_not_attached_to_saved_reports(): void
    {
        $this->get('/')->assertOk();

        $lockedEntry = FormationEntry::query()->orderBy('id')->firstOrFail();

        $this->post(route('report-groups.store'), [
            'title' => 'Locked formation report',
            'selected_entries' => [
                'formation:'.$lockedEntry->id,
            ],
        ])->assertRedirect();

        $response = $this->get('/indexes/formation?scope=unsaved');

        $response->assertOk();
        $entries = $response->viewData('entries');

        $this->assertNotNull($entries);
        $this->assertCount(2, $entries);
        $this->assertSame([
            'I Belong to a Family',
            'Honor Thy Parent',
        ], $entries->pluck('title')->all());
    }

    public function test_human_readable_index_urls_resolve_to_the_same_pages(): void
    {
        $this->get('/indexes/Formation')->assertOk();
        $this->get('/indexes/Parish%20Involvement')->assertOk();
        $this->get('/indexes/Social%20Apostolate')->assertOk();
    }

    public function test_live_entries_can_be_updated_and_deleted_from_the_index_page_with_markdown_sync(): void
    {
        $this->get('/indexes/social_apostolate')->assertOk();

        $entry = SocialApostolateEntry::query()
            ->where('about', 'Creating MAV slides')
            ->firstOrFail();

        $this->assertNotNull($entry->getAttribute('obsidian_record_uuid'));
        $this->assertStringContainsString('record_uuid:', File::get($this->vaultPath.DIRECTORY_SEPARATOR.'SOCIAL APOSTOLATE.md'));

        $updateResponse = $this->patch(route('entries.update', ['entry' => $entry->id]), [
            'type' => 'social_apostolate',
            'served_on' => '2025-09-08',
            'about' => 'Updated social activity',
            'time_start' => '13:00',
            'time_end' => '14:30',
            'scope' => 'all',
        ]);

        $updateResponse->assertRedirect('/indexes/social_apostolate');

        $entry->refresh();

        $this->assertNotNull($entry->getAttribute('obsidian_record_uuid'));
        $this->assertDatabaseHas('social_apostolate_entries', [
            'id' => $entry->id,
            'about' => 'Updated social activity',
            'time_start' => '13:00:00',
            'time_end' => '14:30:00',
        ]);
        $this->assertStringContainsString('Updated social activity', File::get($this->vaultPath.DIRECTORY_SEPARATOR.'SOCIAL APOSTOLATE.md'));
        $deleteResponse = $this->delete(route('entries.destroy', ['entry' => $entry->id]), [
            'type' => 'social_apostolate',
            'scope' => 'all',
        ]);

        $deleteResponse->assertRedirect('/indexes/social_apostolate');

        $this->assertDatabaseMissing('social_apostolate_entries', [
            'id' => $entry->id,
        ]);
        $this->assertStringNotContainsString(
            '| September 8, 2025 | Updated social activity | 1:00 PM | 2:30 PM |',
            File::get($this->vaultPath.DIRECTORY_SEPARATOR.'SOCIAL APOSTOLATE.md'),
        );
    }

    public function test_social_apostolate_live_crud_does_not_leave_stale_activity_options_in_obsidian(): void
    {
        $createdActivity = 'Temporary Playwright Activity Alpha';
        $updatedActivity = 'Temporary Playwright Activity Omega';

        $this->post(route('entries.store'), [
            'type' => 'social_apostolate',
            'served_on' => '2026-04-18',
            'about' => $createdActivity,
            'time_start' => '09:00',
            'time_end' => '10:30',
            'scope' => 'all',
        ])->assertRedirect('/indexes/social_apostolate');

        $entry = SocialApostolateEntry::query()
            ->where('about', $createdActivity)
            ->firstOrFail();

        $this->assertStringContainsString($createdActivity, File::get($this->vaultPath.DIRECTORY_SEPARATOR.'SOCIAL APOSTOLATE.md'));

        $this->patch(route('entries.update', ['entry' => $entry->id]), [
            'type' => 'social_apostolate',
            'served_on' => '2026-04-18',
            'about' => $updatedActivity,
            'time_start' => '10:15',
            'time_end' => '11:45',
            'scope' => 'all',
        ])->assertRedirect('/indexes/social_apostolate');

        $contentAfterUpdate = File::get($this->vaultPath.DIRECTORY_SEPARATOR.'SOCIAL APOSTOLATE.md');

        $this->assertStringContainsString($updatedActivity, $contentAfterUpdate);
        $this->assertStringNotContainsString($createdActivity, $contentAfterUpdate);

        $this->delete(route('entries.destroy', ['entry' => $entry->id]), [
            'type' => 'social_apostolate',
            'scope' => 'all',
        ])->assertRedirect('/indexes/social_apostolate');

        $this->assertStringNotContainsString(
            $updatedActivity,
            File::get($this->vaultPath.DIRECTORY_SEPARATOR.'SOCIAL APOSTOLATE.md'),
        );
    }

    public function test_live_entry_updates_target_the_exact_duplicate_row_by_obsidian_uuid(): void
    {
        $this->get('/indexes/formation')->assertOk();

        $entries = FormationEntry::query()
            ->where('title', 'I Belong to a Family')
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $entries);

        $target = $entries->first();
        $other = $entries->last();

        $response = $this->patch(route('entries.update', ['entry' => $target->id]), [
            'type' => 'formation',
            'served_on' => '2025-09-21',
            'cycle_code' => 'C2',
            'module_code' => 'M1',
            'title' => 'Updated first duplicate only',
            'time_start' => '15:00',
            'time_end' => '16:15',
            'scope' => 'all',
        ]);

        $response->assertRedirect('/indexes/formation');

        $target->refresh();
        $other->refresh();

        $this->assertSame('Updated first duplicate only', $target->title);
        $this->assertSame('I Belong to a Family', $other->title);
        $this->assertNotSame($target->obsidian_record_uuid, $other->obsidian_record_uuid);

        $content = File::get($this->vaultPath.DIRECTORY_SEPARATOR.'FORMATION.md');

        $this->assertStringContainsString('Updated first duplicate only', $content);
        $this->assertStringContainsString('I Belong to a Family', $content);
    }

    public function test_saved_scope_record_changes_from_the_index_page_redirect_back_to_the_same_saved_scope(): void
    {
        $this->get('/')->assertOk();

        $formationEntry = FormationEntry::query()->orderBy('id')->firstOrFail();

        $this->post(route('report-groups.store'), [
            'title' => 'Scope Redirect Report',
            'selected_entries' => [
                'formation:'.$formationEntry->id,
            ],
        ])->assertRedirect();

        $reportGroup = ReportGroup::query()->firstOrFail();
        $reportItem = ReportGroupItem::query()->where('report_group_id', $reportGroup->id)->firstOrFail();

        $updateResponse = $this->patch(route('reports.records.update', [$reportGroup, $reportItem]), [
            'index_type' => 'formation',
            'served_on' => '2025-09-21',
            'cycle_code' => 'C9',
            'module_code' => 'M9',
            'title' => 'Updated from index scope',
            'time_start' => '16:00',
            'time_end' => '18:00',
            'return_type' => 'formation',
            'return_scope' => 'report',
            'return_report' => $reportGroup->tag,
        ]);

        $updateResponse->assertRedirect('/indexes/formation?scope=report&report='.$reportGroup->tag);
        $this->assertDatabaseHas('report_group_items', [
            'id' => $reportItem->id,
            'title' => 'Updated from index scope',
        ]);

        $createResponse = $this->post(route('reports.records.store', $reportGroup), [
            'index_type' => 'social_apostolate',
            'served_on' => '2025-10-03',
            'about' => 'Created from saved scope index page',
            'time_start' => '09:00',
            'time_end' => '10:30',
            'return_type' => 'social_apostolate',
            'return_scope' => 'report',
            'return_report' => $reportGroup->tag,
        ]);

        $createResponse->assertRedirect('/indexes/social_apostolate?scope=report&report='.$reportGroup->tag);
        $this->assertDatabaseHas('report_group_items', [
            'report_group_id' => $reportGroup->id,
            'index_type' => 'social_apostolate',
            'about' => 'Created from saved scope index page',
        ]);
    }
}
