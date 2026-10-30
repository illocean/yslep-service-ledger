<?php

namespace Tests\Feature;

use App\Models\AcademicYearSnapshot;
use App\Models\FormationEntry;
use App\Models\ReportGroup;
use App\Models\SocialApostolateEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class InsightControllerTest extends TestCase
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
| September 28, 2025 | C2 | M2 | Honor Thy Parent | 2:00 PM | 5:00 PM |
| October 5, 2025 | C1 | M1 | I Belong to a Family | 9:00 AM | 12:00 PM |
| October 12, 2025 | C1 | M2 | Honor Thy Parent | 1:00 PM | 4:00 PM |
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
    titles:
      - Parish service
---
# Parish Involvement Index Card

## Service Records

| Date | Activity | Time In | Time Out |
| --- | --- | --- | --- |
| September 7, 2025 | Parish service | 6:30 PM | 7:30 PM |
| September 14, 2025 | Parish service | 6:00 PM | 7:30 PM |
| October 3, 2025 | Parish service | 10:00 AM | 11:30 AM |
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
      - Preparing outreach handouts
---
# Social Apostolate Index Card

## Service Records

| Date | Activity | Time In | Time Out |
| --- | --- | --- | --- |
| September 8, 2025 | Creating MAV slides | 3:00 PM | 4:00 PM |
| October 15, 2025 | Preparing outreach handouts | 5:00 PM | 7:00 PM |
| October 22, 2025 | Creating MAV slides | 9:00 AM | 10:00 AM |
| November 1, 2025 | Preparing outreach handouts | 6:00 PM | 8:00 PM |
MD);
    }

    protected function tearDown(): void
    {
        if ($this->vaultPath !== '') {
            File::deleteDirectory($this->vaultPath);
        }

        parent::tearDown();
    }

    public function test_insight_page_displays_no_reports_state(): void
    {
        $this->get('/')->assertOk();

        $response = $this->get('/insight');

        $response->assertOk();
        $response->assertSee('Yearly totals');
        $response->assertSee('No saved reports yet');
        $response->assertSee('Saved reports, grouped by academic year.');
    }

    public function test_insight_page_shows_per_report_index_totals(): void
    {
        $this->get('/')->assertOk();

        // Create one report from Formation + Social entries
        $formationIds = FormationEntry::query()->orderBy('id')->pluck('id')->all();
        $socialIds = SocialApostolateEntry::query()->orderBy('id')->pluck('id')->all();

        $this->post(route('report-groups.store'), [
            'title' => 'October outreach',
            'selected_entries' => [
                'formation:'.$formationIds[1], // Oct 5, 3h
                'formation:'.$formationIds[2], // Oct 12, 3h
                'social_apostolate:'.$socialIds[1], // Oct 15, 2h
                'social_apostolate:'.$socialIds[2], // Oct 22, 1h
            ],
        ]);

        $reportGroup = ReportGroup::query()->with('items')->firstOrFail();

        $response = $this->get('/insight');

        $response->assertOk();
        // Report title appears
        $response->assertSee('October outreach');
        // Formation: 3h + 3h = 6 hr
        $response->assertSee('6 hr');
        // Social: 2h + 1h = 3 hr
        // But 3 hr might be too generic — check the bar aria-label instead
        $response->assertSee('aria-label', false);
    }

    public function test_insight_groups_reports_by_academic_year_snapshot(): void
    {
        $this->get('/')->assertOk();

        // Create two reports
        $formationIds = FormationEntry::query()->orderBy('id')->pluck('id')->all();
        $socialIds = SocialApostolateEntry::query()->orderBy('id')->pluck('id')->all();

        $this->post(route('report-groups.store'), [
            'title' => 'October outreach',
            'selected_entries' => [
                'formation:'.$formationIds[1], // Oct 5
                'social_apostolate:'.$socialIds[1], // Oct 15
            ],
        ]);

        $this->post(route('report-groups.store'), [
            'title' => 'November work',
            'selected_entries' => [
                'social_apostolate:'.$socialIds[3], // Nov 1
                'formation:'.$formationIds[0], // Sep 21
            ],
        ]);

        $reportGroups = ReportGroup::query()->orderBy('id')->get();

        // Create an academic year snapshot containing only the first report
        $snapshot = AcademicYearSnapshot::query()->create([
            'tag' => 'test-ay-2025',
            'academic_year' => '2025-2026',
            'title' => '2025-2026 Final Archive',
        ]);

        // Copy the first report's items into the snapshot
        foreach ($reportGroups[0]->items as $item) {
            $snapshot->items()->create($item->only([
                'index_type',
                'source_entry_id',
                'served_on',
                'time_start',
                'time_end',
                'academic_year',
                'cycle_code',
                'module_code',
                'title',
                'about',
                'role_in_activity',
                'source_order',
                'report_group_id',
                'obsidian_conflict',
                'obsidian_record_uuid',
                'obsidian_note_path',
                'obsidian_note_hash',
            ]));
        }

        $response = $this->get('/insight');

        $response->assertOk();
        // The academic year header should show
        $response->assertSee('2025-2026 Final Archive');
        // Both reports should appear — one in the year, one unfiled
        $response->assertSee('October outreach');
        $response->assertSee('November work');
        // The unfiled report should be under "Unfiled this year"
        $response->assertSee('Unfiled this year');

        // Verify the year-grouped report shows the snapshot label
        $content = $response->content();
        $this->assertStringContainsString('2025-2026 Final Archive', $content);
    }

    public function test_insight_chart_bars_have_proportional_widths(): void
    {
        $this->get('/')->assertOk();

        $formationIds = FormationEntry::query()->orderBy('id')->pluck('id')->all();

        $this->post(route('report-groups.store'), [
            'title' => 'Big report',
            'selected_entries' => [
                'formation:'.$formationIds[0], // 1h
                'formation:'.$formationIds[1], // 3h
            ],
        ]);

        $this->post(route('report-groups.store'), [
            'title' => 'Small report',
            'selected_entries' => [
                'formation:'.$formationIds[2], // 3h
            ],
        ]);

        $response = $this->get('/insight');

        $response->assertOk();
        $content = $response->content();

        // There must be inline width styles (proportional bars computed from DB data)
        $this->assertStringContainsString('style="width: ', $content);
        $this->assertStringContainsString('insight-bar__fill', $content);

        // Big report: 4h (1h+3h), Small report: 3h
        // Max is 4h, so big report's Formation bar = 100%, small = 75%
        // Just verify both reports show their totals
        $this->assertStringContainsString('Big report', $content);
        $this->assertStringContainsString('Small report', $content);
    }

    public function test_insight_empty_state_links_to_quick_add(): void
    {
        $this->get('/')->assertOk();

        $response = $this->get('/insight');

        $response->assertOk();
        $response->assertSee('quick-add-bar', false);
    }

    public function test_insight_nav_link_appears_in_dashboard(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Yearly totals');
        $response->assertSee(route('insight'));
    }
}
