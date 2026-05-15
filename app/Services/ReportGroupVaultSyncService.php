<?php

namespace App\Services;

use App\Enums\IndexType;
use App\Models\ReportGroup;
use App\Models\ReportGroupItem;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Spatie\YamlFrontMatter\YamlFrontMatter;
use Symfony\Component\Yaml\Yaml;

class ReportGroupVaultSyncService
{
    public function syncReportGroup(ReportGroup $reportGroup): void
    {
        $reportGroup->loadMissing('items');

        $directory = $this->reportDirectory($reportGroup);
        $recordsDirectory = $this->recordsDirectory($reportGroup);

        File::ensureDirectoryExists($recordsDirectory);

        $expectedPaths = [];

        foreach ($reportGroup->items as $item) {
            $item = $this->ensureItemSyncMetadata($reportGroup, $item);
            $path = $item->obsidian_note_path;
            $contents = $this->renderRecordNote($reportGroup, $item);
            $hash = hash('sha256', $contents);

            File::put($path, $contents);

            $expectedPaths[$this->normalizePath($path)] = true;

            ReportGroupItem::withoutTimestamps(function () use ($item, $path, $hash): void {
                $item->forceFill([
                    'obsidian_note_path' => $path,
                    'obsidian_note_hash' => $hash,
                    'obsidian_last_synced_at' => now(),
                ])->save();
            });
        }

        foreach ($this->recordFilesForDirectory($directory) as $path) {
            if (! array_key_exists($this->normalizePath($path), $expectedPaths)) {
                File::delete($path);
            }
        }

        $indexPath = $this->indexNotePath($reportGroup);

        File::put($indexPath, $this->renderIndexNote($reportGroup->fresh('items') ?? $reportGroup));

        ReportGroup::withoutTimestamps(function () use ($reportGroup, $directory, $indexPath): void {
            $reportGroup->forceFill([
                'obsidian_directory' => $directory,
                'obsidian_index_note_path' => $indexPath,
                'obsidian_last_synced_at' => now(),
            ])->save();
        });
    }

    public function deleteReportGroupFiles(ReportGroup $reportGroup): void
    {
        File::deleteDirectory($this->reportDirectory($reportGroup));
    }

    public function pullFromVault(): int
    {
        $changedReportIds = [];

        ReportGroup::query()
            ->with('items')
            ->get()
            ->each(function (ReportGroup $reportGroup) use (&$changedReportIds): void {
                $directory = $this->reportDirectory($reportGroup);

                if (! File::isDirectory($directory)) {
                    return;
                }

                $itemsById = $reportGroup->items->keyBy('id');
                $itemsByUuid = $reportGroup->items
                    ->filter(fn (ReportGroupItem $item): bool => filled($item->obsidian_record_uuid))
                    ->keyBy('obsidian_record_uuid');
                $itemsByPath = $reportGroup->items
                    ->filter(fn (ReportGroupItem $item): bool => filled($item->obsidian_note_path))
                    ->keyBy(fn (ReportGroupItem $item): string => $this->normalizePath($item->obsidian_note_path));

                $seenPaths = [];

                foreach ($this->recordFilesForDirectory($directory) as $path) {
                    $normalizedPath = $this->normalizePath($path);
                    $seenPaths[$normalizedPath] = true;

                    $contents = File::get($path);
                    $hash = hash('sha256', $contents);
                    $matter = YamlFrontMatter::parse($contents)->matter();
                    $item = $itemsByPath->get($normalizedPath)
                        ?? $itemsById->get((int) Arr::get($matter, 'report_group_item_id'))
                        ?? $itemsByUuid->get((string) Arr::get($matter, 'record_uuid'));

                    if ($item !== null && $item->obsidian_note_hash === $hash) {
                        continue;
                    }

                    $payload = $this->payloadFromMatter($matter, $item);

                    if ($payload === null) {
                        continue;
                    }

                    if ($item === null) {
                        $item = $reportGroup->items()->create([
                            ...$payload,
                            'obsidian_record_uuid' => (string) Str::uuid(),
                            'obsidian_note_path' => $path,
                            'obsidian_note_hash' => $hash,
                            'obsidian_last_synced_at' => now(),
                        ]);
                    } else {
                        $updatedType = $payload['index_type'];
                        $detachedSourceId = $item->index_type !== $updatedType ? null : $item->source_entry_id;

                        $item->forceFill([
                            ...$payload,
                            'source_entry_id' => $detachedSourceId,
                            'obsidian_note_path' => $path,
                            'obsidian_note_hash' => $hash,
                            'obsidian_last_synced_at' => now(),
                        ])->save();
                    }

                    $changedReportIds[$reportGroup->id] = true;
                }

                foreach ($reportGroup->items as $item) {
                    if (! filled($item->obsidian_note_path)) {
                        continue;
                    }

                    $normalizedPath = $this->normalizePath($item->obsidian_note_path);

                    if (! Str::startsWith($normalizedPath, $this->normalizePath($directory))) {
                        continue;
                    }

                    if (array_key_exists($normalizedPath, $seenPaths)) {
                        continue;
                    }

                    $item->delete();
                    $changedReportIds[$reportGroup->id] = true;
                }
            });

        foreach (array_keys($changedReportIds) as $reportGroupId) {
            $reportGroup = ReportGroup::query()->with('items')->find($reportGroupId);

            if ($reportGroup !== null) {
                $this->syncReportGroup($reportGroup);
            }
        }

        return count($changedReportIds);
    }

    public function reportDirectory(ReportGroup $reportGroup): string
    {
        return rtrim(config('obsidian.vault_path'), '\\/')
            .DIRECTORY_SEPARATOR
            .trim(config('obsidian.report_notes_directory', 'REPORTS'), '\\/')
            .DIRECTORY_SEPARATOR
            .$reportGroup->tag;
    }

    public function indexNotePath(ReportGroup $reportGroup): string
    {
        return $this->reportDirectory($reportGroup)
            .DIRECTORY_SEPARATOR
            .config('obsidian.report_index_file', 'index.md');
    }

    public function recordsDirectory(ReportGroup $reportGroup): string
    {
        return $this->reportDirectory($reportGroup).DIRECTORY_SEPARATOR.'records';
    }

    private function ensureItemSyncMetadata(ReportGroup $reportGroup, ReportGroupItem $item): ReportGroupItem
    {
        $uuid = $item->obsidian_record_uuid ?: (string) Str::uuid();
        $path = $item->obsidian_note_path ?: $this->recordsDirectory($reportGroup)
            .DIRECTORY_SEPARATOR
            .$this->defaultRecordFileName($item->index_type, $uuid);

        if ($uuid !== $item->obsidian_record_uuid || $path !== $item->obsidian_note_path) {
            ReportGroupItem::withoutTimestamps(function () use ($item, $uuid, $path): void {
                $item->forceFill([
                    'obsidian_record_uuid' => $uuid,
                    'obsidian_note_path' => $path,
                ])->save();
            });
        }

        return $item->fresh() ?? $item;
    }

    private function defaultRecordFileName(string $indexType, string $uuid): string
    {
        return str_replace('_', '-', $indexType).'-'.Str::lower($uuid).'.md';
    }

    private function recordFilesForDirectory(string $directory): array
    {
        if (! File::isDirectory($directory)) {
            return [];
        }

        return collect(File::allFiles($directory))
            ->filter(function (\SplFileInfo $file): bool {
                return Str::lower($file->getExtension()) === 'md'
                    && Str::lower($file->getFilename()) !== Str::lower(config('obsidian.report_index_file', 'index.md'));
            })
            ->map(fn (\SplFileInfo $file): string => $file->getPathname())
            ->values()
            ->all();
    }

    private function renderRecordNote(ReportGroup $reportGroup, ReportGroupItem $item): string
    {
        $matter = Yaml::dump([
            'report_group_id' => $reportGroup->id,
            'report_group_tag' => $reportGroup->tag,
            'report_group_title' => $reportGroup->title,
            'report_group_item_id' => $item->id,
            'record_uuid' => $item->obsidian_record_uuid,
            'index_type' => $item->index_type,
            'source_entry_id' => $item->source_entry_id,
            'served_on' => $item->served_on?->toDateString(),
            'time_start' => $item->getRawOriginal('time_start'),
            'time_end' => $item->getRawOriginal('time_end'),
            'cycle_code' => $item->cycle_code,
            'module_code' => $item->module_code,
            'title' => $item->title,
            'about' => $item->about,
            'source_order' => $item->source_order,
            'created_at' => $item->created_at?->toIso8601String(),
            'updated_at' => $item->updated_at?->toIso8601String(),
        ], 5, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);

        $type = IndexType::from($item->index_type);
        $lines = [
            "---\n{$matter}---",
            '# '.$type->label().' record',
            '',
            '> Edit the YAML frontmatter above inside Obsidian to update this report record.',
            '> Keep `report_group_tag`, `record_uuid`, and `index_type` intact so the sync process can map the note safely.',
            '',
            '## Snapshot',
            '',
            '- Report: '.($reportGroup->compact_label),
            '- Index: '.$type->label(),
            '- Date: '.($item->served_on_label ?? 'Not set'),
            '- Time: '.trim(($item->time_start_label ?? '').' - '.($item->time_end_label ?? '')),
        ];

        if ($type === IndexType::Formation) {
            $lines[] = '- Cycle / Module: '.trim(($item->cycle_code ?? '').' / '.($item->module_code ?? ''), ' /');
            $lines[] = '- Title: '.($item->title ?: 'Not set');
        }

        if ($type === IndexType::SocialApostolate) {
            $lines[] = '- Activity: '.($item->about ?: 'Not set');
        }

        return implode("\n", $lines)."\n";
    }

    private function renderIndexNote(ReportGroup $reportGroup): string
    {
        $reportGroup->loadMissing('items');

        $matter = Yaml::dump([
            'report_group_id' => $reportGroup->id,
            'report_group_tag' => $reportGroup->tag,
            'report_group_title' => $reportGroup->title,
            'record_count' => $reportGroup->items->count(),
            'generated_at' => now()->toIso8601String(),
        ], 4, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);

        $lines = [
            "---\n{$matter}---",
            '# '.($reportGroup->title ?: $reportGroup->tag),
            '',
            '> This summary note is generated by the web app.',
            '> Individual record notes live in the `records/` folder and are the bidirectional sync source for this saved report.',
            '',
        ];

        foreach (IndexType::cases() as $type) {
            $items = $reportGroup->itemsFor($type->value);

            $lines[] = '## '.$type->label();
            $lines[] = '';

            if ($items->isEmpty()) {
                $lines[] = '- No records yet.';
                $lines[] = '';

                continue;
            }

            foreach ($items as $item) {
                $relativePath = 'records/'.basename($item->obsidian_note_path ?: $this->defaultRecordFileName($item->index_type, $item->obsidian_record_uuid ?: (string) Str::uuid()));
                $summary = $type === IndexType::Formation
                    ? trim(($item->cycle_code ?? '').' / '.($item->module_code ?? '').' - '.($item->title ?? ''), ' -/')
                    : ($type === IndexType::SocialApostolate ? ($item->about ?? 'Activity') : 'Parish Involvement');

                $lines[] = '- [['.$relativePath.']] - '.$summary.' ('.$item->duration_label.')';
            }

            $lines[] = '';
        }

        return implode("\n", $lines)."\n";
    }

    private function payloadFromMatter(array $matter, ?ReportGroupItem $existingItem): ?array
    {
        $typeValue = (string) Arr::get($matter, 'index_type', '');

        if ($typeValue === '') {
            return null;
        }

        try {
            $type = IndexType::from($typeValue);
        } catch (\ValueError) {
            return null;
        }

        $servedOn = $this->normalizeDate((string) Arr::get($matter, 'served_on', ''));
        $timeStart = $this->normalizeTime((string) Arr::get($matter, 'time_start', ''));
        $timeEnd = $this->normalizeTime((string) Arr::get($matter, 'time_end', ''));

        if ($servedOn === null || $timeStart === null || $timeEnd === null) {
            return null;
        }

        $payload = [
            'index_type' => $type->value,
            'served_on' => $servedOn,
            'time_start' => $timeStart,
            'time_end' => $timeEnd,
            'source_order' => max(1, (int) Arr::get($matter, 'source_order', $existingItem?->source_order ?? 1)),
        ];

        if ($type === IndexType::Formation) {
            $cycleCode = strtoupper(trim((string) Arr::get($matter, 'cycle_code', '')));
            $moduleCode = strtoupper(trim((string) Arr::get($matter, 'module_code', '')));
            $title = trim((string) Arr::get($matter, 'title', ''));

            if ($cycleCode === '' || $moduleCode === '' || $title === '') {
                return null;
            }

            return [
                ...$payload,
                'cycle_code' => $cycleCode,
                'module_code' => $moduleCode,
                'title' => $title,
                'about' => null,
            ];
        }

        if ($type === IndexType::SocialApostolate) {
            $about = trim((string) Arr::get($matter, 'about', ''));

            if ($about === '') {
                return null;
            }

            return [
                ...$payload,
                'cycle_code' => null,
                'module_code' => null,
                'title' => null,
                'about' => $about,
            ];
        }

        return [
            ...$payload,
            'cycle_code' => null,
            'module_code' => null,
            'title' => null,
            'about' => null,
        ];
    }

    private function normalizeDate(string $value): ?string
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        return CarbonImmutable::parse($trimmed, config('app.timezone'))->toDateString();
    }

    private function normalizeTime(string $value): ?string
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        foreach (['H:i:s', 'H:i', 'g:i A'] as $format) {
            try {
                $time = CarbonImmutable::createFromFormat($format, Str::upper($trimmed), config('app.timezone'));

                return $time->format('H:i:s');
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    private function normalizePath(string $path): string
    {
        $candidate = realpath($path) ?: $path;

        return preg_replace('#/+#', '/', str_replace('\\', '/', $candidate)) ?: str_replace('\\', '/', $candidate);
    }
}
