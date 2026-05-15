# Features

## Live Record Management

- **Three index types** -- Manage Formation, Social Apostolate, and Parish Involvement records independently
- **Inline editing** -- Expand any record to edit its fields in place without leaving the page
- **Scope filtering** -- Switch between All Time, Unsaved, and Saved Report scopes with one click
- **Real-time add/update/delete** -- Changes are persisted immediately to PostgreSQL

## Obsidian Integration

- **Two-way sync** -- Records are sourced from and written to plain Markdown files in an Obsidian vault
- **YAML front-matter parsing** -- Reads structured data from Obsidian notes via `spatie/yaml-front-matter`
- **Live source editing** -- Edit the index note directly, or add records through the web UI and have them appended to the matching Markdown file
- **Sync from vault** -- Pull the latest edits made in Obsidian into PostgreSQL

## Saved Reports

- **Snapshot reports** -- Freeze a set of live records into a named, tagged saved report
- **Per-report records** -- Add, edit, or remove records within the scope of a saved report
- **Obsidian note mirroring** -- Each saved report generates and tracks its own summary note and per-record notes
- **Locking** -- Entries captured in a saved report are locked and tagged in the live view

## Academic Year Archiving

- **Snapshot builder** -- Create academic-year archives from the live service ledger
- **Dedicated archive view** -- Browse and manage archived academic years separately from active records

## User Interface

- **Paper-ledger aesthetic** -- Warm, cream-toned design with serif headings and a physical ledger feel
- **Progressive disclosure** -- Forms and record details are hidden behind accordion toggles, reducing cognitive load
- **Keyboard accessible** -- Full focus-visible outlines, skip-to-content link, and screen-reader friendly markup
- **Responsive** -- Adapts from mobile to wide desktop layouts
- **Staggered page animations** -- Subtle fade-in transitions for a polished feel

---

# Screenshots

Screenshots are pending. Key views:

- **Dashboard** -- Overview with per-type stat cards, grand total, and saved report sidebar
- **Index Show** -- Per-type record management with scope switcher, inline editing, and add form
- **Saved Reports** -- List of saved report snapshots with per-type record breakdowns
- **Report Detail** -- Individual saved report management with rename, sync, delete, and per-type record sections
- **Academic Year Snapshots** -- Archive browsing and snapshot builder
