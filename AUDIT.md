# Seed Library Project Audit

Audit date: 2026-06-06

Scope: This audit inspects the current repository only. No application code was changed. The only intended file change for this task is this `AUDIT.md` file.

## Audit methodology

Reviewed these files:

- `README.md`
- `TESTING.md`
- `config.example.php`
- `database/schema.sql`
- `scripts/create_admin.php`
- `app/bootstrap.php`
- `app/view.php`
- `app/seeds.php`
- `app/import_export.php`
- `app/templates/seed_detail.php`
- `app/templates/seed_form.php`
- `app/templates/seeds_index.php`
- `public/index.php`
- `public/assets/app.css`
- `public/assets/app.js`
- `public/.htaccess`

Commands used during audit:

```bash
git status --short
find /workspace -name AGENTS.md -print
find . -maxdepth 3 -type f | sed 's#^./##' | sort
rg -n "function |if \\(\\$path|preg_match|CREATE TABLE|FROM |JOIN |INSERT INTO|UPDATE |DELETE FROM|href=|action=|name=|class_exists|simplexml|TODO|placeholder|Not implemented|print_page|export_page|import_page|manage_page|settings_page|dashboard_page|calendar_page|companions_page" app public database README.md TESTING.md scripts config.example.php .gitignore
```

No `AGENTS.md` files were found under `/workspace`.

---

## 1. Every route/page that exists

All routes are handled in `public/index.php`. `login` and `logout` are handled before `require_auth()`. Every other route requires authentication.

| Route | Method(s) | Page/action | Controller/function | Template | Auth required | Status |
| --- | --- | --- | --- | --- | --- | --- |
| `/login` | GET/POST | Login page and login submission | `login_page()` | inline HTML inside function | No | Exists |
| `/logout` | POST intended; GET also logs out | Logout action | `logout_action()` | none | No explicit auth check | Exists |
| `/dashboard` | GET | Dashboard | `dashboard_page()` | inline HTML inside function | Yes | Exists |
| `/seeds` | GET | Seed inventory | `seeds_page()` | `app/templates/seeds_index.php` | Yes | Exists |
| `/seeds/create` | GET/POST | Add seed | `seed_form_page(null)` | `app/templates/seed_form.php` | Yes | Exists |
| `/seeds/{id}` | GET | View seed | `seed_detail_page((int)$id)` | `app/templates/seed_detail.php` | Yes | Exists |
| `/seeds/{id}/edit` | GET/POST | Edit seed | `seed_form_page((int)$id)` | `app/templates/seed_form.php` | Yes | Exists |
| `/seeds/{id}/duplicate` | POST intended | Duplicate seed action | `duplicate_seed((int)$id)` | none | Yes | Exists |
| `/seeds/{id}/delete` | POST intended | Delete seed action | `delete_seed((int)$id)` | none | Yes | Exists |
| `/calendar` | GET | Planting calendar | `calendar_page()` | inline HTML inside function | Yes | Exists |
| `/companions` | GET | Companion finder | `companions_page()` | inline HTML inside function | Yes | Exists |
| `/import` | GET/POST | Import upload/map/import/summary | `import_page()` | inline HTML inside function | Yes | Exists |
| `/export` | GET | Export form and download | `export_page()` | inline HTML inside function | Yes | Exists |
| `/print` | GET | Print reports | `print_page()` | inline HTML inside function | Yes | Exists |
| `/settings` | GET/POST | Settings | `settings_page()` | inline HTML inside function | Yes | Exists |
| `/manage/categories` | GET/POST | Manage categories | `manage_page('categories')` | inline HTML inside function | Yes | Exists |
| `/manage/families` | GET/POST | Manage plant families | `manage_page('families')` | inline HTML inside function | Yes | Exists |
| `/manage/uses` | GET/POST | Manage uses | `manage_page('uses')` | inline HTML inside function | Yes | Exists |
| `/manage/statuses` | GET/POST | Manage statuses | `manage_page('statuses')` | inline HTML inside function | Yes | Exists |
| any unmatched route | GET/etc. | 404 | inline fallback in `match_route()` | inline HTML inside function | Yes, unless `/login` or `/logout` | Exists |

### Navigation links found

`app/view.php` links to Dashboard, Inventory, Calendar, Companions, Import, Export, Print Reports, Settings, Categories, Plant Families, Uses, Statuses, and Logout. Inventory pages link to Add Seed, Edit, Detail, Duplicate, Delete, and Print where relevant.

### Missing or weak route behavior

- `/logout` is reachable by GET and destroys the session without CSRF if the request is not POST. The UI uses POST with CSRF, but the route function allows GET logout.
- `/seeds/{id}/duplicate` and `/seeds/{id}/delete` do not check request method. They call `verify_csrf()`, so accidental GET will fail with invalid CSRF rather than a 405.
- No dedicated routes exist for import preview validation errors, manual review queues, or per-row import review. The import flow is compressed into `/import?step=map` and `/import?step=summary`.
- No dedicated report-specific print route exists; `/print` switches by query string only.
- No API routes exist.

---

## 2. Every database table actually used by the code

| Table | Used by code? | Evidence/files/functions | Operations observed |
| --- | --- | --- | --- |
| `users` | Yes | `current_user()`, `login_page()`, `scripts/create_admin.php` | SELECT, INSERT/UPDATE admin |
| `categories` | Yes | `reference_data()`, `dashboard_page()`, `seed_query()`, seed form, management page | SELECT, INSERT, DELETE, FK reference |
| `plant_families` | Yes | `reference_data()`, `seed_query()`, seed form, management page | SELECT, INSERT, DELETE, FK reference |
| `uses` | Yes | `reference_data()`, `seed_uses_for()`, seed form, management page | SELECT, INSERT, DELETE, FK reference |
| `statuses` | Yes | `reference_data()`, `seed_query()`, seed form, management page | SELECT, INSERT/UPDATE, DELETE, FK reference |
| `settings` | Yes | `setting()`, `save_setting()`, `dashboard_page()`, `settings_page()` | SELECT, INSERT/UPDATE |
| `seeds` | Yes | `seed_query()`, `seed_find()`, `seed_save()`, `duplicate_seed()`, `delete_seed()`, dashboard, imports, exports, calendar | SELECT, INSERT, UPDATE, DELETE |
| `seed_locations` | Yes | `seed_query()`, `seed_find()`, `save_location()`, seed detail/form, export via `seed_query()` | SELECT, INSERT/UPDATE, DELETE by cascade |
| `seed_uses` | Yes | `seed_uses_for()`, `save_seed_uses()`, duplicate logic, seed form/detail | SELECT, INSERT, DELETE |
| `companion_relationships` | Yes | `seed_companions_for()`, `save_companion_rows()`, `companions_page()`, `rows_for_export('companions')` | SELECT, INSERT, DELETE by replace-all on seed edit |
| `seed_history` | Yes | `log_history()`, `seed_save()`, `duplicate_seed()`, `delete_seed()` | INSERT only from app; no UI SELECT |

---

## 3. Every database table that exists but is not currently used

No schema table is completely unused by the code.

However, some tables are only partially used:

| Table | Partial-use finding |
| --- | --- |
| `seed_history` | Written through `log_history()`, but there is no route/page to display seed history or audit records. |
| `settings` | Used for zone/ZIP/region/frost dates, but no validation enforces `MM-DD` format for frost dates. |
| `statuses` | Used in seed forms and filters, but management is add/delete only; no row-level edit UI exists. |
| `companion_relationships` | Used for outgoing relationships from a seed, but reciprocal relationship creation/display is not automatic. |

---

## 4. Features claimed in completion report and implementation status

Legend:

- **Fully implemented**: Feature exists, is connected to routes/UI/functions, and is reasonably functional for V1.
- **Partially implemented**: Feature exists and is connected, but has notable missing behavior, validation, robustness, or production gaps.
- **Placeholder only**: UI/function exists but does little or mostly documents intent.
- **Not implemented**: Claimed feature is absent.

| Claimed feature | Status | Evidence | Notes |
| --- | --- | --- | --- |
| Lightweight front-controller architecture | Fully implemented | `public/index.php`, `app/bootstrap.php`, `app/view.php` | Central route switch and shared renderer exist. |
| Secure config example and `.gitignore` | Fully implemented | `config.example.php`, `.gitignore` | `config.php` ignored. |
| Password hashing | Fully implemented | `scripts/create_admin.php`, `login_page()` | Admin script uses `password_hash()`, login uses `password_verify()`. |
| Session authentication | Fully implemented | `current_user()`, `require_auth()`, route gate in `public/index.php` | Non-login/logout routes require auth. |
| CSRF protection | Partially implemented | `csrf_token()`, `csrf_field()`, `verify_csrf()`, forms | POST forms use CSRF, but logout permits GET without CSRF and method checks are weak. |
| Prepared SQL statements | Partially implemented | Most write/filter queries use `db()->prepare()` | Some static SQL uses `query()` appropriately; dynamic table names in `manage_page()` are map-controlled. |
| Dashboard counts | Partially implemented | `dashboard_page()` | Counts exist, but category counts rely on exact category names and “past planting window” only handles non-wrapping ranges. |
| Seed inventory | Fully implemented | `/seeds`, `seed_query()`, `seeds_index.php` | Inventory page exists with table/mobile cards and filters. |
| Add seed | Fully implemented | `/seeds/create`, `seed_form_page(null)`, `seed_save()` | Connected form and persistence exist. |
| Edit seed | Fully implemented | `/seeds/{id}/edit`, `seed_form_page($id)`, `seed_save($id)` | Connected form and persistence exist. |
| View seed/detail | Partially implemented | `/seeds/{id}`, `seed_detail_page()`, `seed_detail.php` | Shows many fields but omits some fields captured by form, such as purchase date, expiration year, indoor/transplant/succession values, and storage notes. |
| Duplicate seed | Partially implemented | `/seeds/{id}/duplicate`, `duplicate_seed()` | Works as an action, but modifies seed number by appending `-copy`, which conflicts with the stronger “never modify automatically” seed-number rule unless considered a user-facing duplicate helper. It also does not duplicate companion rows. |
| Delete seed | Fully implemented | `/seeds/{id}/delete`, `delete_seed()` | Deletes seed; cascades handle dependent rows. Method handling could be stronger. |
| Search | Fully implemented | `seed_query()`, `seeds_index.php` | Search covers seed number, name, variety, notes. |
| Filters | Partially implemented | `seed_query()`, `seeds_index.php` | Required filters are present, but sort UI is not present despite query support. Filtered export is not connected from inventory. |
| Companion Finder | Partially implemented | `/companions`, `companions_page()`, `companion_relationships` | Search and type filter exist. Relationship management only happens inside seed edit; no dedicated add/edit/delete relationship page. |
| Planting Calendar | Fully implemented for simple month windows | `/calendar`, `calendar_page()`, `plantable_in_month_sql()` | Handles overlapping month ranges including wrap-around logic in SQL. Displays one selected month at a time. |
| CSV Import | Partially implemented | `parse_csv_file()`, `import_page()` | Upload/map/import/summary exist. Validation is minimal; manual review only increments a counter and creates no review UI. |
| XLSX Import | Partially implemented | `parse_xlsx_file()`, `import_page()` | Uses native `ZipArchive` and `simplexml_load_string()`. Parser is simplistic and may fail or misread workbooks with inline strings, sparse cells, multiple sheets, formulas, or missing shared strings. No Composer dependency is used. |
| CSV Export | Fully implemented | `export_rows_csv()`, `export_page()` | Streams CSV downloads. |
| XLSX Export | Partially implemented | `export_rows_xlsx()` | Creates a minimal XLSX with `ZipArchive`. No explicit `ZipArchive` availability check before export; generated cells are inline strings. |
| Print Reports | Partially implemented | `/print`, `print_page()`, print CSS | Generic print table exists. No report-specific templates or polished report layouts beyond table output. |
| Settings | Partially implemented | `/settings`, `settings_page()`, `setting()`, `save_setting()` | Settings form exists. No validation for ZIP or `MM-DD` frost date format. |
| Category Management | Partially implemented | `/manage/categories`, `manage_page()` | Add/delete exists. No edit UI; deleting referenced records may fail due to FK constraints. |
| Plant Family Management | Partially implemented | `/manage/families`, `manage_page()` | Add/delete exists. No edit UI; deleting referenced records may fail. |
| Uses Management | Partially implemented | `/manage/uses`, `manage_page()` | Add/delete exists. No edit UI; deleting referenced records may fail. |
| Status Management | Partially implemented | `/manage/statuses`, `manage_page()` | Add/delete/add-with-upsert exists. No edit UI; deleting referenced records may fail. |
| Mobile layout | Partially implemented | `app/view.php`, `public/assets/app.css`, `seeds_index.php` | Responsive meta tag, Bootstrap, mobile cards, touch-target CSS exist. Other complex tables may still overflow and form layout is dense. |
| README documentation | Fully implemented | `README.md` | Overview, requirements, setup, deployment notes exist. |
| TESTING.md documentation | Fully implemented | `TESTING.md` | Manual testing guide exists. |
| Admin creation script | Fully implemented | `scripts/create_admin.php` | Creates/updates admin with hashed password. |
| Audit/history tracking | Partially implemented | `seed_history`, `log_history()` | Writes records but no UI/report to view them. |
| Import duplicate options Skip/Update/Import Anyway/Manual Review | Partially implemented | import duplicate select and import branch | Skip, update, and import anyway execute; manual review only counts skipped rows and does not persist review queue. |
| Export report types | Partially implemented | `export_page()`, `rows_for_export()` | Options exist, but “Filtered Results” is not actually connected to active inventory filters from the UI; it defaults to current export query params. |

---

## 5. Specific feature verification

### Login

- Status: **Fully implemented**
- Route: `/login`
- Files: `public/index.php`, `app/bootstrap.php`, `app/view.php`, `scripts/create_admin.php`
- Functions: `login_page()`, `csrf_field()`, `verify_csrf()`, `db()`, `password_verify()`, `current_user()`
- Evidence: Login page renders a form, verifies CSRF, queries `users` by email, verifies password hash, regenerates session ID, stores `$_SESSION['user_id']`, flashes success, redirects to dashboard.
- Concerns: No rate limiting or lockout. Error message is generic, which is good.

### Logout

- Status: **Partially implemented**
- Route: `/logout`
- Files: `public/index.php`, `app/view.php`
- Functions: `logout_action()`, `csrf_field()`
- Evidence: Nav logout form uses POST and CSRF. `logout_action()` clears session and redirects to login.
- Concerns: `logout_action()` only verifies CSRF if request is POST. A GET request to `/logout` also logs out.

### Dashboard

- Status: **Partially implemented**
- Route: `/dashboard`
- Files: `public/index.php`, `app/bootstrap.php`
- Functions: `dashboard_page()`, `setting()`, `setting_date_label()`, `plantable_in_month_sql()`
- Evidence: Counts total seeds, vegetables, herbs, flowers, medicinal, pollinator, container-friendly, perennials, plantable this month, and past planting window.
- Concerns: Category-specific counts depend on hard-coded category names. Past planting window logic does not fully handle planting windows that wrap across year-end.

### Seed CRUD

- Status: **Fully implemented with detail omissions**
- Routes: `/seeds`, `/seeds/create`, `/seeds/{id}`, `/seeds/{id}/edit`, `/seeds/{id}/delete`
- Files: `public/index.php`, `app/seeds.php`, `app/templates/seeds_index.php`, `app/templates/seed_form.php`, `app/templates/seed_detail.php`
- Functions: `seeds_page()`, `seed_form_page()`, `seed_detail_page()`, `seed_query()`, `seed_find()`, `seed_payload()`, `validate_seed()`, `seed_save()`, `save_location()`, `save_seed_uses()`, `save_companion_rows()`, `delete_seed()`
- Evidence: List, create, read, update, and delete routes are connected to templates and persistence functions.
- Concerns: Detail page omits some stored/form fields. Validation is minimal. Delete route lacks method check beyond CSRF failure on non-POST.

### Duplicate Seed

- Status: **Partially implemented**
- Route: `/seeds/{id}/duplicate`
- Files: `public/index.php`, `app/seeds.php`, `app/templates/seed_detail.php`
- Functions: `duplicate_seed()`
- Evidence: Detail page has Duplicate form; route calls `duplicate_seed()`, which copies seed columns, appends `-copy`, copies location and uses, logs history, and redirects to edit.
- Concerns: It auto-modifies `seed_number` by appending `-copy`; this should be revisited because the seed-number rule says labels should never be automatically modified. Companion relationships are not duplicated.

### Search

- Status: **Fully implemented**
- Route: `/seeds`
- Files: `app/seeds.php`, `app/templates/seeds_index.php`
- Functions: `seed_query()`
- Evidence: Search filter checks seed number, name, variety, and notes using prepared parameters.

### Filters

- Status: **Partially implemented**
- Route: `/seeds`
- Files: `app/seeds.php`, `app/templates/seeds_index.php`
- Functions: `seed_query()`, `plantable_in_month_sql()`
- Evidence: UI and query support category, plant family, status, plantable month, plant type, planting method, seed source, packet year, storage box, container-friendly, pollinator, medicinal, perennial, frost tolerant, heat tolerant, trellis.
- Concerns: Sort support exists in `seed_query()` but no visible sort controls are implemented. “Filtered Results” export is not linked from the inventory page with current filters.

### Companion Finder

- Status: **Partially implemented**
- Route: `/companions`
- Files: `public/index.php`, `app/seeds.php`, `app/templates/seed_form.php`, `app/templates/seed_detail.php`, `database/schema.sql`
- Functions: `companions_page()`, `seed_companions_for()`, `save_companion_rows()`
- Evidence: Finder searches companion relationships by seed/companion name or seed number and filters by relationship type.
- Concerns: No dedicated relationship management page; relationships can only be edited as part of seed edit. Only outgoing relationships for a seed are displayed on the detail page.

### Planting Calendar

- Status: **Fully implemented for selected-month lookup**
- Route: `/calendar`
- Files: `public/index.php`, `app/bootstrap.php`, `app/seeds.php`
- Functions: `calendar_page()`, `seed_query()`, `plantable_in_month_sql()`
- Evidence: User selects month; query returns seeds where planting month range overlaps selected month.
- Concerns: Calendar displays only one month at a time. It does not provide a full year grid or computed frost-relative schedules.

### CSV Import

- Status: **Partially implemented**
- Route: `/import`
- Files: `app/import_export.php`
- Functions: `parse_csv_file()`, `import_page()`
- Evidence: Supports upload, column mapping, duplicate select, insert/update/skip/import-anyway paths, and summary.
- Concerns: Minimal validation, no transaction, no file size/MIME checks, no category/name lookup mapping, no location/use/companion import support, no persisted manual review queue.

### XLSX Import

- Status: **Partially implemented**
- Route: `/import`
- Files: `app/import_export.php`, `TESTING.md`
- Functions: `parse_xlsx_file()`, `import_page()`
- Evidence: Uses `ZipArchive`, reads shared strings and `xl/worksheets/sheet1.xml`, maps columns like CSV.
- Composer dependency finding: No `composer.json` or `composer.lock` exists. XLSX import/export uses native PHP extensions, not Composer packages.
- Concerns: Parser ignores cell references and inline-string cells; sparse rows can misalign columns. Multiple sheets, formulas, dates, numeric formatting, and many common XLSX variants are not robustly handled.

### CSV Export

- Status: **Fully implemented**
- Route: `/export?download=1&format=csv`
- Files: `app/import_export.php`
- Functions: `export_page()`, `rows_for_export()`, `export_rows_csv()`
- Evidence: Streams `seed-library-export.csv` with headers and rows.
- Concerns: Exported row shape varies by report. No selected-column UI.

### XLSX Export

- Status: **Partially implemented**
- Route: `/export?download=1&format=xlsx`
- Files: `app/import_export.php`
- Functions: `export_page()`, `rows_for_export()`, `xlsx_column_name()`, `export_rows_xlsx()`
- Evidence: Creates a minimal XLSX ZIP package using `ZipArchive`.
- Composer dependency finding: No Composer packages required.
- Concerns: No preflight check for `ZipArchive` in export path; missing extension would fatal. Minimal XLSX package may not cover advanced formatting, but should be adequate for simple workbook export.

### Print Reports

- Status: **Partially implemented**
- Route: `/print`
- Files: `app/import_export.php`, `app/view.php`, `public/assets/app.css`
- Functions: `print_page()`, `render()`
- Evidence: Generic report table renders with print-specific layout option and CSS hides nav/forms/buttons.
- Concerns: No specialized layouts per report. The nav link opens generic `/print` only.

### Settings

- Status: **Partially implemented**
- Route: `/settings`
- Files: `public/index.php`, `app/bootstrap.php`, `database/schema.sql`
- Functions: `settings_page()`, `setting()`, `save_setting()`
- Evidence: Form edits zone, ZIP, region, average last frost, average first frost.
- Concerns: No validation for ZIP or date format. Dates are stored as strings and only expected by convention to be `MM-DD`.

### Category Management

- Status: **Partially implemented**
- Route: `/manage/categories`
- Files: `public/index.php`, `app/bootstrap.php`, `database/schema.sql`
- Functions: `manage_page('categories')`, `reference_data()`
- Evidence: Add and delete UI exists.
- Concerns: No edit UI. Delete can fail when referenced by seeds because schema uses FK references from seeds.

### Plant Family Management

- Status: **Partially implemented**
- Route: `/manage/families`
- Files: `public/index.php`, `app/bootstrap.php`, `database/schema.sql`
- Functions: `manage_page('families')`, `reference_data()`
- Evidence: Add and delete UI exists.
- Concerns: No edit UI. Delete can fail when referenced by seeds.

### Uses Management

- Status: **Partially implemented**
- Route: `/manage/uses`
- Files: `public/index.php`, `app/bootstrap.php`, `database/schema.sql`
- Functions: `manage_page('uses')`, `reference_data()`
- Evidence: Add and delete UI exists.
- Concerns: No edit UI. Delete can fail when referenced by `seed_uses`.

### Status Management

- Status: **Partially implemented**
- Route: `/manage/statuses`
- Files: `public/index.php`, `app/bootstrap.php`, `database/schema.sql`
- Functions: `manage_page('statuses')`, `reference_data()`
- Evidence: Add/delete UI exists; add uses upsert by status name and active flag.
- Concerns: No edit UI for existing statuses. Delete can fail when referenced by seeds.

### Mobile Layout

- Status: **Partially implemented**
- Files: `app/view.php`, `public/assets/app.css`, `app/templates/seeds_index.php`
- Evidence: Responsive viewport meta, Bootstrap 5, collapsible nav, mobile cards for inventory, desktop table hidden on small screens, touch target sizes.
- Concerns: Complex forms and non-inventory tables may still be dense/overflow-heavy on phones. There are no screenshots or visual regression tests.

---

## 6. Dead code and unused assets

| Item | Type | Finding |
| --- | --- | --- |
| `table_exists()` in `app/bootstrap.php` | Function | Not referenced anywhere in current app code. |
| `data-check-all` handler in `public/assets/app.js` | JS behavior | No current template appears to emit `data-check-all`; handler is unused. |
| `storage/exports/` | Directory | Kept by `.gitkeep`, but current export code streams downloads and uses `tempnam(sys_get_temp_dir())`; it does not write to `storage/exports`. |
| Sort support in `seed_query()` | Partial dead feature | Query accepts `sort` and `direction`, but inventory UI has no sort controls or clickable table headers. |
| `table_exists()` table introspection | Dead helper | Could be useful for installer checks but currently unused. |

No `TODO`, `Not implemented`, or explicit placeholder marker was found by text search. Some features are functionally placeholder-like despite no marker, especially import manual review and generic print report layouts.

---

## 7. Unused tables

No table is entirely unused.

Partially-used tables:

- `seed_history`: write-only; no UI or report reads it.
- `settings`: limited validation and limited set of setting keys.
- `companion_relationships`: no dedicated management UI; only edited through seed edit form.

---

## 8. Missing routes

No route is missing for the explicitly requested page list from the original requirements:

- Login: exists.
- Logout: exists.
- Dashboard: exists.
- Seed Inventory: exists.
- Add Seed: exists.
- Edit Seed: exists.
- View Seed: exists.
- Duplicate Seed: exists.
- Delete Seed: exists.
- Planting Calendar: exists.
- Companion Finder: exists.
- Import Seeds: exists.
- Export Seeds: exists.
- Print Reports: exists.
- Settings: exists.
- Manage Categories: exists.
- Manage Plant Families: exists.
- Manage Uses: exists.
- Manage Statuses: exists.

Routes that would still be useful before production:

- Dedicated seed history/audit route.
- Dedicated companion relationship management route.
- Import manual-review queue route.
- Import validation error detail route.
- Export filtered-results route that preserves inventory filters.
- Dedicated 405 method-not-allowed handling for action routes.
- Health check/install check route for DB/schema readiness.

---

## 9. Missing templates

Only three template files exist:

- `app/templates/seeds_index.php`
- `app/templates/seed_form.php`
- `app/templates/seed_detail.php`

All other pages render inline HTML inside `public/index.php` or `app/import_export.php`.

This is connected and functional, but maintainability would improve with templates for:

- Login
- Dashboard
- Calendar
- Companion Finder
- Import upload/map/summary
- Export
- Print reports
- Settings
- Manage taxonomy pages
- Error pages

---

## 10. Missing validation

### Seed form validation

Current validation checks only:

- Required `seed_number`
- Required `name`
- Month/day pair completeness
- Month in 1-12
- Day in 1-31

Missing or weak validation:

- Valid calendar dates, e.g. Feb 31 is currently accepted by simple day bounds.
- Packet year and expiration year reasonable ranges.
- Purchase date format/valid date.
- Germination min <= germination max.
- Non-negative numeric fields.
- Category/family/status IDs actually exist.
- `planting_method` constrained before DB insert, relying mostly on DB enum.
- Length checks before DB write.

### Settings validation

Missing validation:

- ZIP format.
- Frost dates in exact `MM-DD` format.
- Valid month/day combinations.
- Region length.

### Import validation

Missing or weak validation:

- No transaction around full import.
- No row-level validation equivalent to `validate_seed()`.
- No detailed error reporting for invalid rows.
- No file size limits.
- No MIME/content validation beyond file extension.
- No handling for category/family/status names from spreadsheets.
- No storage location, uses, or companion import mapping beyond columns that exist directly on `seeds`.
- Manual review does not persist a review queue.

### Management validation

Missing or weak validation:

- Empty names may hit DB constraints but are not consistently handled with user-friendly messages.
- No edit/update form for existing taxonomy rows.
- Deleting referenced taxonomy rows may throw exceptions.

---

## 11. Missing UI elements

- No sort controls despite backend sort parameters.
- No “export current filtered inventory” button that carries the current filters to `/export`.
- No seed history display.
- No dedicated companion management page.
- No reciprocal companion helper.
- No import manual review screen.
- No detailed import validation table showing row-by-row errors before import.
- No specialized print report selectors beyond the export/print generic route behavior.
- No pagination for 500+ seed entries; inventory currently loads all matching rows.
- No bulk actions.
- No image/photo support if desired for seed packets, though not explicitly required.

---

## 12. Security concerns

| Concern | Severity | Notes |
| --- | --- | --- |
| Logout via GET | Medium | `logout_action()` destroys the session for non-POST requests without CSRF. |
| Production error disclosure | High | Top-level catch renders exception messages directly to users. This is useful for development but unsafe in production. |
| No rate limiting/login throttling | Medium | Brute-force protection is absent. |
| Session cookie hardening not explicit | Medium | `session.use_strict_mode` is set, but cookie `Secure`, `HttpOnly`, and `SameSite` options are not explicitly configured in code. |
| Import upload checks are weak | Medium | File extension is checked, but size/MIME/content scanning is not robust. |
| XLSX parser XML hardening | Medium | Uses `simplexml_load_string()` on uploaded XLSX content. PHP versions usually disable external entity loading by default, but explicit hardening would be safer. |
| CDN dependency | Low/Medium | Bootstrap is loaded from CDN; production availability and CSP/SRI are not addressed. |
| Method enforcement | Low/Medium | Mutating action routes generally rely on CSRF but do not enforce POST uniformly. |
| Taxonomy delete exception exposure | Medium | Deleting referenced rows can trigger DB exceptions that are displayed by the global error handler. |

Positive security findings:

- Passwords are hashed with `password_hash()`.
- Login verifies hashes with `password_verify()`.
- Login regenerates the session ID.
- Most mutating forms include CSRF fields.
- SQL values are generally parameterized with PDO prepared statements.
- `config.php` is ignored by Git.
- Output escaping helper `e()` is used broadly in templates.

---

## 13. Areas that still need work before production use

### Highest priority

1. Add automated tests or at least integration smoke tests with MySQL/MariaDB.
2. Replace production exception display with safe error pages and server-side logging.
3. Enforce POST-only on logout, duplicate, delete, import submissions, settings saves, and management mutations.
4. Add session cookie options: `HttpOnly`, `Secure` when HTTPS, and `SameSite=Lax` or `Strict`.
5. Add login throttling or lockout.
6. Improve seed-number duplicate/duplicate-seed behavior to fully respect “never auto-modify” rule.
7. Add pagination for inventory to support 500+ seeds comfortably.

### Data quality and validation

1. Strengthen seed form validation.
2. Validate settings, especially frost dates.
3. Validate import rows before insert/update and show a row-level preview/error screen.
4. Make category/family/status/use mapping explicit during import.
5. Add transaction handling for imports.

### Feature completeness

1. Add real manual-review workflow for imports.
2. Add dedicated companion management UI.
3. Add seed history/audit UI.
4. Add edit forms for categories, plant families, uses, and statuses.
5. Add filtered export link from inventory.
6. Add sort controls and possibly saved views.
7. Add report-specific print templates.
8. Add full-year calendar view if desired.

### XLSX robustness

1. Add explicit `ZipArchive` check for XLSX export.
2. Improve XLSX import parser to handle inline strings, cell references, sparse rows, formulas, multiple sheets, and dates.
3. Consider a vetted library if robust XLSX support becomes critical. Current code does **not** use Composer dependencies.

---

## 14. Final audit conclusion

The repository contains a connected first-pass Seed Library application with all requested page routes present. Most claimed features exist in some form and are wired to routes, functions, templates, and database tables.

However, several features are only partially implemented relative to production expectations:

- Import validation/manual review is shallow.
- XLSX support is minimal and native-extension based, not robust spreadsheet handling.
- Print reports are generic.
- Management pages are add/delete only.
- Seed history is write-only.
- Inventory lacks pagination and UI sorting.
- Several security hardening items remain.

The app is a functional prototype/early V1 foundation, but it still needs hardening, stronger validation, better import/export robustness, automated DB-backed testing, and production error/security improvements before it should be considered production-ready.
