# Usage

## Managing Live Records

1. Navigate to an index page (Formation, Social Apostolate, or Parish Involvement)
2. Use the scope switcher to filter records (All Time / Unsaved / Saved Report)
3. Click the "Add Record" accordion to open the creation form
4. Fill in the fields and submit
5. Existing records can be expanded inline -- click the row to reveal edit and delete controls

---

## Creating Saved Reports

1. From the Dashboard, scroll to the "Save Report Group" section
2. Expand the accordion
3. Select a title (optional) and pick entries from the available categories
4. Click "Save Report" to create the snapshot
5. Manage saved reports from the Reports page

---

## Syncing with Obsidian

1. Edit your Markdown source notes directly in Obsidian
2. Navigate to Reports and click "Sync from Obsidian"
3. The application reads the YAML front-matter and updates PostgreSQL accordingly

---

## Routes

| Method | URI | Action |
|---|---|---|
| `GET` | `/` | Dashboard overview |
| `GET` | `/indexes/{type}` | Per-type record management |
| `POST` | `/entries` | Create a live entry |
| `PATCH` | `/entries/{entry}` | Update a live entry |
| `DELETE` | `/entries/{entry}` | Delete a live entry |
| `POST` | `/report-groups` | Create a saved report |
| `GET` | `/reports` | Saved reports list |
| `GET` | `/reports/{reportGroup}` | Saved report detail |
| `PATCH` | `/reports/{reportGroup}` | Update a saved report |
| `DELETE` | `/reports/{reportGroup}` | Delete a saved report |
| `POST` | `/reports/{reportGroup}/records` | Add record to report |
| `PATCH` | `/reports/{reportGroup}/records/{item}` | Update report record |
| `DELETE` | `/reports/{reportGroup}/records/{item}` | Delete report record |
| `POST` | `/reports/sync-from-obsidian` | Sync notes from vault |
| `GET` | `/academic-year-snapshots` | Academic year archives |
| `POST` | `/academic-year-snapshots` | Create a snapshot |
| `DELETE` | `/academic-year-snapshots/{snapshot}` | Delete a snapshot |
