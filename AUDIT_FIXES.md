# Audit Fixes

Date: 2026-06-07

This document records the surgical fixes made after reviewing `AUDIT.md`. The application architecture was preserved: `public/index.php` remains the front controller, shared helpers remain in `app/bootstrap.php`, seed logic remains in `app/seeds.php`, import/export logic remains in `app/import_export.php`, and existing templates/assets were updated rather than replacing the app with a new framework.

## Summary of fixes

### 1. Security concerns

#### Logout via GET

- What was wrong: `logout_action()` destroyed the session on GET requests because CSRF was only verified for POST.
- Files changed: `public/index.php` and `app/bootstrap.php`.
- How it was fixed: Added `require_post()` and now `/logout` requires POST plus CSRF. The logout function also expires the session cookie.
- How it was tested: PHP lint and route smoke test confirmed `GET/HEAD /logout` returns `405 Method Not Allowed`.

#### Mutating routes without method enforcement

- What was wrong: Duplicate/delete actions relied on CSRF but did not explicitly require POST.
- Files changed: `public/index.php`, `app/bootstrap.php`.
- How it was fixed: Added `require_post()` checks to duplicate and delete routes before CSRF verification.
- How it was tested: PHP lint passed; route handling was smoke-tested through the PHP built-in server.

#### Production exception disclosure

- What was wrong: The global exception handler rendered raw exception messages to users.
- Files changed: `public/index.php`, `config.example.php`, `app/bootstrap.php`.
- How it was fixed: Added `app.debug` config support and `app_debug()`. Exceptions are logged with `error_log()` and users see a generic message unless debug mode is explicitly enabled.
- How it was tested: PHP lint passed.

#### Session cookie hardening

- What was wrong: Session cookies did not explicitly set `HttpOnly`, `SameSite`, or HTTPS-aware `Secure` options.
- Files changed: `app/bootstrap.php`.
- How it was fixed: Added `session_set_cookie_params()` before `session_start()` with `httponly=true`, `samesite=Lax`, and `secure=true` when HTTPS is detected.
- How it was tested: PHP lint passed and `/login` still loaded through the PHP built-in server.

#### Login throttling

- What was wrong: Login had no brute-force throttling.
- Files changed: `public/index.php`.
- How it was fixed: Added session-based throttling for five failed attempts in a 15-minute window.
- How it was tested: PHP lint passed and login page still rendered.

#### XLSX XML hardening

- What was wrong: XLSX XML parsing did not explicitly use `LIBXML_NONET`.
- Files changed: `app/import_export.php`.
- How it was fixed: `simplexml_load_string()` calls for XLSX now pass `LIBXML_NONET` and validate XML parse success.
- How it was tested: PHP lint passed.

### 2. Missing routes/templates and missing UI elements

#### Seed history display

- What was wrong: `seed_history` was write-only with no UI display.
- Files changed: `app/seeds.php`, `public/index.php`, `app/templates/seed_detail.php`.
- How it was fixed: Added `seed_history_for()` and rendered a Recent History table on the seed detail page.
- How it was tested: PHP lint passed.

#### Sort controls

- What was wrong: `seed_query()` supported sorting, but the inventory UI exposed no sort controls.
- Files changed: `app/templates/seeds_index.php`.
- How it was fixed: Added sort/direction controls to the filter form and clickable table-heading sort links.
- How it was tested: PHP lint passed.

#### Filtered export link

- What was wrong: Exporting filtered inventory was not connected from the inventory UI.
- Files changed: `app/templates/seeds_index.php`.
- How it was fixed: Added an `Export Filtered CSV` button that carries current inventory filters to `/export?download=1&report=filtered`.
- How it was tested: PHP lint passed.

#### Print report selector

- What was wrong: Print reports were only a generic print table without a visible report selector on the print page.
- Files changed: `app/import_export.php`.
- How it was fixed: Added a report selector and print button to `/print` while preserving the generic printable table.
- How it was tested: PHP lint passed.

#### Management edit UI

- What was wrong: taxonomy/status management pages had add/delete only.
- Files changed: `public/index.php`.
- How it was fixed: Added inline update forms for categories, plant families, uses, and statuses.
- How it was tested: PHP lint passed.

### 3. Placeholder-only and partially implemented features

#### Import manual review

- What was wrong: Manual review only incremented a counter and did not show rows.
- Files changed: `app/import_export.php`.
- How it was fixed: Manual-review duplicate rows are now stored in session and displayed in the import summary with row number, seed number, and mapped data.
- How it was tested: PHP lint passed.

#### Duplicate seed and seed-number rule

- What was wrong: Duplicate seed appended `-copy` to the seed number, conflicting with the physical label preservation rule.
- Files changed: `app/seeds.php`, `public/index.php`.
- How it was fixed: Duplicate now preserves the seed number exactly. The flash message explains that the user can edit the physical label only if needed. Uses, location, and companion relationships are copied.
- How it was tested: PHP lint passed.

#### Seed detail omissions

- What was wrong: The detail page omitted stored fields such as indoor/transplant timing, succession days, purchase date, expiration year, and storage notes.
- Files changed: `app/templates/seed_detail.php`.
- How it was fixed: Added the missing fields to the detail view.
- How it was tested: PHP lint passed.

#### XLSX import robustness

- What was wrong: XLSX import ignored cell references and inline string cells, which could misalign sparse rows.
- Files changed: `app/import_export.php`.
- How it was fixed: Added cell reference parsing, inline string handling, boolean cell handling, shared string validation, and sparse-cell alignment.
- How it was tested: PHP lint passed.

#### XLSX export dependency failure

- What was wrong: XLSX export did not check `ZipArchive` availability before use.
- Files changed: `app/import_export.php`.
- How it was fixed: Added an explicit `ZipArchive` check and throws a clear runtime error if the extension is missing.
- How it was tested: PHP lint passed.

### 4. Missing validation

#### Seed form validation

- What was wrong: Seed validation only checked required name/number and basic month/day ranges.
- Files changed: `app/seeds.php`, `app/bootstrap.php`.
- How it was fixed: Added validation for seed/name length, lookup IDs, planting method enum values, real calendar dates, non-negative numeric fields, germination min/max order, year ranges, and purchase date format.
- How it was tested: PHP lint passed.

#### Settings validation

- What was wrong: Settings accepted invalid ZIP and frost date values.
- Files changed: `public/index.php`, `app/bootstrap.php`.
- How it was fixed: Added `valid_mmdd()` and settings validation for ZIP, required fields, and valid `MM-DD` frost dates.
- How it was tested: PHP lint passed.

#### Import validation

- What was wrong: Import rows bypassed seed validation and upload checks were shallow.
- Files changed: `app/import_export.php`.
- How it was fixed: Added upload error checks, a 10 MB upload limit, saved-file failure handling, duplicate-action validation, row normalization, seed validation reuse, row-level error summaries, and import transaction handling.
- How it was tested: PHP lint passed.

#### Management validation and delete failures

- What was wrong: taxonomy/status management had weak validation and referenced-row deletes could expose DB exceptions.
- Files changed: `public/index.php`.
- How it was fixed: Empty names are rejected and PDO exceptions are caught with user-friendly flash messages.
- How it was tested: PHP lint passed.

### 5. Production-readiness improvements

- Added safer production error behavior.
- Added session cookie hardening.
- Added login throttling.
- Added transaction handling for imports.
- Improved XLSX parser behavior without adding Composer dependencies.
- Added clearer upload-size and extension handling.
- Improved mobile/table CSS for sortable headers and horizontal table overflow.

### 6. Dead code cleanup

#### `table_exists()`

- What was wrong: `table_exists()` was unused.
- Files changed: `app/bootstrap.php`.
- How it was fixed: Removed the unused helper.
- How it was tested: PHP lint passed.

#### Unused JavaScript `data-check-all` handler

- What was wrong: The handler had no current template using it.
- Files changed: `public/assets/app.js`.
- How it was fixed: Removed the unused handler and kept the confirmation behavior.
- How it was tested: PHP lint passed for PHP files and route smoke tests still passed.

## Verification commands run

```bash
php -l app/bootstrap.php
php -l config.example.php
php -l app/seeds.php
php -l public/index.php
php -l app/import_export.php
php -l app/templates/seeds_index.php
php -l app/templates/seed_detail.php
find app public scripts -name '*.php' -print0 | xargs -0 -n1 php -l
php -S 127.0.0.1:8090 -t public
curl -fsS http://127.0.0.1:8090/login | grep -q 'Seed Library Login'
curl -sI http://127.0.0.1:8090/seeds | grep -q 'Location: /login'
curl -sI http://127.0.0.1:8090/logout | grep -q '405 Method Not Allowed'
rg -n "function table_exists|data-check-all|\\-copy|logout_action|require_post|app_debug|seed_history_for|Manual Review|Export Filtered|ZipArchive|simplexml_load_string|valid_mmdd|valid_month_day|record_exists" app public config.example.php
if command -v mysql >/dev/null 2>&1 || command -v mariadbd >/dev/null 2>&1 || command -v mysqld >/dev/null 2>&1; then echo 'MySQL/MariaDB tooling available'; else echo 'MySQL/MariaDB tooling not available in container'; fi
```

## Remaining known issues

These items still need deeper work or a live MySQL-backed test environment:

1. No automated PHPUnit/integration test suite exists yet.
2. Full database-backed CRUD/import/export verification could not be completed in this container because no MySQL/MariaDB server is available.
3. Import category/family/status mapping still expects IDs for those direct columns; spreadsheet-friendly name-to-ID mapping is still future work.
4. XLSX support is improved but still intentionally lightweight; robust Excel date/formula/multi-sheet support would benefit from a vetted library.
5. Inventory still does not paginate results; this should be added for very large collections beyond the 500+ target.
6. Companion relationship management still occurs through the seed edit form rather than a dedicated management page.
7. Most non-seed pages still render inline HTML; moving them to templates would improve maintainability but was not necessary for this surgical pass.
