<?php

namespace Tests\Feature;

use App\Enums\IndexType;
use App\Models\FormationEntry;
use App\Services\ObsidianSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class LiveConflictResolutionUiTest extends TestCase
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

        $this->vaultPath = storage_path('framework/testing/live-conflict-resolution-ui');
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

    private function formationFile(): string
    {
        return $this->vaultPath.DIRECTORY_SEPARATOR.'FORMATION.md';
    }

    private function conflictedFormationEntry(): FormationEntry
    {
        File::put($this->formationFile(), <<<'MD'
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
    title: 'Original Title'
    time_start: '15:00:00'
    time_end: '16:00:00'
---
# Formation Index Card

## Service Records

MD);

        $this->syncService->syncIndex(IndexType::Formation);

        $entry = FormationEntry::query()->firstOrFail();
        $uuid = $entry->obsidian_record_uuid;
        $originalHash = $entry->obsidian_content_hash;

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
    cycle_code: 'C1'
    module_code: 'M1'
    title: 'Vault Edited Title'
    time_start: '15:00:00'
    time_end: '16:00:00'
---
# Formation Index Card

## Service Records

MD);

        $entry->forceFill([
            'title' => 'DB Edited Title',
            'obsidian_content_hash' => $originalHash,
            'updated_at' => now()->subMinute(),
        ])->save();

        $this->syncService->syncIndex(IndexType::Formation);

        $entry = $entry->fresh();
        $this->assertTrue($entry->obsidian_conflict, 'Setup must produce a conflicted live entry.');

        return $entry;
    }

    public function test_index_page_offers_inline_conflict_resolution_for_live_entries(): void
    {
        $entry = $this->conflictedFormationEntry();

        $response = $this->get(route('indexes.show', ['type' => 'formation']));

        $response->assertOk();

        $content = $response->getContent();

        $this->assertStringContainsString(
            'action="'.e(route('entries.conflict.accept-vault', $entry)).'"',
            $content,
            'Index page must offer an inline Accept Vault action for a conflicted live entry.',
        );

        $this->assertStringContainsString(
            'action="'.e(route('entries.conflict.accept-db', $entry)).'"',
            $content,
            'Index page must offer an inline Keep Database action for a conflicted live entry.',
        );

        $this->assertMatchesRegularExpression(
            '/'.preg_quote('action="'.e(route('entries.conflict.accept-vault', $entry)).'"', '/').'.{0,400}name="type" value="formation"/s',
            $content,
            'The conflict form must carry the index type the controller requires.',
        );
    }

    public function test_conflict_resolution_redirect_keeps_report_scope(): void
    {
        $entry = $this->conflictedFormationEntry();

        $response = $this->post(route('entries.conflict.accept-db', $entry), [
            'type' => 'formation',
            'scope' => 'report',
            'report' => 'TEST-TAG',
        ]);

        $response->assertRedirect(route('indexes.show', [
            'type' => 'formation',
            'scope' => 'report',
            'report' => 'TEST-TAG',
        ]));

        $this->assertFalse($entry->fresh()->obsidian_conflict, 'Conflict should be resolved.');
    }
}
