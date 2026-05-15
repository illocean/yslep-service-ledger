<?php

namespace App\Services;

use App\Enums\IndexType;
use App\Models\AcademicYearSnapshot;
use App\Models\AcademicYearSnapshotItem;
use App\Models\ReportGroupItem;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Yaml\Yaml;

class AcademicYearSnapshotService
{
    public function all(): EloquentCollection
    {
        return AcademicYearSnapshot::query()
            ->with(['items.reportGroup'])
            ->orderByDesc('created_at')
            ->get();
    }

    public function assignedSnapshotLookup(): array
    {
        return AcademicYearSnapshotItem::query()
            ->with('academicYearSnapshot')
            ->whereNotNull('report_group_id')
            ->get()
            ->mapWithKeys(function (AcademicYearSnapshotItem $item): array {
                if ($item->report_group_id === null || $item->academicYearSnapshot === null) {
                    return [];
                }

                return [$item->report_group_id => $item->academicYearSnapshot];
            })
            ->all();
    }

    public function create(?string $title, string $academicYear, array $reportGroupIds): AcademicYearSnapshot
    {
        $this->ensureReportsAreAvailable($reportGroupIds);
        $items = $this->selectedItems($reportGroupIds);

        if ($items->isEmpty()) {
            throw ValidationException::withMessages([
                'selected_report_groups' => 'Choose at least one saved report before creating the academic year snapshot.',
            ]);
        }

        $snapshot = DB::transaction(function () use ($title, $academicYear, $items): AcademicYearSnapshot {
            $snapshot = AcademicYearSnapshot::query()->create([
                'tag' => $this->generateUniqueTag($academicYear),
                'academic_year' => $academicYear,
                'title' => $title,
            ]);

            $timestamp = now();
            $payload = [];

            foreach ($items->values() as $index => $item) {
                $payload[] = [
                    'academic_year_snapshot_id' => $snapshot->id,
                    'report_group_id' => $item->report_group_id,
                    'report_group_item_id' => $item->id,
                    'source_report_tag' => $item->reportGroup?->tag,
                    'source_report_label' => $item->reportGroup?->compact_label,
                    'index_type' => $item->index_type,
                    'served_on' => $item->served_on->toDateString(),
                    'time_start' => $item->getRawOriginal('time_start'),
                    'time_end' => $item->getRawOriginal('time_end'),
                    'cycle_code' => $item->cycle_code,
                    'module_code' => $item->module_code,
                    'title' => $item->title,
                    'about' => $item->about,
                    'source_order' => $index + 1,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];
            }

            AcademicYearSnapshotItem::query()->insert($payload);

            return $snapshot->fresh(['items.reportGroup']);
        });

        $this->syncMarkdown();

        return $snapshot;
    }

    public function delete(AcademicYearSnapshot $snapshot): void
    {
        DB::transaction(function () use ($snapshot): void {
            $snapshot->delete();
        });

        $this->syncMarkdown();
    }

    public function syncMarkdown(): void
    {
        $snapshots = $this->all();
        $path = $this->snapshotPath();
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($path, $this->renderMarkdown($snapshots));
    }

    public function snapshotPath(): string
    {
        return rtrim(config('obsidian.vault_path'), '\\/').DIRECTORY_SEPARATOR.config('obsidian.academic_year_snapshots_file', 'ACADEMIC YEAR SNAPSHOTS.md');
    }

    private function ensureReportsAreAvailable(array $reportGroupIds): void
    {
        $selectedGroupIds = collect($reportGroupIds)
            ->map(fn (mixed $id): int => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($selectedGroupIds->isEmpty()) {
            return;
        }

        $assignedLookup = $this->assignedSnapshotLookup();
        $blockedSnapshots = $selectedGroupIds
            ->map(fn (int $groupId) => $assignedLookup[$groupId] ?? null)
            ->filter()
            ->unique('id')
            ->values();

        if ($blockedSnapshots->isEmpty()) {
            return;
        }

        $labels = $blockedSnapshots
            ->map(fn (AcademicYearSnapshot $snapshot): string => $snapshot->compact_label)
            ->values();

        $suffix = $labels->count() > 3 ? ', and more' : '';

        throw ValidationException::withMessages([
            'selected_report_groups' => 'Some saved reports are already archived in '.$labels->take(3)->implode(', ').$suffix.'.',
        ]);
    }

    private function selectedItems(array $reportGroupIds): Collection
    {
        $selectedGroupIds = array_values(array_unique(array_map('intval', $reportGroupIds)));

        if ($selectedGroupIds === []) {
            return collect();
        }

        return ReportGroupItem::query()
            ->with('reportGroup')
            ->whereIn('report_group_id', $selectedGroupIds)
            ->orderBy('served_on')
            ->orderBy('source_order')
            ->get()
            ->unique('id')
            ->values();
    }

    private function generateUniqueTag(string $academicYear): string
    {
        $yearToken = Str::upper(Str::of($academicYear)->replaceMatches('/[^0-9A-Za-z]+/', '')->limit(8, ''));

        do {
            $tag = 'AYS-'.($yearToken !== '' ? $yearToken.'-' : '').now()->format('Ymd-His').'-'.Str::upper(Str::random(4));
        } while (AcademicYearSnapshot::query()->where('tag', $tag)->exists());

        return $tag;
    }

    private function renderMarkdown(EloquentCollection $snapshots): string
    {
        $matter = Yaml::dump([
            'index' => 'academic_year_snapshots',
            'generated_at' => now()->toIso8601String(),
            'snapshot_count' => $snapshots->count(),
        ], 4, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);

        $lines = [
            "---\n{$matter}---",
            '# Academic Year Snapshots',
            '',
            '> This file is generated by the web app.',
            '> Each section below is a manual academic-year archive built from selected saved reports.',
            '',
        ];

        if ($snapshots->isEmpty()) {
            $lines[] = '## No academic year snapshots yet';
            $lines[] = '';
            $lines[] = 'Create one from the Academic Years page in the web app.';

            return implode("\n", $lines)."\n";
        }

        foreach ($snapshots as $snapshot) {
            $lines[] = '---';
            $lines[] = '## '.($snapshot->title ?: 'Untitled Academic Year Snapshot');
            $lines[] = '';
            $lines[] = '- Academic Year: '.$snapshot->academic_year;
            $lines[] = '- Tag: `'.$snapshot->tag.'`';
            $lines[] = '- Created: '.$snapshot->created_at?->setTimezone(config('app.timezone'))->format('F j, Y g:i A');
            $lines[] = '- Grand Total: '.$this->formatMinutes($snapshot->items->sum('duration_minutes'));
            $lines[] = '';
            $lines[] = '### Included Saved Reports';
            $lines[] = '';

            foreach ($snapshot->items->map(fn (AcademicYearSnapshotItem $item) => [
                'label' => $item->source_report_display_label,
                'tag' => $item->source_report_tag,
            ])->unique(fn (array $reportMeta): string => $reportMeta['tag'] ?: 'label:'.$reportMeta['label'])->values() as $reportMeta) {
                $lines[] = '- '.$reportMeta['label'].($reportMeta['tag'] ? ' (`'.$reportMeta['tag'].'`)' : '');
            }

            $lines[] = '';

            foreach (IndexType::cases() as $type) {
                $items = $snapshot->itemsFor($type->value);

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

    private function renderTable(IndexType $type, Collection $items): array
    {
        $headers = match ($type) {
            IndexType::Formation => ['Date', 'Cycle No.', 'Module No.', 'Title', 'Time In', 'Time Out', 'Hours', 'Saved Report'],
            IndexType::ParishInvolvement => ['Date', 'Activity', 'Time In', 'Time Out', 'Hours', 'Saved Report'],
            IndexType::SocialApostolate => ['Date', 'Activity', 'Time In', 'Time Out', 'Hours', 'Saved Report'],
        };

        $lines = [
            '| '.implode(' | ', $headers).' |',
            '| '.implode(' | ', array_fill(0, count($headers), '---')).' |',
        ];

        foreach ($items as $item) {
            $savedReport = $item->source_report_display_label;

            $row = match ($type) {
                IndexType::Formation => [
                    $item->served_on_label,
                    $item->cycle_code,
                    $item->module_code,
                    str_replace('|', '/', $item->title),
                    $item->time_start_label,
                    $item->time_end_label,
                    $item->duration_label,
                    str_replace('|', '/', $savedReport),
                ],
                IndexType::ParishInvolvement => [
                    $item->served_on_label,
                    'Parish Involvement',
                    $item->time_start_label,
                    $item->time_end_label,
                    $item->duration_label,
                    str_replace('|', '/', $savedReport),
                ],
                IndexType::SocialApostolate => [
                    $item->served_on_label,
                    str_replace('|', '/', $item->about),
                    $item->time_start_label,
                    $item->time_end_label,
                    $item->duration_label,
                    str_replace('|', '/', $savedReport),
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
}
