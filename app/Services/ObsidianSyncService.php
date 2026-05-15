<?php

namespace App\Services;

use App\Enums\IndexType;
use App\Models\ReportGroupItem;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\YamlFrontMatter\YamlFrontMatter;
use Symfony\Component\Yaml\Yaml;

class ObsidianSyncService
{
    public function syncAll(): void
    {
        foreach (IndexType::cases() as $type) {
            $this->syncIndex($type);
        }

        $this->refreshAssignmentFormatting();
    }

    public function normalizeAllMarkdown(): void
    {
        $this->syncAll();
    }

    public function normalizeIndexMarkdown(IndexType $type): void
    {
        $this->syncIndex($type);
        $this->refreshIndexAssignmentFormatting($type);
    }

    public function syncIndex(IndexType $type): void
    {
        $path = $this->pathFor($type);
        $this->ensureFileExists($type, $path);

        $document = YamlFrontMatter::parseFile($path);
        $records = $this->recordsFor($type);
        $records = $this->syncRecordsToDatabase($type, $records);
        $matter = $this->normalizeMatter($type, $document->matter(), $records);

        $this->writeIfChanged($path, $this->renderDocument($type, $matter, $records));
    }

    public function appendRecord(IndexType $type, array $record): void
    {
        $normalized = [
            ...$this->normalizeRecordForStorage($type, $record),
            'record_uuid' => (string) Str::uuid(),
        ];
        $path = $this->pathFor($type);

        $this->ensureFileExists($type, $path);

        $document = YamlFrontMatter::parseFile($path);
        $records = $this->recordsFor($type);
        $records[] = $normalized;
        $matter = $this->syncMutationEntryOptions($type, $document->matter(), $records, null, $normalized);
        $matter = $this->normalizeMatter($type, $matter, $records);

        $this->writeIfChanged($path, $this->renderDocument($type, $matter, $records));

        $this->syncIndex($type);
        $this->refreshIndexAssignmentFormatting($type);
    }

    public function updateRecord(IndexType $type, int $entryId, array $record): void
    {
        $entry = $this->entryForMutation($type, $entryId);
        $path = $this->pathFor($type);

        $this->ensureFileExists($type, $path);

        $document = YamlFrontMatter::parseFile($path);
        $records = $this->recordsFor($type);
        $recordIndex = $this->recordIndexForEntry($type, $records, $entry);
        $previousRecord = $records[$recordIndex];

        $records[$recordIndex] = [
            ...$this->normalizeRecordForStorage($type, $record),
            'record_uuid' => $entry->obsidian_record_uuid,
        ];

        $matter = $this->syncMutationEntryOptions($type, $document->matter(), $records, $previousRecord, $records[$recordIndex]);
        $matter = $this->normalizeMatter($type, $matter, $records);

        $this->writeIfChanged($path, $this->renderDocument($type, $matter, $records));

        $this->syncIndex($type);
        $this->refreshIndexAssignmentFormatting($type);
    }

    public function deleteRecord(IndexType $type, int $entryId): void
    {
        $entry = $this->entryForMutation($type, $entryId);
        $path = $this->pathFor($type);

        $this->ensureFileExists($type, $path);

        $document = YamlFrontMatter::parseFile($path);
        $records = $this->recordsFor($type);
        $recordIndex = $this->recordIndexForEntry($type, $records, $entry);
        $previousRecord = $records[$recordIndex];

        unset($records[$recordIndex]);
        $records = array_values($records);
        $matter = $this->syncMutationEntryOptions($type, $document->matter(), $records, $previousRecord, null);
        $matter = $this->normalizeMatter($type, $matter, $records);

        $this->writeIfChanged($path, $this->renderDocument($type, $matter, $records));

        $this->syncIndex($type);
        $this->refreshIndexAssignmentFormatting($type);
    }

    public function refreshAssignmentFormatting(): void
    {
        foreach (IndexType::cases() as $type) {
            $this->refreshIndexAssignmentFormatting($type);
        }
    }

    public function refreshIndexAssignmentFormatting(IndexType $type): void
    {
        $path = $this->pathFor($type);

        $this->ensureFileExists($type, $path);

        $document = YamlFrontMatter::parseFile($path);
        $records = $this->recordsFor($type);
        $matter = $this->normalizeMatter($type, $document->matter(), $records);

        $this->writeIfChanged(
            $path,
            $this->renderDocument($type, $matter, $records, $this->lockedRecordKeys($type)),
        );
    }

    public function vaultPath(): string
    {
        return config('obsidian.vault_path');
    }

    public function filePath(IndexType $type): string
    {
        return $this->pathFor($type);
    }

    public function cardMeta(IndexType $type): array
    {
        $path = $this->pathFor($type);

        $this->ensureFileExists($type, $path);

        $document = YamlFrontMatter::parseFile($path);
        $records = $this->recordsFor($type);
        $matter = $this->normalizeMatter($type, $document->matter(), $records);

        return [
            'title' => Arr::get($matter, 'card_title', $type->cardTitle()),
            'profile' => Arr::get($matter, 'profile', $this->defaultProfileMatter()),
            'entry_options' => Arr::get($matter, 'entry_options', $this->defaultEntryOptions($type, $records)),
            'file_name' => basename($path),
            'file_path' => $path,
        ];
    }

    private function recordsFor(IndexType $type): array
    {
        $path = $this->pathFor($type);

        $this->ensureFileExists($type, $path);

        $document = YamlFrontMatter::parseFile($path);
        $frontMatterRecords = $document->matter('records');

        if (is_array($frontMatterRecords)) {
            return $this->prepareRecords(
                $type,
                array_values(array_filter(array_map(
                    fn (mixed $record) => is_array($record) ? $this->normalizeFrontMatterRecord($type, $record) : null,
                    $frontMatterRecords,
                ))),
            );
        }

        $records = match ($type) {
            IndexType::Formation => $this->parseFormationBody($document->body()),
            IndexType::ParishInvolvement => $this->parseParishBody($document->body()),
            IndexType::SocialApostolate => $this->parseSocialBody($document->body()),
        };

        return $this->prepareRecords($type, $records);
    }

    private function prepareRecords(IndexType $type, array $records): array
    {
        $records = array_values(array_filter($records, fn (mixed $record): bool => is_array($record) && $this->isCompleteRecord($type, $record)));

        usort($records, function (array $left, array $right): int {
            return [
                $left['served_on'] ?? '',
                $left['time_start'] ?? '',
                $left['time_end'] ?? '',
                $left['cycle_code'] ?? '',
                $left['module_code'] ?? '',
                $left['title'] ?? '',
                $left['about'] ?? '',
                $left['record_uuid'] ?? '',
            ] <=> [
                $right['served_on'] ?? '',
                $right['time_start'] ?? '',
                $right['time_end'] ?? '',
                $right['cycle_code'] ?? '',
                $right['module_code'] ?? '',
                $right['title'] ?? '',
                $right['about'] ?? '',
                $right['record_uuid'] ?? '',
            ];
        });

        return $records;
    }

    private function isCompleteRecord(IndexType $type, array $record): bool
    {
        if (($record['served_on'] ?? null) === null || ($record['time_start'] ?? null) === null || ($record['time_end'] ?? null) === null) {
            return false;
        }

        return match ($type) {
            IndexType::Formation => filled($record['cycle_code'] ?? null)
                && filled($record['module_code'] ?? null)
                && filled($record['title'] ?? null),
            IndexType::ParishInvolvement => true,
            IndexType::SocialApostolate => filled($record['about'] ?? null),
        };
    }

    private function normalizeFrontMatterRecord(IndexType $type, array $record): ?array
    {
        $normalized = [
            'record_uuid' => $this->normalizeRecordUuid(Arr::get($record, 'record_uuid', Arr::get($record, 'uuid'))),
            'served_on' => $this->normalizeDate(Arr::get($record, 'served_on', Arr::get($record, 'date'))),
            'time_start' => $this->normalizeTime(Arr::get($record, 'time_start')),
            'time_end' => $this->normalizeTime(Arr::get($record, 'time_end')),
        ];

        return match ($type) {
            IndexType::Formation => $this->withFormationFields($normalized, $record),
            IndexType::ParishInvolvement => $normalized,
            IndexType::SocialApostolate => [
                ...$normalized,
                'about' => trim((string) Arr::get($record, 'about', '')),
            ],
        };
    }

    private function withFormationFields(array $normalized, array $record): array
    {
        $cycleCode = strtoupper((string) Arr::get($record, 'cycle_code', Arr::get($record, 'cycle')));
        $moduleCode = strtoupper((string) Arr::get($record, 'module_code', Arr::get($record, 'module')));

        if ($cycleCode === '' || $moduleCode === '') {
            [$cycleCode, $moduleCode] = $this->extractFormationCodes((string) Arr::get($record, 'code', ''));
        }

        return [
            ...$normalized,
            'cycle_code' => $cycleCode,
            'module_code' => $moduleCode,
            'title' => trim((string) Arr::get($record, 'title', '')),
        ];
    }

    private function parseFormationBody(string $body): array
    {
        $tableRecords = $this->parseFormationTables($body);

        if ($tableRecords !== []) {
            return $tableRecords;
        }

        $records = [];

        foreach ($this->bodyLines($body) as $line) {
            if (preg_match('/^(?<date>.+?)\s*-\s*`?(?<code>C\d+M\d+)`?\s*-\s*(?<title>.+?)\s*-\s*(?<time>.+)$/i', $line, $matches)) {
                [$cycleCode, $moduleCode] = $this->extractFormationCodes($matches['code']);
            } elseif (preg_match('/^(?<date>.+?)\s*-\s*(?<cycle>C\d+)\s*-\s*(?<module>M\d+)\s*-\s*(?<title>.+?)\s*-\s*(?<time>.+)$/i', $line, $matches)) {
                $cycleCode = strtoupper($matches['cycle']);
                $moduleCode = strtoupper($matches['module']);
            } else {
                continue;
            }

            [$timeStart, $timeEnd] = $this->parseTimeRange($matches['time']);

            $records[] = [
                'served_on' => $this->normalizeDate($matches['date']),
                'cycle_code' => $cycleCode,
                'module_code' => $moduleCode,
                'title' => trim($matches['title']),
                'time_start' => $timeStart,
                'time_end' => $timeEnd,
            ];
        }

        return $records;
    }

    private function parseParishBody(string $body): array
    {
        $tableRecords = $this->parseParishTables($body);

        if ($tableRecords !== []) {
            return $tableRecords;
        }

        $records = [];

        foreach ($this->bodyLines($body) as $line) {
            [$servedOn, $remainder] = $this->splitDateAndRemainder($line);

            if ($servedOn === null || $remainder === null) {
                continue;
            }

            [$timeStart, $timeEnd] = $this->parseTimeRange($remainder);

            $records[] = [
                'served_on' => $servedOn,
                'time_start' => $timeStart,
                'time_end' => $timeEnd,
            ];
        }

        return $records;
    }

    private function parseSocialBody(string $body): array
    {
        $tableRecords = $this->parseSocialTables($body);

        if ($tableRecords !== []) {
            return $tableRecords;
        }

        $records = [];

        foreach ($this->bodyLines($body) as $line) {
            [$servedOn, $remainder] = $this->splitDateAndRemainder($line);

            if ($servedOn === null || $remainder === null) {
                continue;
            }

            if (! preg_match('/^(?<about>.+?)\s*-\s*(?<time>.+)$/', $remainder, $matches)) {
                continue;
            }

            [$timeStart, $timeEnd] = $this->parseTimeRange($matches['time']);

            $records[] = [
                'served_on' => $servedOn,
                'about' => trim($matches['about']),
                'time_start' => $timeStart,
                'time_end' => $timeEnd,
            ];
        }

        return $records;
    }

    private function parseFormationTables(string $body): array
    {
        $records = [];

        foreach ($this->tableBlocks($body) as $table) {
            if (! $this->tableContainsHeaders($table['headers'], ['date', 'cycleno', 'moduleno', 'title', 'timein', 'timeout'])) {
                continue;
            }

            $index = array_flip($table['headers']);

            foreach ($table['rows'] as $row) {
                $records[] = [
                    'served_on' => $this->normalizeDate($row[$index['date']] ?? null),
                    'cycle_code' => strtoupper(trim((string) ($row[$index['cycleno']] ?? ''))),
                    'module_code' => strtoupper(trim((string) ($row[$index['moduleno']] ?? ''))),
                    'title' => trim((string) ($row[$index['title']] ?? '')),
                    'time_start' => $this->normalizeTime($row[$index['timein']] ?? null),
                    'time_end' => $this->normalizeTime($row[$index['timeout']] ?? null),
                ];
            }
        }

        return $records;
    }

    private function parseParishTables(string $body): array
    {
        $records = [];

        foreach ($this->tableBlocks($body) as $table) {
            if (! $this->tableContainsHeaders($table['headers'], ['date', 'timein', 'timeout'])) {
                continue;
            }

            $index = array_flip($table['headers']);

            foreach ($table['rows'] as $row) {
                $records[] = [
                    'served_on' => $this->normalizeDate($row[$index['date']] ?? null),
                    'time_start' => $this->normalizeTime($row[$index['timein']] ?? null),
                    'time_end' => $this->normalizeTime($row[$index['timeout']] ?? null),
                ];
            }
        }

        return $records;
    }

    private function parseSocialTables(string $body): array
    {
        $records = [];

        foreach ($this->tableBlocks($body) as $table) {
            if (! $this->tableContainsHeaders($table['headers'], ['date', 'timein', 'timeout'])) {
                continue;
            }

            $index = array_flip($table['headers']);
            $aboutKey = array_key_exists('about', $index) ? 'about' : (array_key_exists('activity', $index) ? 'activity' : null);

            if ($aboutKey === null) {
                continue;
            }

            foreach ($table['rows'] as $row) {
                $records[] = [
                    'served_on' => $this->normalizeDate($row[$index['date']] ?? null),
                    'about' => trim((string) ($row[$index[$aboutKey]] ?? '')),
                    'time_start' => $this->normalizeTime($row[$index['timein']] ?? null),
                    'time_end' => $this->normalizeTime($row[$index['timeout']] ?? null),
                ];
            }
        }

        return $records;
    }

    private function tableBlocks(string $body): array
    {
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $body));
        $blocks = [];
        $current = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if (Str::startsWith($trimmed, '|')) {
                $current[] = $trimmed;

                continue;
            }

            if ($current !== []) {
                $parsed = $this->parseTableBlock($current);

                if ($parsed !== null) {
                    $blocks[] = $parsed;
                }

                $current = [];
            }
        }

        if ($current !== []) {
            $parsed = $this->parseTableBlock($current);

            if ($parsed !== null) {
                $blocks[] = $parsed;
            }
        }

        return $blocks;
    }

    private function parseTableBlock(array $lines): ?array
    {
        if (count($lines) < 2) {
            return null;
        }

        $rows = array_map(fn (string $line): array => $this->splitTableRow($line), $lines);

        if (! $this->isSeparatorRow($rows[1])) {
            return null;
        }

        $headers = array_map(fn (string $header): string => $this->normalizeTableHeader($header), $rows[0]);
        $dataRows = [];

        foreach (array_slice($rows, 2) as $row) {
            $normalizedRow = array_map(fn (string $value): string => trim($value), $row);

            if (collect($normalizedRow)->every(fn (string $value): bool => $value === '')) {
                continue;
            }

            $dataRows[] = $normalizedRow;
        }

        return [
            'headers' => $headers,
            'rows' => $dataRows,
        ];
    }

    private function splitTableRow(string $line): array
    {
        $trimmed = trim($line);
        $trimmed = Str::startsWith($trimmed, '|') ? substr($trimmed, 1) : $trimmed;
        $trimmed = Str::endsWith($trimmed, '|') ? substr($trimmed, 0, -1) : $trimmed;

        return array_map(fn (string $cell): string => $this->cleanMarkdownValue($cell), explode('|', $trimmed));
    }

    private function isSeparatorRow(array $row): bool
    {
        if ($row === []) {
            return false;
        }

        foreach ($row as $cell) {
            $candidate = str_replace([' ', ':'], '', trim($cell));

            if ($candidate === '' || preg_match('/^-+$/', $candidate) !== 1) {
                return false;
            }
        }

        return true;
    }

    private function tableContainsHeaders(array $headers, array $required): bool
    {
        foreach ($required as $header) {
            if (! in_array($header, $headers, true)) {
                return false;
            }
        }

        return true;
    }

    private function normalizeTableHeader(string $header): string
    {
        return (string) Str::of($header)
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '')
            ->trim();
    }

    private function bodyLines(string $body): array
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $body);
        $lines = array_map(
            fn (string $line) => $this->cleanMarkdownValue(trim(preg_replace('/^[\-\*\+]\s*/', '', trim($line)))),
            explode("\n", $normalized),
        );

        return array_values(array_filter($lines, function (string $line): bool {
            return $line !== ''
                && ! Str::startsWith($line, '#')
                && ! Str::startsWith($line, '|')
                && preg_match('/^_+$/', $line) !== 1;
        }));
    }

    private function splitDateAndRemainder(string $line): array
    {
        if (! preg_match('/^(?<date>[A-Za-z]+\s+\d{1,2},\s+\d{2,4}|Date TBD)\s*(?:-|:)\s*(?<rest>.+)$/i', $line, $matches)) {
            return [null, null];
        }

        return [
            $this->normalizeDate($matches['date']),
            trim($matches['rest']),
        ];
    }

    private function parseTimeRange(string $value): array
    {
        if (Str::contains(Str::lower($value), 'tbd')) {
            return [null, null];
        }

        if (! preg_match('/(?<start>\d{1,2}(?::\d{2})?\s*[ap]\.?m?\.?)\s*(?:to|-)\s*(?<end>\d{1,2}(?::\d{2})?\s*[ap]\.?m?\.?)$/i', trim($value), $matches)) {
            return [null, null];
        }

        return [
            $this->normalizeTime($matches['start']),
            $this->normalizeTime($matches['end']),
        ];
    }

    private function normalizeRecordForStorage(IndexType $type, array $record): array
    {
        $normalized = [
            'record_uuid' => $this->normalizeRecordUuid(Arr::get($record, 'record_uuid')),
            'served_on' => $this->normalizeDate((string) Arr::get($record, 'served_on')),
            'time_start' => $this->normalizeTime((string) Arr::get($record, 'time_start')),
            'time_end' => $this->normalizeTime((string) Arr::get($record, 'time_end')),
        ];

        return match ($type) {
            IndexType::Formation => [
                ...$normalized,
                'cycle_code' => strtoupper((string) Arr::get($record, 'cycle_code')),
                'module_code' => strtoupper((string) Arr::get($record, 'module_code')),
                'title' => trim((string) Arr::get($record, 'title')),
            ],
            IndexType::ParishInvolvement => $normalized,
            IndexType::SocialApostolate => [
                ...$normalized,
                'about' => trim((string) Arr::get($record, 'about')),
            ],
        };
    }

    private function normalizeDate(?string $value): ?string
    {
        $value = $this->cleanMarkdownValue($value);

        if (blank($value) || Str::contains(Str::lower($value), 'tbd')) {
            return null;
        }

        $candidate = trim((string) Str::of($value)->replaceMatches('/\s+/', ' ')->title());

        if (preg_match('/,\s*(?<year>\d{2})$/', $candidate, $matches) === 1) {
            $candidate = preg_replace('/,\s*\d{2}$/', ', 20'.$matches['year'], $candidate) ?? $candidate;
        }

        if (preg_match('/,\s*00(?<year>\d{2})$/', $candidate, $matches) === 1) {
            $candidate = preg_replace('/,\s*00\d{2}$/', ', 20'.$matches['year'], $candidate) ?? $candidate;
        }

        foreach (['Y-m-d', 'F j, Y', 'M j, Y'] as $format) {
            try {
                return CarbonImmutable::createFromFormat($format, $candidate, config('app.timezone'))->toDateString();
            } catch (\Throwable) {
                continue;
            }
        }

        return CarbonImmutable::parse($candidate, config('app.timezone'))->toDateString();
    }

    private function normalizeTime(?string $value): ?string
    {
        $value = $this->cleanMarkdownValue($value);

        if (blank($value) || Str::contains(Str::lower((string) $value), 'tbd')) {
            return null;
        }

        $trimmed = trim((string) $value);

        if (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $trimmed) === 1) {
            return strlen($trimmed) === 5 ? $trimmed.':00' : $trimmed;
        }

        $candidate = strtoupper(str_replace(['.', ' '], '', $trimmed));

        if (preg_match('/^(?<hour>\d{1,2})(?::(?<minute>\d{2}))?(?<meridiem>AM|PM)$/', $candidate, $matches) !== 1) {
            return null;
        }

        $minute = $matches['minute'] ?: '00';

        return CarbonImmutable::createFromFormat(
            'g:i A',
            $matches['hour'].':'.$minute.' '.$matches['meridiem'],
            config('app.timezone'),
        )->format('H:i:s');
    }

    private function cleanMarkdownValue(?string $value): string
    {
        $cleaned = str_replace(['~~', '`'], '', trim((string) $value));

        return preg_replace('/\s+/', ' ', $cleaned) ?? $cleaned;
    }

    private function extractFormationCodes(string $code): array
    {
        if (preg_match('/^(?<cycle>C\d+)(?<module>M\d+)$/i', strtoupper(trim($code)), $matches) !== 1) {
            return ['', ''];
        }

        return [strtoupper($matches['cycle']), strtoupper($matches['module'])];
    }

    private function renderDocument(IndexType $type, array $matter, array $records, array $markedSignatures = []): string
    {
        $preparedRecords = $this->prepareRecords($type, $records);
        $yaml = Yaml::dump($this->normalizeMatter($type, $matter, $preparedRecords), 6, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);

        return "---\n{$yaml}---\n".$this->renderBody($type, $preparedRecords, $markedSignatures);
    }

    private function renderBody(IndexType $type, array $records, array $markedSignatures = []): string
    {
        $lines = [
            '# '.$type->cardTitle(),
            '',
            '> Edit the note properties above for the card header details used by the web app.',
            '> Keep any reference lists you want in `entry_options` above, but native Obsidian properties do not attach dropdowns to markdown table cells.',
            '> Add or update rows in the single ledger table below. Keep dates like `September 21, 2025` and times like `3:00 PM`.',
        ];

        if ($type === IndexType::Formation) {
            $lines[] = '> Keep cycle and module values in `C#` and `M#` format.';
        }

        if ($type === IndexType::SocialApostolate) {
            $lines[] = '> Use the Activity / About column to describe what you served.';
        }

        $lines[] = '';
        $lines[] = '## Service Records';
        $lines[] = '';

        if ($records === []) {
            $lines = [...$lines, ...$this->renderEmptyTable($type)];

            return implode("\n", $lines)."\n";
        }

        $lines = [...$lines, ...$this->renderTable($type, $records, $markedSignatures)];

        return rtrim(implode("\n", $lines))."\n";
    }

    private function renderEmptyTable(IndexType $type): array
    {
        return $this->renderTable($type, []);
    }

    private function renderTable(IndexType $type, array $records, array $markedSignatures = []): array
    {
        $headers = $this->tableHeaders($type);
        $divider = array_map(fn (): string => '---', $headers);
        $lines = [
            '| '.implode(' | ', $headers).' |',
            '| '.implode(' | ', $divider).' |',
        ];

        foreach ($records as $record) {
            $isMarked = array_key_exists($this->recordKey($type, $record), $markedSignatures);

            $lines[] = '| '.implode(' | ', $this->tableRow($type, $record, $isMarked)).' |';
        }

        return $lines;
    }

    private function tableHeaders(IndexType $type): array
    {
        return match ($type) {
            IndexType::Formation => ['Date', 'Cycle No.', 'Module No.', 'Title', 'Time In', 'Time Out'],
            IndexType::ParishInvolvement => ['Date', 'Time In', 'Time Out'],
            IndexType::SocialApostolate => ['Date', 'Activity', 'Time In', 'Time Out'],
        };
    }

    private function tableRow(IndexType $type, array $record, bool $isMarked = false): array
    {
        $row = match ($type) {
            IndexType::Formation => [
                $this->formatDateForMarkdown($record['served_on'] ?? null),
                $record['cycle_code'] ?? '',
                $record['module_code'] ?? '',
                str_replace('|', '/', $record['title'] ?? ''),
                $this->formatTimeForMarkdown($record['time_start'] ?? ''),
                $this->formatTimeForMarkdown($record['time_end'] ?? ''),
            ],
            IndexType::ParishInvolvement => [
                $this->formatDateForMarkdown($record['served_on'] ?? null),
                $this->formatTimeForMarkdown($record['time_start'] ?? ''),
                $this->formatTimeForMarkdown($record['time_end'] ?? ''),
            ],
            IndexType::SocialApostolate => [
                $this->formatDateForMarkdown($record['served_on'] ?? null),
                str_replace('|', '/', $record['about'] ?? ''),
                $this->formatTimeForMarkdown($record['time_start'] ?? ''),
                $this->formatTimeForMarkdown($record['time_end'] ?? ''),
            ],
        };

        if (! $isMarked) {
            return $row;
        }

        return array_map(fn (string $value): string => $this->strikeValue($value), $row);
    }

    private function formatDateForMarkdown(?string $date): string
    {
        if ($date === null) {
            return '';
        }

        return CarbonImmutable::parse($date, config('app.timezone'))->format('F j, Y');
    }

    private function formatTimeForMarkdown(?string $time): string
    {
        if ($time === null || $time === '') {
            return '';
        }

        return CarbonImmutable::createFromFormat('H:i:s', $time, config('app.timezone'))->format('g:i A');
    }

    private function strikeValue(string $value): string
    {
        return $value === '' ? '' : '~~'.$value.'~~';
    }

    private function normalizeMatter(IndexType $type, array $matter, array $records = []): array
    {
        $profile = array_merge($this->defaultProfileMatter(), Arr::get($matter, 'profile', []));
        $entryOptions = $this->normalizeEntryOptions($type, Arr::get($matter, 'entry_options', []), $records, $profile);

        return [
            ...Arr::except($matter, ['records', 'card_title', 'index', 'profile', 'entry_options']),
            'index' => $type->value,
            'card_title' => Arr::get($matter, 'card_title', $type->cardTitle()),
            'profile' => $profile,
            'entry_options' => $entryOptions,
            'records' => $this->recordsForFrontMatter($type, $records),
        ];
    }

    private function defaultProfileMatter(): array
    {
        return [
            'name' => '',
            'school_year' => '2025-2026',
            'year_level' => '',
            'parish' => '',
            'diocese_institution' => '',
            'school' => '',
            'course' => '',
        ];
    }

    private function defaultEntryOptions(IndexType $type, array $records = [], array $profile = []): array
    {
        $academicYear = trim((string) Arr::get($profile, 'school_year', '2025-2026'));
        $defaults = [
            'academic_years' => $this->normalizeOptionList([$academicYear !== '' ? $academicYear : '2025-2026']),
        ];

        return match ($type) {
            IndexType::Formation => [
                ...$defaults,
                'cycle_codes' => $this->normalizeOptionList([
                    'C1',
                    'C2',
                    'C3',
                    ...array_column($records, 'cycle_code'),
                ]),
                'module_codes' => $this->normalizeOptionList([
                    'M1',
                    'M2',
                    'M3',
                    ...array_column($records, 'module_code'),
                ]),
                'titles' => $this->normalizeOptionList([
                    'Formation Session',
                    ...array_column($records, 'title'),
                ]),
            ],
            IndexType::ParishInvolvement => $defaults,
            IndexType::SocialApostolate => [
                ...$defaults,
                'activities' => $this->normalizeOptionList([
                    'Service Activity',
                    ...array_column($records, 'about'),
                ]),
            ],
        };
    }

    private function normalizeEntryOptions(IndexType $type, array $entryOptions, array $records, array $profile): array
    {
        $defaults = $this->defaultEntryOptions($type, $records, $profile);

        return match ($type) {
            IndexType::Formation => [
                'academic_years' => $this->mergeOptionLists($defaults['academic_years'], Arr::get($entryOptions, 'academic_years', [])),
                'cycle_codes' => $this->mergeOptionLists($defaults['cycle_codes'], Arr::get($entryOptions, 'cycle_codes', [])),
                'module_codes' => $this->mergeOptionLists($defaults['module_codes'], Arr::get($entryOptions, 'module_codes', [])),
                'titles' => $this->mergeOptionLists($defaults['titles'], Arr::get($entryOptions, 'titles', [])),
            ],
            IndexType::ParishInvolvement => [
                'academic_years' => $this->mergeOptionLists($defaults['academic_years'], Arr::get($entryOptions, 'academic_years', [])),
            ],
            IndexType::SocialApostolate => [
                'academic_years' => $this->mergeOptionLists($defaults['academic_years'], Arr::get($entryOptions, 'academic_years', [])),
                'activities' => $this->mergeOptionLists($defaults['activities'], Arr::get($entryOptions, 'activities', [])),
            ],
        };
    }

    private function mergeOptionLists(array $defaults, mixed $provided): array
    {
        return $this->normalizeOptionList([
            ...$defaults,
            ...$this->normalizeOptionInput($provided),
        ]);
    }

    private function normalizeOptionInput(mixed $options): array
    {
        if (! is_array($options)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (mixed $value): string => trim((string) $value),
            $options,
        )));
    }

    private function normalizeOptionList(array $options): array
    {
        return array_values(array_unique(array_filter(array_map(
            fn (mixed $value): string => trim((string) $value),
            $options,
        ))));
    }

    private function syncMutationEntryOptions(
        IndexType $type,
        array $matter,
        array $records,
        ?array $previousRecord,
        ?array $nextRecord,
    ): array {
        $entryOptions = Arr::get($matter, 'entry_options', []);

        foreach ($this->dynamicEntryOptionPairs($type, $previousRecord) as [$optionKey, $optionValue]) {
            if ($optionValue === '' || $this->recordsContainOptionValue($type, $optionKey, $optionValue, $records)) {
                continue;
            }

            $entryOptions[$optionKey] = array_values(array_filter(
                $this->normalizeOptionInput(Arr::get($entryOptions, $optionKey, [])),
                fn (string $candidate): bool => ! $this->optionValuesMatch($candidate, $optionValue),
            ));
        }

        foreach ($this->dynamicEntryOptionPairs($type, $nextRecord) as [$optionKey, $optionValue]) {
            if ($optionValue === '') {
                continue;
            }

            $entryOptions[$optionKey] = $this->normalizeOptionList([
                ...$this->normalizeOptionInput(Arr::get($entryOptions, $optionKey, [])),
                $optionValue,
            ]);
        }

        $matter['entry_options'] = $entryOptions;

        return $matter;
    }

    private function dynamicEntryOptionPairs(IndexType $type, ?array $record): array
    {
        if ($record === null) {
            return [];
        }

        return match ($type) {
            IndexType::Formation => [
                ['cycle_codes', trim((string) ($record['cycle_code'] ?? ''))],
                ['module_codes', trim((string) ($record['module_code'] ?? ''))],
                ['titles', trim((string) ($record['title'] ?? ''))],
            ],
            IndexType::SocialApostolate => [
                ['activities', trim((string) ($record['about'] ?? ''))],
            ],
            default => [],
        };
    }

    private function recordsContainOptionValue(IndexType $type, string $optionKey, string $optionValue, array $records): bool
    {
        foreach ($records as $record) {
            foreach ($this->dynamicEntryOptionPairs($type, $record) as [$candidateKey, $candidateValue]) {
                if ($candidateKey === $optionKey && $this->optionValuesMatch($candidateValue, $optionValue)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function optionValuesMatch(string $left, string $right): bool
    {
        return Str::lower(trim($left)) === Str::lower(trim($right));
    }

    private function pathFor(IndexType $type): string
    {
        $fileName = config('obsidian.files.'.$type->value, $type->defaultFileName());

        return rtrim(config('obsidian.vault_path'), '\\/').DIRECTORY_SEPARATOR.$fileName;
    }

    private function ensureFileExists(IndexType $type, string $path): void
    {
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        if (! file_exists($path)) {
            file_put_contents($path, $this->defaultTemplate($type));
        }
    }

    private function writeIfChanged(string $path, string $contents): void
    {
        if (file_exists($path) && file_get_contents($path) === $contents) {
            return;
        }

        file_put_contents($path, $contents);
    }

    private function defaultTemplate(IndexType $type): string
    {
        return $this->renderDocument($type, [], []);
    }

    private function syncRecordsToDatabase(IndexType $type, array $records): array
    {
        $modelClass = $type->modelClass();

        return DB::transaction(function () use ($modelClass, $records, $type): array {
            $timestamp = now();
            $existingEntries = $modelClass::query()
                ->orderBy('id')
                ->get();
            $unmatchedEntries = $existingEntries->keyBy('id')->all();
            $entriesByUuid = $existingEntries
                ->filter(fn ($entry): bool => filled($entry->obsidian_record_uuid))
                ->keyBy('obsidian_record_uuid')
                ->all();
            $entriesBySignature = [];

            foreach ($existingEntries as $entry) {
                $entriesBySignature[$this->modelSignature($type, $entry)][] = $entry;
            }

            $resolvedRecords = [];

            foreach ($records as $index => $record) {
                $normalizedRecord = $this->normalizeRecordForStorage($type, $record);
                $recordUuid = $normalizedRecord['record_uuid'] ?: (string) Str::uuid();
                $normalizedRecord['record_uuid'] = $recordUuid;

                $entry = $entriesByUuid[$recordUuid] ?? null;

                if ($entry !== null) {
                    unset($entriesByUuid[$recordUuid], $unmatchedEntries[$entry->id]);
                    $this->removeEntryFromSignatureGroup($entriesBySignature, $type, $entry);
                } else {
                    $entry = $this->pullMatchingEntryBySignature($entriesBySignature, $type, $normalizedRecord);

                    if ($entry !== null) {
                        unset($unmatchedEntries[$entry->id]);

                        if (filled($entry->obsidian_record_uuid)) {
                            unset($entriesByUuid[$entry->obsidian_record_uuid]);
                            $normalizedRecord['record_uuid'] = $entry->obsidian_record_uuid;
                        }
                    }
                }

                $payload = [
                    ...Arr::except($normalizedRecord, ['record_uuid']),
                    'source_order' => $index + 1,
                    'obsidian_record_uuid' => $normalizedRecord['record_uuid'],
                    'updated_at' => $timestamp,
                ];

                if ($entry !== null) {
                    $entry->forceFill($payload)->save();
                } else {
                    $entry = $modelClass::query()->create([
                        ...$payload,
                        'created_at' => $timestamp,
                    ]);
                }

                $resolvedRecords[] = [
                    ...$normalizedRecord,
                    'record_uuid' => $entry->obsidian_record_uuid,
                ];
            }

            if ($unmatchedEntries !== []) {
                $modelClass::query()
                    ->whereIn('id', array_keys($unmatchedEntries))
                    ->delete();
            }

            return $resolvedRecords;
        });
    }

    private function entryForMutation(IndexType $type, int $entryId)
    {
        $modelClass = $type->modelClass();
        $entry = $modelClass::query()->findOrFail($entryId);

        if (blank($entry->obsidian_record_uuid)) {
            $this->syncIndex($type);
            $entry = $modelClass::query()->findOrFail($entryId);
        }

        return $entry;
    }

    private function recordIndexForEntry(IndexType $type, array $records, $entry): int
    {
        if (filled($entry->obsidian_record_uuid)) {
            foreach ($records as $index => $record) {
                if (($record['record_uuid'] ?? null) === $entry->obsidian_record_uuid) {
                    return $index;
                }
            }
        }

        $signature = $this->modelSignature($type, $entry);

        foreach ($records as $index => $record) {
            if ($this->recordSignature($type, $record) === $signature) {
                return $index;
            }
        }

        abort(404);
    }

    private function recordSignature(IndexType $type, array $record): string
    {
        return match ($type) {
            IndexType::Formation => implode('|', [
                $record['served_on'] ?? '',
                $record['time_start'] ?? '',
                $record['time_end'] ?? '',
                $record['cycle_code'] ?? '',
                $record['module_code'] ?? '',
                $record['title'] ?? '',
            ]),
            IndexType::ParishInvolvement => implode('|', [
                $record['served_on'] ?? '',
                $record['time_start'] ?? '',
                $record['time_end'] ?? '',
            ]),
            IndexType::SocialApostolate => implode('|', [
                $record['served_on'] ?? '',
                $record['time_start'] ?? '',
                $record['time_end'] ?? '',
                $record['about'] ?? '',
            ]),
        };
    }

    private function recordKey(IndexType $type, array $record): string
    {
        return $record['record_uuid'] ?? $this->recordSignature($type, $record);
    }

    private function modelSignature(IndexType $type, $entry): string
    {
        return match ($type) {
            IndexType::Formation => implode('|', [
                $entry->served_on?->toDateString() ?? $entry->getRawOriginal('served_on') ?? '',
                $entry->getRawOriginal('time_start') ?? '',
                $entry->getRawOriginal('time_end') ?? '',
                $entry->cycle_code ?? '',
                $entry->module_code ?? '',
                $entry->title ?? '',
            ]),
            IndexType::ParishInvolvement => implode('|', [
                $entry->served_on?->toDateString() ?? $entry->getRawOriginal('served_on') ?? '',
                $entry->getRawOriginal('time_start') ?? '',
                $entry->getRawOriginal('time_end') ?? '',
            ]),
            IndexType::SocialApostolate => implode('|', [
                $entry->served_on?->toDateString() ?? $entry->getRawOriginal('served_on') ?? '',
                $entry->getRawOriginal('time_start') ?? '',
                $entry->getRawOriginal('time_end') ?? '',
                $entry->about ?? '',
            ]),
        };
    }

    private function modelKey(IndexType $type, $entry): string
    {
        return $entry->obsidian_record_uuid ?: $this->modelSignature($type, $entry);
    }

    private function lockedRecordKeys(IndexType $type): array
    {
        $sourceEntryIds = ReportGroupItem::query()
            ->where('index_type', $type->value)
            ->whereNotNull('source_entry_id')
            ->pluck('source_entry_id');

        if ($sourceEntryIds->isEmpty()) {
            return [];
        }

        $modelClass = $type->modelClass();

        return $modelClass::query()
            ->whereIn('id', $sourceEntryIds)
            ->get()
            ->mapWithKeys(fn ($entry): array => [$this->modelKey($type, $entry) => true])
            ->all();
    }

    private function recordsForFrontMatter(IndexType $type, array $records): array
    {
        return array_values(array_map(function (array $record) use ($type): array {
            $normalized = [
                'record_uuid' => $record['record_uuid'] ?? (string) Str::uuid(),
                'served_on' => $record['served_on'] ?? null,
                'time_start' => $record['time_start'] ?? null,
                'time_end' => $record['time_end'] ?? null,
            ];

            return match ($type) {
                IndexType::Formation => [
                    ...$normalized,
                    'cycle_code' => $record['cycle_code'] ?? null,
                    'module_code' => $record['module_code'] ?? null,
                    'title' => $record['title'] ?? null,
                ],
                IndexType::ParishInvolvement => $normalized,
                IndexType::SocialApostolate => [
                    ...$normalized,
                    'about' => $record['about'] ?? null,
                ],
            };
        }, $records));
    }

    private function pullMatchingEntryBySignature(array &$entriesBySignature, IndexType $type, array $record)
    {
        $signature = $this->recordSignature($type, $record);

        if (! array_key_exists($signature, $entriesBySignature) || $entriesBySignature[$signature] === []) {
            return null;
        }

        return array_shift($entriesBySignature[$signature]);
    }

    private function removeEntryFromSignatureGroup(array &$entriesBySignature, IndexType $type, $entry): void
    {
        $signature = $this->modelSignature($type, $entry);

        if (! array_key_exists($signature, $entriesBySignature)) {
            return;
        }

        $entriesBySignature[$signature] = array_values(array_filter(
            $entriesBySignature[$signature],
            fn ($candidate): bool => $candidate->id !== $entry->id,
        ));
    }

    private function normalizeRecordUuid(mixed $value): ?string
    {
        $uuid = trim((string) $value);

        return $uuid === '' ? null : Str::lower($uuid);
    }
}
