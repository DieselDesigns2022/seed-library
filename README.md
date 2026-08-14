# Seed Library

Seed Library is a private PHP/MySQL seed inventory and garden planning application for replacing a large spreadsheet with a long-term gardening database. Version 1.0 targets a typical LAMP VPS, PHP 8.3+, MySQL/MariaDB, Bootstrap 5, and vanilla JavaScript.

## Architecture

Seed Library intentionally avoids heavy frameworks. The app uses a small front-controller architecture:

- `public/index.php` routes requests, enforces authentication, and coordinates pages.
- `app/bootstrap.php` centralizes configuration, sessions, PDO, CSRF helpers, settings, and shared utilities.
- `app/seeds.php` owns seed CRUD, filters, locations, uses, companion relationships, duplication, and history logging.
- `app/import_export.php` owns CSV/XLSX imports, mapping, duplicate handling, export downloads, and print reports.
- `app/view.php` provides the Bootstrap layout and navigation.
- `app/templates/` contains page fragments for inventory, detail, and the large seed form.
- `database/schema.sql` creates the production MySQL/MariaDB schema and seed lookup data.
- `scripts/create_admin.php` creates or updates an admin user using password hashing.

## Database schema summary

The schema is designed around stable physical seed labels. `seed_number` is indexed but intentionally not unique because duplicate handling is workflow-dependent and the app must never auto-renumber labels.

Required tables implemented:

- `users`: authenticated administrators with `password_hash`.
- `seeds`: inventory, planting windows, growing conditions, flags, source, packet year, and notes.
- `categories`: vegetable, herb, flower, medicinal, and custom groups.
- `plant_families`: botanical family lookup.
- `uses`: reusable use tags.
- `seed_uses`: many-to-many seeds-to-uses table.
- `companion_relationships`: structured companion planting records with relationship types.
- `settings`: zone, ZIP, region, and reusable frost dates stored as `MM-DD`.
- `seed_locations`: storage box, container, envelope, row, slot, and storage notes.
- `seed_history`: audit trail for create/update/duplicate/delete actions.
- `statuses`: active, low stock, expired, archived, wish list, and custom statuses.

Important indexes cover seed number, name, category/family/status filters, packet year, flags, locations, companion lookup, and history lookup.

## Features

- Secure login/logout with password hashing and session regeneration.
- CSRF protection on mutating forms.
- Prepared SQL statements through PDO.
- Dashboard counts for total seeds, major categories, flags, plantable-this-month, and past windows.
- Searchable/filterable inventory with simultaneous filters for category, plant family, plant type, planting method, plantable month, flags, source, packet year, location, and status.
- Add, edit, view, duplicate, and delete seeds.
- Year-independent planting calendar using month/day windows.
- Structured companion finder with good, avoid, neutral, pest deterrent, trap crop, support, and pollinator support relationships.
- CSV and XLSX import workflow: upload, preview/map, duplicate handling, import, summary.
- CSV and XLSX exports for inventory, filtered results, calendar, companion guide, container-friendly, medicinal, pollinator, perennial, and seed bank reports.
- Print-friendly report views.
- Settings and management pages for categories, plant families, uses, and statuses.
- Responsive Bootstrap UI with mobile cards, collapsible filters, and large touch targets.

## Requirements

- PHP 8.3 or newer.
- MySQL 8 or MariaDB 10.6+.
- PHP extensions: `pdo_mysql`; `zip` and `simplexml` are required for XLSX import/export.
- Apache or Nginx configured to serve the `public/` directory.

## Installation

1. Clone or deploy the repository to your server.
2. Copy the example config:

   ```bash
   cp config.example.php config.php
   ```

3. Edit `config.php` with your database credentials and optional `base_url`.
4. Create the database and import the schema:

   ```bash
   mysql -u root -p -e "CREATE DATABASE seed_library CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   mysql -u root -p seed_library < database/schema.sql
   ```

5. Create an admin user:

   ```bash
   php scripts/create_admin.php "Admin" admin@example.com 'Use-A-Strong-Password'
   ```

6. Configure your web server document root to `public/` and open `/login`.

## Local development

With a local MySQL database configured in `config.php`:

```bash
php -S 127.0.0.1:8080 -t public
```

Then visit `http://127.0.0.1:8080/login`.

## VPS deployment notes

- Point Apache/Nginx to the repository `public/` directory, not the repository root.
- Keep `config.php` outside public web access and never commit it.
- Use HTTPS and set secure cookie flags at the web server/PHP level for production.
- Restrict filesystem permissions so the web user can write only to `storage/imports` and `storage/exports` if needed.
- Run database backups regularly, especially before large imports.
- Keep PHP, MySQL/MariaDB, and OS packages updated.

## Validation checklist for operators

After deployment, verify:

- Login rejects bad credentials and accepts the admin user.
- Adding a seed preserves the exact seed number entered.
- Editing a seed does not change seed number unless manually edited.
- Inventory filters can be combined.
- Calendar shows seeds in every month overlapped by the planting window.
- Companion finder returns relationship records.
- CSV import can skip/update/import/manual-review duplicate seed numbers.
- CSV/XLSX exports download.
- Print reports render cleanly on mobile and desktop.

## Phase 1 seed records

Phase 1 keeps the lightweight PHP/MySQL architecture and extends each seed with the following owner-facing data:

The shared Add/Edit form requires exactly six fields: **Seed Number**, **Seed Name**, **Category**, **Planting Method**, **Start Planting Date**, and **Last Recommended Planting Date**. All other Phase 1 fields listed below are optional unless a complete optional planting range is started; an optional Indoor Start, Direct Sow, or Transplant range then requires both complete endpoints.

- **Identity and classification:** Seed Number, Seed Name, Variety, Category, Plant Family, Plant Type, Planting Method, and Seed Status.
- **Planting:** required Start Planting and Last Recommended Planting month/day values; explicitly selected January–December Plantable Months; optional complete Indoor Start, Direct Sow, and Transplant month/day ranges; germination minimum/maximum; harvest/maturity days; and the existing weeks-before/after-frost and succession values.
- **Growing:** ideal soil temperature, planting depth, plant/row/thin-to spacing, sun, water, soil preference, container suitability and minimum size, trellis/support, frost/heat/drought tolerance, pollinator friendliness, medicinal use, plant height, and Annual/Biennial/Perennial status.
- **Inventory and storage:** source/brand, packet year, quantity notes, purchase date, expiration year, storage box, container, envelope, row, slot, and storage notes.
- **Relationships and notes:** multi-select Uses, up to the existing six structured companion rows, Notes, timestamps, and readable history.

All gardening windows store only month and day, never a fixed year. Complete cross-year ranges such as November through February are valid. Plantable Months are stored explicitly rather than inferred in the browser. If explicit months are absent on an older record, calendar/month filtering retains the existing start/end-window fallback.

Seed Number is a free-form physical label. The application preserves it exactly, including spaces, letters, punctuation, and symbols; it never automatically renumbers it. The database intentionally permits duplicate Seed Numbers, and duplication retains the source label unless the owner edits it. Seed Location is separate.

The shared Add/Edit form provides:

- **Save:** save and open Detail.
- **Save and Add Another:** save and return to a clean Add form.
- **Save and Duplicate:** save the source first, atomically copy it with its location, Uses, and companions, record a duplication history event, then open the copy for editing. A failed copy does not undo the already successful source save.
- **Cancel:** leave without saving.

Edit and Detail retain POST/CSRF-protected Delete and the existing Duplicate action. Companion rows preserve notes and validate seed references, relationship types, self-relationships, and duplicate seed/type pairs; Phase 1 intentionally retains the six-row limit.

The owner selects a single **Perennial/Biennial Status**. `Perennial` keeps the compatibility `perennial` flag true; `Annual` and `Biennial` keep it false. Existing perennial records are backfilled by the migration. Imports apply the same rule, and an existing richer status remains authoritative when an update maps only the older boolean.

Seed Detail presents Basic Information, Planting Information, Growing Conditions, Garden Uses, Companion Planting, Storage, Notes, and History. Empty values use `Not recorded` or a specific clean empty state. History records creation, duplication, and meaningful updates with friendly labels and readable before → after values for lookups, flags, Plantable Months, storage, Uses, and companions. An unchanged Edit does not add an empty update event, and the derived compatibility boolean is not shown as a duplicate status change.

### Phase 1 import behavior

CSV and XLSX mapping suggestions recognize Seed Number/Seed #, Seed Name/Plant Name, Variety, Category, Plant Family/Family, Status, Source/Seed Source/Seed Source/Brand, and Packet Year, plus exact seed-column headings. Category maps only to `category_id`; Variety remains separate. Category, Plant Family, and Status accept a valid numeric ID or a case-insensitive existing lookup name. An unknown non-empty name rejects that row.

CSV and XLSX Seed Number text is preserved exactly. XLSX data strings retain leading/trailing spaces while header text is trimmed for mapping. Mapped numeric and Plantable Month values receive the same strict server-side validation as the form.

Temporary uploads use the first writable location in this order: optional `app.imports_path`, `BASE_PATH/storage/imports`, then `sys_get_temp_dir()/seed-library-imports`. Missing directories are created when possible. If no location is writable, Import returns a controlled owner-facing error without exposing paths; database failures are logged and rolled back without exposing SQL/PDO details.

### Verification status

Phase 1 code-level verification included PHP lint plus targeted static checks for parsing, validation, history normalization, transaction guards, and controlled error paths. Separately, Phase 1 isolated VPS/database verification passed and the owner’s Phase 1 live smoke test passed. Those live pass statements do not imply that every item in the broader regression checklist was exercised. Phase 2 database/browser verification remains separate and pending; see `TESTING.md`.

## Phase 2: dashboard and scalable inventory

Phase 2 turns saved records into a searchable seed bank. The dashboard now derives twelve live metrics from category, flag, planting-method, Plantable Months, and reusable month/day window data. Its Quick Search submits to the same inventory global search used on the inventory page, and the dashboard links directly to inventory, Add Seed, the existing calendar and companion tools, import, and export/print.

Dashboard category metrics use one centralized name map: Food Crops recognizes `Vegetable`, `Food Crop`, and `Food Crops`; Herbs recognizes `Herb`; Flowers recognizes `Flower`; and Medicinal Plants recognizes the `Medicinal` category or the seed's Medicinal flag. Category names are normalized to lowercase for comparison. Update `dashboard_category_count_rules()` when owner-managed category conventions expand; no additional category schema is required.

Inventory search covers Seed Number, name, variety, category, family, plant type, Uses, companions, and notes with partial matching. `EXISTS` relationship subqueries prevent Use or companion matches from multiplying seed rows. Combinable controls cover Category, Plant Family, Plant Type, exact Planting Method, Plantable Month, reusable Start/Last Planting MM-DD ranges, Container/Pollinator/Medicinal/Perennial/Frost/Heat/Drought/Trellis Yes/No flags, Indoor Start/Direct Sow/Transplant capability, multiple Uses (all selected Uses must match), Seed Source/Brand, Packet Year, full Seed Location text, companion text, and Status. Non-empty planting-date boundaries are server-validated as real `MM-DD` dates; invalid values remain in the controls and produce an owner-facing error instead of running a broadened query.

Results use an allowlisted SQL sort map for major identity, lookup, planting, germination, maturity, source, year, location, and status fields. Database-side `COUNT`, `LIMIT`, and `OFFSET` pagination offers 25/50/100/200 rows per page, shows the current result range and page, clamps out-of-range pages, and preserves active query state in sorting and pagination links. The inventory distinguishes an empty library from filters with no matches. Desktop uses an information-dense table; small screens use cards with the important growing data, flags, and View/Edit/POST-protected Duplicate/Delete actions.

Inventory provides a direct filtered CSV download and a filtered print view while preserving the active query. The existing Export and Print pages remain available from the dashboard and navigation for their established report choices; Phase 2 does not add Phase 4 backup/export expansion.

No Phase 2 schema or migration was required: the Phase 1 foreign keys, relationship primary keys, unique one-to-one location record, and existing seed indexes support the query design. Search deliberately uses correlated `EXISTS` clauses and pagination instead of adding speculative indexes before database query plans can be measured on a representative collection.

Phase 2 PHP lint and source-level static checks passed, and the Phase 2 live smoke test passed. The broader database-backed and authenticated desktop/mobile regression scenarios in `TESTING.md` remain separate verification work and are not implied by that smoke test.

## Phase 3: garden planning

The Planting Calendar supports every month, explicit Plantable Months, normal and cross-year planting windows, and filters for planting methods and useful plant groups. Inferred grouping is centralized in `calendar_group_rules()` / `calendar_group_matches()`: **Fall Crop** means an existing planting window or explicit month includes August–November; Flowers and Herbs use owner-managed category names; Medicinal uses either its category or existing flag.

Companion relationships remain structured and validated, but the edit form now has dynamic Add/Remove controls without a fixed six-row limit. The finder resolves relationships stored from either side and visually separates every relationship type, especially Good Companion and Avoid. Good Companion, Avoid, and Neutral are symmetric. Pest Deterrent, Trap Crop, Support Plant, and Pollinator Support are directional: results preserve every distinct stored source → target direction. Finder rows are deduplicated by returned seed and relationship type while distinct notes and directions are merged.

Existing databases should run `database/2026-08-13-phase-3-statuses.sql`. It only inserts missing practical statuses and never removes, renames, or disables custom statuses. Fresh installations receive the identical status set from `database/schema.sql`.

## Phase 4: import, export, printing, and backups

The known 20-column workbook headings are detected and transformed: Label remains the exact Seed Number; Seed splits on only the first semicolon into name/variety; month-name dates, unambiguous planting methods, numeric ranges, flags, lifecycle, source, and safe succession values are derived. Category and ambiguities are resolved in a per-row review without creating lookup values, and otherwise unmapped non-empty cells retain their original heading in Notes. Generic CSV/XLSX mapping remains available. XLSX parsing follows workbook relationships and handles default XML namespaces and rich shared strings.

Imports now follow an explicit upload, preview/map, validate, error review, confirmation, transactional import, and detailed-summary workflow. Common owner spreadsheet headings are suggested automatically; lookup names resolve case-insensitively to existing categories, families, and statuses. Every uploaded row retains its original row number/raw values in review. Normalization, lookup, numeric, date, and validation failures remain blocking and must be corrected through the editable mapped JSON/fields or explicitly skipped. Duplicate physical labels can be skipped, updated, imported as another record, or resolved individually during manual review; unresolved rows block confirmation. After review edits, exact Seed Numbers are re-queried and within-file duplicates and counts are rebuilt; Update Existing is rejected without a current database or earlier staged exact target. Within-file Update Existing targets the record inserted by the earlier occurrence. Generic mappings include Storage Box, Container, Envelope, Row, Slot, and Storage/Location Notes, which are upserted atomically with the seed transaction. Existing locations update only fields actually supplied by the row: an omitted field is preserved, while an explicitly mapped blank clears that field. Seed Number remains exact text and is never generated or renumbered. The final summary preserves workflow-history counts for rows that encountered errors, missing required fields, or manual review even when corrected or skipped; only unresolved current errors block execution. A defensive execution guard also rejects an Update Existing action whose target became stale. No sample or starting XLSX is fabricated.

All Seeds and active-filter Results download as CSV or XLSX. The two filtered buttons on Inventory carry the complete Phase 2 query string. The standalone Export page offers only All Seeds; filtered downloads are available only from Inventory with its active filters. Printable seed reports use concise report-specific columns instead of raw database records. The Companion Guide accepts a plant/Seed Number filter. Companion output applies the Phase 3 symmetric/directional rules, canonicalizes mutual pairs, and merges reciprocal notes without duplicate clutter. Print Reports provides deliberately print-styled Full Library, monthly Calendar, Companion Guide, Container, Medicinal, Pollinator, Perennial/Biennial, and Seed Bank views. Seed Bank Inventory is restricted to Seed Number, Seed Name, Category, and composed Seed Location.

Full database JSON backup/restore is separate from seed export and restricted to users with `is_owner = 1`. Downloads stream directly with private/no-store headers; uploaded restores are size-, JSON-format-, version-, table-, confirmation-, authentication-, and CSRF-validated, and must contain at least one owner with a valid ID, email, and supported password hash. A successful restore rotates the session, removes the authenticated user, and requires a fresh owner login. Failures are logged server-side and show no SQL, credentials, filesystem paths, or backup data. Existing installations must apply `database/2026-08-13-phase-4-owner.sql`; it makes existing administrators owners to preserve the existing single-owner deployment.

### Automatic VPS backups

Set `app.backup_path` in `config.php` to `/var/backups/seed-library` (or another directory outside the web root), then install the `www-data`-owned backup and log directories and the daily cron entry:

```bash
sudo install -d -m 0700 -o www-data -g www-data /var/backups/seed-library
sudo install -d -m 0750 -o www-data -g www-data /var/log/seed-library
( sudo crontab -u www-data -l 2>/dev/null; echo '17 2 * * * /usr/bin/php /var/www/garden.dieseldesigns.co/scripts/database_backup.php >> /var/log/seed-library/backup.log 2>&1' ) | sudo crontab -u www-data -
sudo -u www-data /usr/bin/php /var/www/garden.dieseldesigns.co/scripts/database_backup.php
```

The script writes mode-0600 gzip-compressed application database backups at `/var/backups/seed-library/daily-YYYY-MM-DD.json.gz`, retains the newest 7 daily files, makes `weekly-YYYY-Www.json.gz` each Sunday, and retains the newest 4 weekly files. Cron scheduling is intentionally performed on the VPS after merge. To restore an automatic backup, decompress it to a protected temporary location, sign in as an owner, open **Tools → Database Backup & Restore**, download a pre-restore backup, upload the JSON, type `RESTORE DATABASE`, and confirm. Delete the decompressed temporary copy afterward.
