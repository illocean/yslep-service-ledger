<?php

namespace Tests\Feature;

use App\Enums\IndexType;
use App\Services\ObsidianSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ReportIndexBuilderTest extends TestCase
{
    use RefreshDatabase;

    private string $vaultPath = '';

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_pgsql')) {
            $this->markTestSkipped('The pdo_pgsql extension is required for the PostgreSQL test database.');
        }

        parent::setUp();

        $this->vaultPath = storage_path('framework/testing/report-index-builder');

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

    public function test_reports_index_renders_the_save_group_builder_open_when_there_are_no_reports(): void
    {
        File::put($this->vaultPath.DIRECTORY_SEPARATOR.'FORMATION.md', <<<'MD'
---
index: formation
card_title: Formation Index Card
profile:
  school_year: "2025-2026"
entry_options:
  academic_years:
    - "2025-2026"
records:
  - served_on: '2025-09-21'
    cycle_code: 'C1'
    module_code: 'M1'
    title: 'Service Session'
    time_start: '15:00:00'
    time_end: '16:00:00'
---
# Formation Index Card

## Service Records

MD);

        app(ObsidianSyncService::class)->syncIndex(IndexType::Formation);

        $response = $this->get(route('reports.index'));

        $response->assertOk();
        $response->assertSee('data-save-group-card="formation"', false);
        $response->assertSee('Build a saved report from unassigned live entries');
        $response->assertSee('No reports yet');
        $this->assertStringNotContainsString('<details', $response->getContent());
        $this->assertMatchesRegularExpression(
            '/<section[^>]*id="save-group-builder"[^>]*>/',
            $response->getContent(),
            'The builder should always render as a visible panel (no <details> toggle).',
        );
    }

    public function test_save_group_builder_shows_the_parish_activity_in_details(): void
    {
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

| Date | Activity | Time In | Time Out |
| --- | --- | --- | --- |
| September 7, 2025 | Parish service | 6:30 PM | 7:30 PM |
MD);

        app(ObsidianSyncService::class)->syncIndex(IndexType::ParishInvolvement);

        $response = $this->get(route('reports.index'));

        $response->assertOk();
        $response->assertSee('data-save-group-card="parish_involvement"', false);
        $response->assertSee('Parish service');
        $this->assertStringNotContainsString('<div>Parish Involvement</div>', $response->getContent());
    }
}
