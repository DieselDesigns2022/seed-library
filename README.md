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

Phase 2 PHP lint and source-level static checks have passed. Database-backed count/search/filter/sort/pagination checks and authenticated desktop/mobile browser verification are still pending.
