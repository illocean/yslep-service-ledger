<?php

namespace Tests\Feature;

use App\Models\AcademicYearSnapshot;
use App\Models\FormationEntry;
use App\Models\ReportGroup;
use App\Models\SocialApostolateEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DashboardSyncTest extends TestCase
{
    use RefreshDatabase;

    private string $vaultPath = '';

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_pgsql')) {
            $this->markTestSkipped('The pdo_pgsql extension is required for the PostgreSQL test database.');
        }

        parent::setUp();

        $this->vaultPath = storage_path('framework/testing/obsidian-sync');

        File::deleteDirectory($this->vaultPath);
        File::ensureDirectoryExists($this->vaultPath);

        config()->set('obsidian.vault_path', $this->vaultPath);
        config()->set('obsidian.report_groups_file', 'REPORT GROUPS.md');
        config()->set('obsidian.academic_year_snapshots_file', 'ACADEMIC YEAR SNAPSHOTS.md');

        File::put($this->vaultPath.DIRECTORY_SEPARATOR.'FORMATION.md', <<<'MD'
---
index: formation
card_title: Formation Index Card
profile:
  school_year: "2025-2026"
entry_options:
  academic_years:
    - "2025-2026"
  cycle_codes:
    - C1
    - C2
  module_codes:
    - M1
    - M2
  titles:
    - I Belong to a Family
    - Honor Thy Parent
---
# Formation Index Card

## Service Records

| Date | Cycle No. | Module No. | Title | Time In | Time Out |
| --- | --- | --- | --- | --- | --- |
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
    - Preparing outreach handouts
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

    public function test_dashboard_syncs_markdown_files_and_displays_all_time_totals(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('All Time totals');
        $response->assertSee('Open Saved Reports');
        $response->assertSee('Locked report entries tagged');
        $response->assertSee('3 hr');
        $response->assertSee('2 hr 30 min');
        $response->assertSee('8 hr 30 min');

        $this->assertDatabaseCount('formation_entries', 2);
        $this->assertDatabaseCount('parish_involvement_entries', 2);
        $this->assertDatabaseCount('social_apostolate_entries', 2);
        $this->assertStringContainsString('entry_options:', File::get($this->vaultPath.DIRECTORY_SEPARATOR.'FORMATION.md'));
        $this->assertStringNotContainsString('## Monthly Entries', File::get($this->vaultPath.DIRECTORY_SEPARATOR.'FORMATION.md'));
    }

    public function test_creating_a_report_group_redirects_to_saved_report_management_and_writes_the_obsidian_report_file(): void
    {
        $this->get('/')->assertOk();

        $response = $this->post(route('report-groups.store'), [
            'title' => 'Christmas break service',
            'selected_entries' => [
                'formation:'.FormationEntry::query()->firstOrFail()->id,
                'social_apostolate:'.SocialApostolateEntry::query()->firstOrFail()->id,
            ],
        ]);

        $reportGroup = ReportGroup::query()->with('items')->firstOrFail();

        $response->assertRedirect(route('reports.show', $reportGroup));

        $this->assertDatabaseCount('report_groups', 1);
        $this->assertDatabaseCount('report_group_items', 2);

        $content = File::get($this->vaultPath.DIRECTORY_SEPARATOR.'REPORT GROUPS.md');
        $formationContent = File::get($this->vaultPath.DIRECTORY_SEPARATOR.'FORMATION.md');
        $socialContent = File::get($this->vaultPath.DIRECTORY_SEPARATOR.'SOCIAL APOSTOLATE.md');

        $this->assertStringContainsString('# Saved Report Groups', $content);
        $this->assertStringContainsString('## Christmas break service', $content);
        $this->assertStringContainsString($reportGroup->tag, $content);
        $this->assertStringContainsString('I Belong to a Family', $content);
        $this->assertStringContainsString('Creating MAV slides', $content);
        $this->assertStringContainsString('~~September 21, 2025~~', $formationContent);
        $this->assertStringContainsString('~~I Belong to a Family~~', $formationContent);
        $this->assertStringContainsString('~~September 8, 2025~~', $socialContent);
        $this->assertStringContainsString('~~Creating MAV slides~~', $socialContent);

        $management = $this->get(route('reports.show', $reportGroup));

        $management->assertOk();
        $management->assertSee('Saved Report Management');
        $management->assertSee('Edit Saved Report');
        $management->assertSee($reportGroup->display_label);
    }

    public function test_locked_entries_are_tagged_and_cannot_be_reused_until_the_report_is_deleted(): void
    {
        $this->get('/')->assertOk();

        $formationEntry = FormationEntry::query()->firstOrFail();

        $this->post(route('report-groups.store'), [
            'title' => 'Locked report',
            'selected_entries' => [
                'formation:'.$formationEntry->id,
            ],
        ])->assertRedirect();

        $reportGroup = ReportGroup::query()->firstOrFail();

        $overview = $this->get('/');

        $overview->assertOk();
        $overview->assertSee($reportGroup->compact_label);
        $overview->assertDontSee('Locked to '.$reportGroup->display_label);
        $this->assertDatabaseCount('formation_entries', 2);

        $formationContent = File::get($this->vaultPath.DIRECTORY_SEPARATOR.'FORMATION.md');

        $this->assertStringContainsString('~~September 21, 2025~~', $formationContent);

        $retry = $this->from(route('dashboard'))->post(route('report-groups.store'), [
            'title' => 'Second report',
            'selected_entries' => [
                'formation:'.$formationEntry->id,
            ],
        ]);

        $retry->assertRedirect(route('dashboard'));
        $retry->assertSessionHasErrors('selected_entries');
        $this->assertDatabaseCount('report_groups', 1);

        $this->delete(route('reports.destroy', $reportGroup))
            ->assertRedirect(route('reports.index'));

        $this->assertDatabaseCount('report_groups', 0);
        $this->assertStringNotContainsString('~~September 21, 2025~~', File::get($this->vaultPath.DIRECTORY_SEPARATOR.'FORMATION.md'));

        $this->post(route('report-groups.store'), [
            'title' => 'Unlocked again',
            'selected_entries' => [
                'formation:'.$formationEntry->id,
            ],
        ])->assertRedirect();

        $this->assertDatabaseCount('report_groups', 1);
        $this->assertDatabaseHas('report_groups', [
            'title' => 'Unlocked again',
        ]);
    }

    public function test_saved_report_management_page_can_rename_a_report(): void
    {
        $this->get('/')->assertOk();

        $this->post(route('report-groups.store'), [
            'title' => 'Initial name',
            'selected_entries' => [
                'formation:'.FormationEntry::query()->firstOrFail()->id,
            ],
        ])->assertRedirect();

        $reportGroup = ReportGroup::query()->firstOrFail();

        $response = $this->patch(route('reports.update', $reportGroup), [
            'title' => 'Renamed report',
        ]);

        $response->assertRedirect(route('reports.show', $reportGroup->fresh()));
        $this->assertDatabaseHas('report_groups', [
            'tag' => $reportGroup->tag,
            'title' => 'Renamed report',
        ]);

        $content = File::get($this->vaultPath.DIRECTORY_SEPARATOR.'REPORT GROUPS.md');

        $this->assertStringContainsString('## Renamed report', $content);
    }

    public function test_index_page_can_show_a_saved_report_snapshot(): void
    {
        $this->get('/')->assertOk();

        $formationEntry = FormationEntry::query()->firstOrFail();
        $socialEntry = SocialApostolateEntry::query()->firstOrFail();

        $this->post(route('report-groups.store'), [
            'title' => 'Chosen report',
            'selected_entries' => [
                'formation:'.$formationEntry->id,
                'social_apostolate:'.$socialEntry->id,
            ],
        ])->assertRedirect();

        $reportGroup = ReportGroup::query()->firstOrFail();

        $response = $this->get(route('indexes.show', ['type' => 'social_apostolate', 'report' => $reportGroup->tag]));

        $response->assertOk();
        $response->assertSee('Chosen report');
        $response->assertSee('Add new records straight into this saved report here');
        $response->assertSee('Creating MAV slides');
        $response->assertSee('Add Saved Record');
    }

    public function test_dashboard_hides_categories_with_no_remaining_available_entries(): void
    {
        $this->get('/')->assertOk();

        $selectedEntries = FormationEntry::query()
            ->orderBy('served_on')
            ->pluck('id')
            ->map(fn (int $id): string => 'formation:'.$id)
            ->all();

        $this->post(route('report-groups.store'), [
            'title' => 'Formation complete',
            'selected_entries' => $selectedEntries,
        ])->assertRedirect();

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Already Saved Or Locked');
        $response->assertSee('data-hidden-save-type="formation"', false);
        $response->assertDontSee('data-save-group-card="formation"', false);
        $response->assertSee('data-save-group-card="parish_involvement"', false);
    }

    public function test_manual_academic_year_snapshot_can_collect_saved_reports(): void
    {
        $this->get('/')->assertOk();

        $this->post(route('report-groups.store'), [
            'title' => 'Formation archive',
            'selected_entries' => [
                'formation:'.FormationEntry::query()->orderBy('id')->firstOrFail()->id,
            ],
        ])->assertRedirect();

        $this->post(route('report-groups.store'), [
            'title' => 'Social archive',
            'selected_entries' => [
                'social_apostolate:'.SocialApostolateEntry::query()->orderBy('id')->firstOrFail()->id,
            ],
        ])->assertRedirect();

        $reportGroups = ReportGroup::query()->with('items')->orderBy('id')->get();

        $response = $this->post(route('academic-year-snapshots.store'), [
            'title' => 'AY 2025-2026 Final Archive',
            'selected_report_groups' => $reportGroups->pluck('id')->all(),
        ]);

        $snapshot = AcademicYearSnapshot::query()->with('items')->firstOrFail();

        $response->assertRedirect(route('academic-year-snapshots.show', $snapshot));
        $this->assertCount(2, $snapshot->items);
        $this->assertStringContainsString('AY 2025-2026 Final Archive', File::get($this->vaultPath.DIRECTORY_SEPARATOR.'ACADEMIC YEAR SNAPSHOTS.md'));
        $this->assertStringContainsString('Formation archive', File::get($this->vaultPath.DIRECTORY_SEPARATOR.'ACADEMIC YEAR SNAPSHOTS.md'));
        $this->assertStringContainsString('Social archive', File::get($this->vaultPath.DIRECTORY_SEPARATOR.'ACADEMIC YEAR SNAPSHOTS.md'));
    }

    public function test_academic_year_snapshot_picker_hides_saved_reports_that_are_already_archived(): void
    {
        $this->get('/')->assertOk();

        $this->post(route('report-groups.store'), [
            'title' => 'Formation archive',
            'selected_entries' => [
                'formation:'.FormationEntry::query()->orderBy('id')->firstOrFail()->id,
            ],
        ])->assertRedirect();

        $this->post(route('report-groups.store'), [
            'title' => 'Social archive',
            'selected_entries' => [
                'social_apostolate:'.SocialApostolateEntry::query()->orderBy('id')->firstOrFail()->id,
            ],
        ])->assertRedirect();

        $reportGroups = ReportGroup::query()->orderBy('id')->get();

        $this->post(route('academic-year-snapshots.store'), [
            'title' => 'AY 2025-2026 Final Archive',
            'selected_report_groups' => [$reportGroups->first()->id],
        ])->assertRedirect();

        $response = $this->get(route('academic-year-snapshots.index'));

        $response->assertOk();
        $response->assertDontSee('data-available-archive-report="'.$reportGroups->first()->id.'"', false);
        $response->assertSee('data-hidden-archive-report="'.$reportGroups->first()->id.'"', false);
        $response->assertSee('data-available-archive-report="'.$reportGroups->last()->id.'"', false);
    }

    public function test_live_entry_forms_use_free_form_inputs_and_accept_values_outside_reference_lists(): void
    {
        $formationPage = $this->get(route('indexes.show', ['type' => 'formation']));

        $formationPage->assertOk();
        $formationPage->assertSee('<input id="cycle-code" name="cycle_code" type="text"', false);
        $formationPage->assertSee('<input id="module-code" name="module_code" type="text"', false);
        $formationPage->assertSee('<input id="title" name="title" type="text"', false);
        $formationPage->assertDontSee('<select id="cycle-code"', false);
        $formationPage->assertDontSee('<select id="module-code"', false);
        $formationPage->assertDontSee('<select id="title"', false);

        $socialPage = $this->get(route('indexes.show', ['type' => 'social_apostolate']));

        $socialPage->assertOk();
        $socialPage->assertSee('<input id="about" name="about" type="text"', false);
        $socialPage->assertDontSee('<select id="about"', false);

        $response = $this->post(route('entries.store'), [
            'type' => 'formation',
            'served_on' => '2025-10-05',
            'cycle_code' => 'C9',
            'module_code' => 'M9',
            'title' => 'Custom formation topic',
            'time_start' => '15:00',
            'time_end' => '16:00',
        ]);

        $response->assertRedirect(route('indexes.show', ['type' => 'formation']));
        $this->assertDatabaseHas('formation_entries', [
            'served_on' => '2025-10-05',
            'cycle_code' => 'C9',
            'module_code' => 'M9',
            'title' => 'Custom formation topic',
            'time_start' => '15:00:00',
            'time_end' => '16:00:00',
        ]);
    }

    public function test_posting_a_social_entry_rewrites_the_markdown_table_and_resyncs_the_database(): void
    {
        $response = $this->post(route('entries.store'), [
            'type' => 'social_apostolate',
            'served_on' => '2025-09-29',
            'about' => 'Preparing outreach handouts',
            'time_start' => '13:00',
            'time_end' => '14:30',
        ]);

        $response->assertRedirect(route('indexes.show', ['type' => 'social_apostolate']));

        $this->assertDatabaseHas('social_apostolate_entries', [
            'served_on' => '2025-09-29',
            'about' => 'Preparing outreach handouts',
            'time_start' => '13:00:00',
            'time_end' => '14:30:00',
        ]);

        $content = File::get($this->vaultPath.DIRECTORY_SEPARATOR.'SOCIAL APOSTOLATE.md');

        $this->assertStringContainsString('## Service Records', $content);
        $this->assertStringContainsString('| September 29, 2025 | Preparing outreach handouts | 1:00 PM | 2:30 PM |', $content);
        $this->assertStringContainsString('records:', $content);
        $this->assertStringNotContainsString('### September 2025', $content);
    }
}
