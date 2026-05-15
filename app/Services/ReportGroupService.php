<?php

namespace App\Services;

use App\Enums\IndexType;
use App\Models\ReportGroup;
use App\Models\ReportGroupItem;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Yaml\Yaml;

class ReportGroupService
{
    public function __construct(
        private readonly ObsidianSyncService $obsidianSyncService,
        private readonly ReportGroupVaultSyncService $reportGroupVaultSyncService,
    ) {}

    public function all(): EloquentCollection
    {
        return ReportGroup::query()
            ->with('items')
            ->orderByDesc('created_at')
            ->get();
    }

    public function findByTag(?string $tag): ?ReportGroup
    {
        if (blank($tag)) {
            return null;
        }

        return ReportGroup::query()
            ->with('items')
            ->where('tag', $tag)
            ->first();
    }

    public function create(?string $title, array $selectionMap): ReportGroup
    {
        $snapshots = $this->resolveSnapshots($selectionMap);

        if ($snapshots === []) {
            throw ValidationException::withMessages([
                'selected_entries' => 'Choose at least one live entry before saving a report group.',
            ]);
        }

        $reportGroup = DB::transaction(function () use ($title, $snapshots): ReportGroup {
            $reportGroup = ReportGroup::query()->create([
                'tag' => $this->generateUniqueTag(),
                'title' => $title,
            ]);

            $payload = [];
            $timestamp = now();

            foreach ($snapshots as $index => $snapshot) {
                $payload[] = [
                    ...$snapshot,
                    'report_group_id' => $reportGroup->id,
                    'source_order' => $index + 1,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];
            }

            ReportGroupItem::query()->insert($payload);

            return $reportGroup->fresh('items');
        });

        $this->syncMarkdown();
        $this->obsidianSyncService->refreshAssignmentFormatting();
        $this->reportGroupVaultSyncService->syncReportGroup($reportGroup);

        return $reportGroup;
    }

    public function update(ReportGroup $reportGroup, ?string $title): ReportGroup
    {
        $reportGroup->forceFill([
            'title' => $title,
        ])->save();

        $this->syncMarkdown();
        $this->reportGroupVaultSyncService->syncReportGroup($reportGroup->fresh('items'));

        return $reportGroup->fresh('items');
    }

    public function delete(ReportGroup $reportGroup): void
    {
        DB::transaction(function () use ($reportGroup): void {
            $reportGroup->delete();
        });

        $this->reportGroupVaultSyncService->deleteReportGroupFiles($reportGroup);
        $this->syncMarkdown();
        $this->obsidianSyncService->refreshAssignmentFormatting();
    }

    public function addItem(ReportGroup $reportGroup, IndexType $type, array $payload): ReportGroupItem
    {
        $item = DB::transaction(function () use ($reportGroup, $type, $payload): ReportGroupItem {
            return $reportGroup->items()->create([
                ...$payload,
                'index_type' => $type->value,
                'source_entry_id' => null,
                'source_order' => $this->nextSourceOrder($reportGroup),
            ]);
        });

        $this->syncMarkdown();
        $this->obsidianSyncService->refreshAssignmentFormatting();
        $this->reportGroupVaultSyncService->syncReportGroup($reportGroup->fresh('items'));

        return $item->fresh();
    }

    public function updateItem(
        ReportGroup $reportGroup,
        ReportGroupItem $reportGroupItem,
        IndexType $type,
        array $payload,
    ): ReportGroupItem {
        $reportGroupItem->forceFill([
            ...$payload,
            'index_type' => $type->value,
        ])->save();

        $this->syncMarkdown();
        $this->obsidianSyncService->refreshAssignmentFormatting();
        $this->reportGroupVaultSyncService->syncReportGroup($reportGroup->fresh('items'));

        return $reportGroupItem->fresh();
    }

    public function deleteItem(ReportGroup $reportGroup, ReportGroupItem $reportGroupItem): void
    {
        $reportGroupItem->delete();

        $this->syncMarkdown();
        $this->obsidianSyncService->refreshAssignmentFormatting();
        $this->reportGroupVaultSyncService->syncReportGroup($reportGroup->fresh('items'));
    }

    public function assignedReportLookup(): array
    {
        return ReportGroupItem::query()
            ->with('reportGroup')
            ->whereNotNull('source_entry_id')
            ->get()
            ->mapWithKeys(function (ReportGroupItem $item): array {
                if ($item->reportGroup === null || $item->source_entry_id === null) {
                    return [];
                }

                return [
                    $this->entryKey($item->index_type, $item->source_entry_id) => $item->reportGroup,
                ];
            })
            ->all();
    }

    public function syncMarkdown(): void
    {
        $reports = $this->all();
        $path = $this->reportGroupsPath();
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($path, $this->renderMarkdown($reports));
    }

    public function reportGroupsPath(): string
    {
        return rtrim(config('obsidian.vault_path'), '\\/').DIRECTORY_SEPARATOR.config('obsidian.report_groups_file', 'REPORT GROUPS.md');
    }

    private function resolveSnapshots(array $selectionMap): array
    {
        $this->ensureSelectionsAreAvailable($selectionMap);

        $snapshots = [];

        foreach ($selectionMap as $typeValue => $ids) {
            $type = IndexType::from($typeValue);
            $modelClass = $type->modelClass();

            /** @var Collection $entries */
            $entries = $modelClass::query()
                ->whereIn('id', $ids)
                ->orderBy('served_on')
                ->orderBy('source_order')
                ->get();

            foreach ($entries as $entry) {
                $snapshots[] = $this->snapshotFromEntry($type, $entry);
            }
        }

        usort($snapshots, function (array $left, array $right): int {
            return [
                $left['served_on'],
                $left['time_start'],
                $left['index_type'],
                $left['source_order'],
            ] <=> [
                $right['served_on'],
                $right['time_start'],
                $right['index_type'],
                $right['source_order'],
            ];
        });

        return $snapshots;
    }

    private function snapshotFromEntry(IndexType $type, $entry): array
    {
        return [
            'index_type' => $type->value,
            'source_entry_id' => $entry->id,
            'served_on' => $entry->served_on->toDateString(),
            'time_start' => $entry->getRawOriginal('time_start'),
            'time_end' => $entry->getRawOriginal('time_end'),
            'cycle_code' => $entry->cycle_code ?? null,
            'module_code' => $entry->module_code ?? null,
            'title' => $entry->title ?? null,
            'about' => $entry->about ?? null,
            'source_order' => $entry->source_order ?? 0,
        ];
    }

    private function generateUniqueTag(): string
    {
        do {
            $tag = 'RPT-'.now()->format('Ymd-His').'-'.Str::upper(Str::random(4));
        } while (ReportGroup::query()->where('tag', $tag)->exists());

        return $tag;
    }

    private function renderMarkdown(EloquentCollection $reports): string
    {
        $matter = Yaml::dump([
            'index' => 'saved_report_groups',
            'generated_at' => now()->toIso8601String(),
            'report_count' => $reports->count(),
        ], 4, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);

        $lines = [
            "---\n{$matter}---",
            '# Saved Report Groups',
            '',
            '> This file is generated by the web app.',
            '> Your three index files remain the live user-input source of truth.',
            '> Each section below is a saved report snapshot that can span any dates you choose.',
            '',
        ];

        if ($reports->isEmpty()) {
            $lines[] = '## No saved report groups yet';
            $lines[] = '';
            $lines[] = 'Create a report from the web app overview to see it listed here.';

            return implode("\n", $lines)."\n";
        }

        foreach ($reports as $report) {
            $lines[] = '---';
            $lines[] = '## '.($report->title ?: 'Untitled Report Group');
            $lines[] = '';
            $lines[] = '- Tag: `'.$report->tag.'`';
            $lines[] = '- Created: '.$report->created_at?->setTimezone(config('app.timezone'))->format('F j, Y g:i A');
            $lines[] = '- Grand Total: '.$this->formatMinutes($report->items->sum('duration_minutes'));
            $lines[] = '';
            $lines[] = '### Totals by Index';
            $lines[] = '';

            foreach (IndexType::cases() as $type) {
                $items = $report->itemsFor($type->value);
                $lines[] = '- '.$type->label().': '.$items->count().' serve(s), '.$this->formatMinutes($items->sum('duration_minutes'));
            }

            $lines[] = '';

            foreach (IndexType::cases() as $type) {
                $items = $report->itemsFor($type->value);

                if ($items->isEmpty()) {
                    continue;
                }

                $lines[] = '### '.$type->label();
                $lines[] = '';
                $lines = [...$lines, ...$this->renderTable($type, $items)];
                $lines[] = '';
            }
        }

        return rtrim(implode("\n", $lines))."\n";
    }

    private function ensureSelectionsAreAvailable(array $selectionMap): void
    {
        $lockedItems = collect();

        foreach ($selectionMap as $typeValue => $ids) {
            if ($ids === []) {
                continue;
            }

            $lockedItems = $lockedItems->merge(
                ReportGroupItem::query()
                    ->with('reportGroup')
                    ->where('index_type', $typeValue)
                    ->whereIn('source_entry_id', $ids)
                    ->whereNotNull('source_entry_id')
                    ->get()
            );
        }

        if ($lockedItems->isEmpty()) {
            return;
        }

        $labels = $lockedItems
            ->map(fn (ReportGroupItem $item): string => $item->reportGroup?->display_label ?? 'another saved report')
            ->unique()
            ->values();

        $suffix = $labels->count() > 3 ? ', and more' : '';

        throw ValidationException::withMessages([
            'selected_entries' => 'Some selected entries are already locked to '.$labels->take(3)->implode(', ').$suffix.'. Remove them from that saved report first.',
        ]);
    }

    private function renderTable(IndexType $type, Collection $items): array
    {
        $headers = match ($type) {
            IndexType::Formation => ['Date', 'Cycle No.', 'Module No.', 'Title', 'Time In', 'Time Out', 'Hours'],
            IndexType::ParishInvolvement => ['Date', 'Activity', 'Time In', 'Time Out', 'Hours'],
            IndexType::SocialApostolate => ['Date', 'Activity', 'Time In', 'Time Out', 'Hours'],
        };

        $lines = [
            '| '.implode(' | ', $headers).' |',
            '| '.implode(' | ', array_fill(0, count($headers), '---')).' |',
        ];

        foreach ($items as $item) {
            $row = match ($type) {
                IndexType::Formation => [
                    $item->served_on_label,
                    $item->cycle_code,
                    $item->module_code,
                    str_replace('|', '/', $item->title),
                    $item->time_start_label,
                    $item->time_end_label,
                    $item->duration_label,
                ],
                IndexType::ParishInvolvement => [
                    $item->served_on_label,
                    'Parish Involvement',
                    $item->time_start_label,
                    $item->time_end_label,
                    $item->duration_label,
                ],
                IndexType::SocialApostolate => [
                    $item->served_on_label,
                    str_replace('|', '/', $item->about),
                    $item->time_start_label,
                    $item->time_end_label,
                    $item->duration_label,
                ],
            };

            $lines[] = '| '.implode(' | ', $row).' |';
        }

        return $lines;
    }

    private function formatMinutes(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        if ($minutes === 0) {
            return '0 hr';
        }

        if ($remainingMinutes === 0) {
            return $hours.' hr';
        }

        return sprintf('%d hr %02d min', $hours, $remainingMinutes);
    }

    private function entryKey(string $indexType, int $sourceEntryId): string
    {
        return $indexType.':'.$sourceEntryId;
    }

    private function nextSourceOrder(ReportGroup $reportGroup): int
    {
        return (int) ReportGroupItem::query()
            ->where('report_group_id', $reportGroup->id)
            ->max('source_order') + 1;
    }
}
