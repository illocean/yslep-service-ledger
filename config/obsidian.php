<?php

return [
    'vault_path' => env('OBSIDIAN_VAULT_PATH', 'C:\\Users\\andador kim phillip\\Desktop\\NOTES-OBSIDIAN\\!YSLEP'),

    'files' => [
        'formation' => 'FORMATION.md',
        'parish_involvement' => 'PARISH INVOLVEMENT.md',
        'social_apostolate' => 'SOCIAL APOSTOLATE.md',
    ],

    'report_groups_file' => env('OBSIDIAN_REPORT_GROUPS_FILE', 'REPORT GROUPS.md'),

    'report_notes_directory' => env('OBSIDIAN_REPORT_NOTES_DIRECTORY', 'REPORTS'),

    'report_index_file' => env('OBSIDIAN_REPORT_INDEX_FILE', 'index.md'),

    'academic_year_snapshots_file' => env('OBSIDIAN_ACADEMIC_YEAR_SNAPSHOTS_FILE', 'ACADEMIC YEAR SNAPSHOTS.md'),
];
