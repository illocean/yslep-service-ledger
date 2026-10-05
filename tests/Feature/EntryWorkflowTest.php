<?php

namespace Tests\Feature;

use App\Enums\IndexType;
use App\Models\AcademicYearSnapshot;
use App\Models\ReportGroup;
use App\Services\ObsidianSyncService;
use App\Services\ReportGroupVaultSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EntryWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private string $vaultPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->vaultPath = storage_path('framework/testing/entry-workflow');
        File::deleteDirectory($this->vaultPath);
        File::ensureDirectoryExists($this->vaultPath);
        config()->set('obsidian.vault_path', $this->vaultPath);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->vaultPath);
        parent::tearDown();
    }

    public static function entryYears(): array
    {
        return [
            'formation without year' => [IndexType::Formation, null],
            'formation with year' => [IndexType::Formation, '2030-2031'],
            'parish without year' => [IndexType::ParishInvolvement, null],
            'parish with year' => [IndexType::ParishInvolvement, '2030-2031'],
            'social without year' => [IndexType::SocialApostolate, null],
            'social with year' => [IndexType::SocialApostolate, '2030-2031'],
        ];
    }

    #[DataProvider('entryYears')]
    public function test_entry_year_and_role_survive_create_edit_and_vault_sync(IndexType $type, ?string $year): void
    {
        $payload = $this->payload($type) + ['academic_year' => $year];

        $this->post(route('entries.store'), $payload)->assertSessionHasNoErrors()->assertRedirect();
        app(ObsidianSyncService::class)->syncAll();

        $entry = $type->modelClass()::query()->sole();
        $this->assertSame($year, $entry->academic_year);
        if ($type !== IndexType::Formation) {
            $this->assertSame('Volunteer', $entry->role_in_activity);
        }
        $this->assertDatabaseCount('academic_year_snapshots', 0);
        $this->assertDatabaseCount('report_groups', 0);

        $this->patch(route('entries.update', $entry->id), array_replace($payload, ['academic_year' => '2031-2032']))
            ->assertSessionHasNoErrors()->assertRedirect();
        app(ObsidianSyncService::class)->syncAll();

        $this->assertSame('2031-2032', $entry->fresh()->academic_year);
        $this->assertSame(1, $type->modelClass()::query()->count());

        $this->patch(route('entries.update', $entry->id), array_replace($payload, ['academic_year' => '']))
            ->assertSessionHasNoErrors()->assertRedirect();
        app(ObsidianSyncService::class)->syncAll();
        $this->assertNull($entry->fresh()->academic_year);
    }

    public function test_invalid_entry_year_and_time_do_not_write_records_or_notes(): void
    {
        $this->post(route('entries.store'), array_replace($this->payload(IndexType::Formation), [
            'academic_year' => 'not a year',
            'time_end' => '08:00',
        ]))->assertSessionHasErrors(['academic_year', 'time_end']);

        $this->assertDatabaseCount('formation_entries', 0);
        $this->assertSame([], File::allFiles($this->vaultPath));
    }

    public function test_saved_report_forms_target_report_routes_and_preserve_year_and_role(): void
    {
        $report = $this->createReport();
        $payload = $this->payload(IndexType::ParishInvolvement);
        unset($payload['type']);

        $this->post(route('reports.records.store', $report), $payload + [
            'index_type' => 'parish_involvement',
            'academic_year' => '2030-2031',
        ])->assertSessionHasNoErrors()->assertRedirect(route('reports.show', $report));

        $item = $report->items()->where('index_type', 'parish_involvement')->sole();
        app(ReportGroupVaultSyncService::class)->pullFromVault();
        $this->assertSame('2030-2031', $item->fresh()->academic_year);
        $this->assertSame('Volunteer', $item->fresh()->role_in_activity);
        $this->assertDatabaseCount('parish_involvement_entries', 0);

        $this->get(route('reports.show', $report))
            ->assertSee('action="'.route('reports.records.store', $report).'"', false)
            ->assertSee('name="index_type"', false)
            ->assertSee('name="_method" value="PATCH"', false);
        $this->get(route('indexes.show', ['type' => 'parish_involvement', 'scope' => 'report', 'report' => $report->tag]))
            ->assertSee('action="'.route('reports.records.store', $report).'"', false);
    }

    public function test_removing_a_report_record_updates_totals_and_releases_the_live_entry(): void
    {
        $report = $this->createReport();
        $item = $report->items()->sole();
        $sourceId = $item->source_entry_id;

        $this->delete(route('reports.records.destroy', [$report, $item]))->assertRedirect();

        $this->assertSoftDeleted($item);
        $response = $this->get(route('reports.index'))->assertOk();
        $this->assertSame(0, $response->viewData('totalRecords'));
        $response = $this->get(route('indexes.show', ['type' => 'formation', 'scope' => 'unsaved']))->assertOk();
        $this->assertSame([$sourceId], $response->viewData('entries')->modelKeys());
        $this->post(route('report-groups.store'), [
            'title' => 'Reused entry',
            'selected_entries' => ['formation:'.$sourceId],
        ])->assertSessionHasNoErrors()->assertRedirect();
    }

    public function test_vault_year_changes_import_without_false_conflicts_on_legacy_report_notes(): void
    {
        $report = $this->createReport();
        $item = $report->items()->sole();
        $item->update(['academic_year' => null]);
        $sync = app(ReportGroupVaultSyncService::class);
        $sync->syncReportGroup($report->fresh('items'));
        $item->refresh();
        $contents = File::get($item->obsidian_note_path);
        $this->assertStringNotContainsString('academic_year:', $contents);
        File::put($item->obsidian_note_path, preg_replace('/^---/', "---\nacademic_year: 2032-2033", $contents, 1));

        $sync->pullFromVault();

        $this->assertSame('2032-2033', $item->fresh()->academic_year);
        $this->assertFalse((bool) $item->fresh()->obsidian_conflict);
    }

    public function test_failed_snapshot_submission_does_not_reselect_an_unchecked_prefilled_report(): void
    {
        $report = $this->createReport();
        $url = route('academic-year-snapshots.index', ['prefill' => $report->id]);

        $this->from($url)->post(route('academic-year-snapshots.store'), ['academic_year' => '2030-2031'])
            ->assertSessionHasErrors('selected_report_groups');

        $response = $this->get($url)->assertOk();
        $this->assertSame([], $response->viewData('prefilledReportGroupIds')->all());
        $this->assertDatabaseCount('academic_year_snapshots', 0);
    }

    public function test_snapshot_requires_explicit_year_and_selection_and_keeps_saved_reports(): void
    {
        $report = $this->createReport();

        $this->get(route('academic-year-snapshots.index'))->assertOk();
        $this->assertDatabaseCount('academic_year_snapshots', 0);
        $this->post(route('academic-year-snapshots.store'), ['selected_report_groups' => [$report->id]])
            ->assertSessionHasErrors('academic_year');
        $this->post(route('academic-year-snapshots.store'), ['academic_year' => '2030-2031'])
            ->assertSessionHasErrors('selected_report_groups');
        $this->post(route('academic-year-snapshots.store'), ['academic_year' => ['2030-2031'], 'selected_report_groups' => [$report->id]])
            ->assertSessionHasErrors('academic_year');
        $this->assertDatabaseCount('academic_year_snapshots', 0);

        $this->post(route('academic-year-snapshots.store'), [
            'academic_year' => '2030-2031',
            'selected_report_groups' => [$report->id],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $snapshot = AcademicYearSnapshot::query()->sole();
        $this->assertSame('2030-2031', $snapshot->academic_year);
        $this->assertSame('2025-2026', $snapshot->items()->sole()->academic_year);
        $this->assertModelExists($report);
        $this->assertSame(1, $report->items()->count());

        $this->post(route('academic-year-snapshots.store'), [
            'academic_year' => '2031-2032',
            'selected_report_groups' => [$report->id],
        ])->assertSessionHasErrors('selected_report_groups');
        $this->assertSame('2030-2031', $snapshot->fresh()->academic_year);
        $this->assertDatabaseCount('academic_year_snapshots', 1);

        $this->delete(route('academic-year-snapshots.destroy', $snapshot))->assertRedirect();
        $this->assertModelExists($report);
        $this->assertDatabaseCount('academic_year_snapshot_items', 0);
    }

    public function test_all_pages_offer_three_types_and_optional_year_without_duplicate_description(): void
    {
        foreach (['/', '/indexes/formation', '/indexes/parish_involvement', '/indexes/social_apostolate', '/reports', '/academic-year-snapshots'] as $path) {
            $response = $this->get($path)->assertOk();
            $dom = new \DOMDocument;
            @$dom->loadHTML($response->getContent());
            $xpath = new \DOMXPath($dom);
            $this->assertSame(3, $xpath->query('//button[@class="quick-add-option"]')->length);
            $this->assertSame(3, $xpath->query('//dialog//input[@name="academic_year" and not(@required)]')->length);
            $this->assertSame(0, $xpath->query('//dialog//textarea[@name="about"]')->length);
            $this->assertSame(1, $xpath->query('//dialog//input[@name="about"]')->length);
            $this->assertSame(3, $xpath->query('//dialog//input[@name="served_on"]')->length);
            $this->assertSame(3, $xpath->query('//dialog//input[@name="time_start"]')->length);
            $ids = array_map(fn (\DOMElement $element): string => $element->getAttribute('id'), iterator_to_array($xpath->query('//*[@id]')));
            $this->assertSame($ids, array_values(array_unique($ids)));
        }
    }

    private function createReport(): ReportGroup
    {
        $this->post(route('entries.store'), $this->payload(IndexType::Formation) + ['academic_year' => '2025-2026'])
            ->assertSessionHasNoErrors();
        $entry = IndexType::Formation->modelClass()::query()->sole();
        $this->post(route('report-groups.store'), [
            'title' => 'Selected report',
            'selected_entries' => ['formation:'.$entry->id],
        ])->assertSessionHasNoErrors();

        return ReportGroup::query()->sole();
    }

    private function payload(IndexType $type): array
    {
        return [
            'type' => $type->value,
            'served_on' => '2025-09-21',
            'time_start' => '09:00',
            'time_end' => '10:30',
            ...match ($type) {
                IndexType::Formation => ['cycle_code' => 'C1', 'module_code' => 'M1', 'title' => 'Formation session'],
                IndexType::ParishInvolvement => ['role_in_activity' => 'Volunteer'],
                IndexType::SocialApostolate => ['about' => 'Community outreach', 'role_in_activity' => 'Volunteer'],
            },
        ];
    }
}
