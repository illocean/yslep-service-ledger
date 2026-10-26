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
        $this->assertMatchesRegularExpression(
            '/<details id="save-group-builder"[^>]*\bopen\b/',
            $response->getContent(),
            'The builder should start open while there are no saved reports to manage.',
        );
    }
}
