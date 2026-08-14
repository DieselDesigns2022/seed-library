# Seed Library Testing Guide

This guide walks through testing Seed Library from a clean checkout through the core Version 1.0 workflows.

## Phase 6 automated and live verification boundary

Run the complete source suite from the repository root:

```bash
find app public scripts tests -name '*.php' -print0 | xargs -0 -n1 php -l
for t in tests/*.php; do php "$t"; done
git diff --check
```

The Phase 6 static test checks response headers, the explicit Secure-cookie production override, enforcement of private import permissions and superseded-upload cleanup, login labels, skip navigation, navigation naming, and visible keyboard focus. It does not substitute for an authenticated browser or database.

On the production-like VPS, test at narrow phone and desktop widths with keyboard-only navigation. Exercise Login, Dashboard, Inventory filters/cards, Add/Edit/Detail (including **Save and Add Another**), July Calendar, Onion Companion Finder, management pages, import review, exports, print, and owner backup/restore. With a disposable copy of representative data, capture `EXPLAIN` plans and timings for Inventory/global search/filter/count/calendar/companion queries at 200+ and 500+ records. Confirm HTTPS sets the session cookie `Secure`, all writable paths are outside `public/`, import files are mode `0600`, errors expose no internal paths or SQL, and an approximately 100-row import can be corrected before confirmation. These browser, authenticated MySQL, scale, and VPS checks are **pending live testing** until recorded in the deployment log; never alter owner seed data to manufacture them.

The project is a framework-light PHP/MySQL application. It does **not** require Composer for the current codebase. XLSX import/export uses native PHP extensions only:

- `ZipArchive` from the PHP `zip` extension.
- `SimpleXML` from the PHP XML extension.

If XLSX import/export fails, install/enable PHP `zip` and XML extensions for your PHP version. You do not need `composer install` unless future code adds a `composer.json` dependency.

---

## 1. Prerequisites

### Required software

- PHP 8.3 or newer.
- MySQL 8 or MariaDB 10.6+.
- PHP extensions:
  - `pdo_mysql`
  - `zip` for XLSX import/export
  - `SimpleXML` / XML for XLSX import
- A shell terminal.

### Check PHP version and extensions

Run from the repository root:

```bash
php -v
php -m | sort | grep -E 'PDO|pdo_mysql|zip|SimpleXML|xml'
```

Expected: PHP 8.3+ and output containing `PDO`, `pdo_mysql`, `zip`, `SimpleXML`, and XML-related modules.

### Install missing packages on Ubuntu/Debian VPS

Replace `8.3` with your installed PHP version if needed:

```bash
sudo apt update
sudo apt install -y php8.3-cli php8.3-mysql php8.3-zip php8.3-xml mariadb-server mariadb-client
sudo systemctl enable --now mariadb
php -m | sort | grep -E 'PDO|pdo_mysql|zip|SimpleXML|xml'
```

### Install missing packages on RHEL/Rocky/AlmaLinux-style VPS

Package names vary by repository, but a typical setup is:

```bash
sudo dnf install -y php php-cli php-mysqlnd php-zip php-xml mariadb-server mariadb
sudo systemctl enable --now mariadb
php -m | sort | grep -E 'PDO|pdo_mysql|zip|SimpleXML|xml'
```

---

## 2. Start from a clean repository checkout

```bash
cd /path/to/seed-library
git status --short
```

Expected: no uncommitted changes before you begin testing.

If testing in this container/repository path:

```bash
cd /workspace/seed-library
git status --short
```

---

## 3. Set up the database locally or on a VPS

### Option A: Create a local test database

Log in to MySQL/MariaDB as root:

```bash
sudo mysql
```

Create a database and test user:

```sql
CREATE DATABASE seed_library_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'seed_library_test'@'localhost' IDENTIFIED BY 'ChangeThisLocalPassword123!';
GRANT ALL PRIVILEGES ON seed_library_test.* TO 'seed_library_test'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### Option B: Create a VPS database

On the VPS, log in to MySQL/MariaDB:

```bash
sudo mysql
```

Create a production-style database and user:

```sql
CREATE DATABASE seed_library CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'seed_library_user'@'localhost' IDENTIFIED BY 'ReplaceWithAStrongUniquePassword!';
GRANT ALL PRIVILEGES ON seed_library.* TO 'seed_library_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### Verify database login

For local test database:

```bash
mysql -u seed_library_test -p seed_library_test -e "SELECT DATABASE();"
```

For VPS production-style database:

```bash
mysql -u seed_library_user -p seed_library -e "SELECT DATABASE();"
```

Expected: the command prints the selected database name.

---

## 4. Copy `config.example.php` to `config.php`

From the repository root:

```bash
cp config.example.php config.php
```

Confirm the file exists:

```bash
test -f config.php && echo "config.php exists"
```

`config.php` is intentionally ignored by Git and must not be committed.

---

## 5. Edit values in `config.php`

Open `config.php` in your editor:

```bash
nano config.php
```

Edit these values:

```php
'app' => [
    'name' => 'Seed Library',
    'base_url' => '',
    'session_name' => 'seed_library_session',
    'timezone' => 'America/Detroit',
],
'db' => [
    'host' => '127.0.0.1',
    'port' => 3306,
    'database' => 'seed_library_test',
    'username' => 'seed_library_test',
    'password' => 'ChangeThisLocalPassword123!',
    'charset' => 'utf8mb4',
],
```

### Local development values

Use these values if your database was created with the local test commands above:

- `app.base_url`: leave as `''` for the PHP built-in server.
- `app.timezone`: `America/Detroit` is appropriate for Southeast Michigan testing.
- `db.host`: `127.0.0.1`
- `db.port`: `3306`
- `db.database`: `seed_library_test`
- `db.username`: `seed_library_test`
- `db.password`: `ChangeThisLocalPassword123!`
- `db.charset`: `utf8mb4`

### VPS values

Use these values if your database was created with the VPS commands above:

- `app.base_url`: set to your deployed base URL if the app is not served from the domain root; otherwise leave `''`.
- `app.timezone`: usually `America/Detroit` for this project.
- `db.host`: usually `127.0.0.1` or `localhost`.
- `db.port`: usually `3306`.
- `db.database`: `seed_library`.
- `db.username`: `seed_library_user`.
- `db.password`: your strong database password.
- `db.charset`: `utf8mb4`.

### Verify config syntax

```bash
php -l config.php
```

Expected:

```text
No syntax errors detected in config.php
```

---

## 6. Import `database/schema.sql`

### Local test database

```bash
mysql -u seed_library_test -p seed_library_test < database/schema.sql
```

### VPS production-style database

```bash
mysql -u seed_library_user -p seed_library < database/schema.sql
```

### Verify required tables

Local test database:

```bash
mysql -u seed_library_test -p seed_library_test -e "SHOW TABLES;"
```

VPS database:

```bash
mysql -u seed_library_user -p seed_library -e "SHOW TABLES;"
```

Expected tables:

```text
categories
companion_relationships
plant_families
seed_history
seed_locations
seed_uses
seeds
settings
statuses
uses
users
```

### Verify default settings

Local test database:

```bash
mysql -u seed_library_test -p seed_library_test -e "SELECT * FROM settings ORDER BY setting_key;"
```

Expected default values include:

- `zone = 6B`
- `zip = 48239`
- `region = Southeast Michigan`
- `average_last_frost = 05-05`
- `average_first_frost = 10-15`

---

## 7. Create the first admin user

From the repository root, run:

```bash
php scripts/create_admin.php "Test Admin" test-admin@example.com 'StrongTestPassword123!'
```

Expected output:

```text
Admin user ready: test-admin@example.com
```

Verify the admin user exists:

```bash
mysql -u seed_library_test -p seed_library_test -e "SELECT id, name, email, created_at FROM users;"
```

For VPS database, replace the database credentials:

```bash
mysql -u seed_library_user -p seed_library -e "SELECT id, name, email, created_at FROM users;"
```

---

## 8. Start the PHP server locally

From the repository root:

```bash
php -S 127.0.0.1:8080 -t public
```

Keep this terminal open. You should see output similar to:

```text
PHP 8.x Development Server (http://127.0.0.1:8080) started
```

Open the app in your browser:

```text
http://127.0.0.1:8080/login
```

### Optional route smoke test from another terminal

```bash
curl -I http://127.0.0.1:8080/seeds
```

Expected while logged out: a `302` redirect to `/login`.

---

## 9. Log in

1. Visit `http://127.0.0.1:8080/login`.
2. Enter:
   - Email: `test-admin@example.com`
   - Password: `StrongTestPassword123!`
3. Click **Login**.

Expected:

- You are redirected to the dashboard.
- You see a success flash message.
- Navigation links appear for Inventory, Calendar, Companions, Tools, and Manage.

### Test bad login protection

1. Log out.
2. Try logging in with `test-admin@example.com` and a wrong password.

Expected:

- Login fails.
- The app displays `Invalid email or password.`

---

## 10. Test adding a seed

1. Log in.
2. Click **Add Seed** from the dashboard or inventory.
3. Fill out the form with this test seed:

   | Field | Value |
   | --- | --- |
   | Seed Number | `TOM-001-A!` |
   | Seed Name | `Tomato` |
   | Variety | `Black Cherry` |
   | Category | `Vegetable` |
   | Plant Family | `Solanaceae` |
   | Seed Status | `Active` |
   | Plant Type | `Fruit vegetable` |
   | Planting Method | `Start Indoors` |
   | Start Planting Date | `April 15` |
   | Last Recommended Planting Date | `July 20` |
   | Germination Days — Minimum | `5` |
   | Germination Days — Maximum | `10` |
   | Harvest/Maturity Days | `75` |
   | Sun Requirements | `Full sun` |
   | Water Needs | `Even moisture` |
   | Soil Preference | `Rich, well-drained` |
   | Plant Spacing | `24-36 in` |
   | Planting Depth | `1/4 in` |
   | Plant Height | `5-7 ft` |
   | Seed Source/Brand | `Test Seed Co.` |
   | Packet Year | `2026` |
   | Quantity Notes | `25 seeds` |
   | Storage Box | `Box A` |
   | Container | `Tin 1` |
   | Envelope | `Envelope 3` |
   | Row | `R1` |
   | Slot | `S1` |
   | Notes | `Test tomato seed.` |

4. Check these boxes:
   - Container Friendly
   - Pollinator Friendly
   - Trellis/Support Needed
5. Select uses such as:
   - Culinary
   - Seed Saving
6. Click **Save**.

Expected:

- You are redirected to the seed detail page.
- The seed number displays exactly as `TOM-001-A!`.
- Start Planting Date displays as `Apr 15`.
- Last Recommended Planting Date displays as `Jul 20`.
- Storage, uses, flags, and notes are visible.

### Verify directly in database

```bash
mysql -u seed_library_test -p seed_library_test -e "SELECT seed_number, name, variety, packet_year FROM seeds WHERE seed_number = 'TOM-001-A!';"
```

Expected: one row with `TOM-001-A!` exactly as entered.

---

## 11. Test editing a seed

1. Open the `Tomato / Black Cherry` seed detail page.
2. Click **Edit**.
3. Change:
   - Quantity Notes: `20 seeds`
   - Slot: `S2`
   - Notes: `Edited test tomato seed.`
4. Do **not** change the seed number.
5. Click **Save**.

Expected:

- You return to the seed detail page.
- Quantity Notes displays `20 seeds`.
- Storage slot now displays `S2`.
- Notes display the edited text.
- Seed number remains exactly `TOM-001-A!`.

Verify in database:

```bash
mysql -u seed_library_test -p seed_library_test -e "SELECT s.seed_number, s.quantity, l.slot FROM seeds s LEFT JOIN seed_locations l ON l.seed_id = s.id WHERE s.seed_number = 'TOM-001-A!';"
```

---

## 12. Test all Add/Edit form actions

These browser/database checks remain required on the isolated test environment. Use a temporary record so each action can be verified independently.

### Save

1. Open Add Seed, complete all six required fields, and click **Save**.
2. Expected: the seed is saved and the browser redirects to that seed's Detail page.

### Save and Add Another

1. Open Add Seed and enter a distinct temporary seed, including obvious text in optional fields.
2. Click **Save and Add Another**.
3. Expected: the current seed is saved and the browser redirects to a clean Add Seed form.
4. Confirm the previously entered Seed Number, Seed Name, optional text, Plantable Months, Uses, storage, and companion values are not populated as a new record.

### Save and Duplicate

1. Open Add or Edit, make a recognizable change, and click **Save and Duplicate**.
2. Expected: the source is saved first; a duplicate is created with the exact same Seed Number; and the browser redirects to the duplicate Edit page.
3. Confirm the duplicate Seed Name is the saved source name followed by ` (Copy)` and that copied location, Uses, and companions are present.
4. In a controlled failure-injection test, make duplicate creation fail after the source save. Expected: the source remains saved, no partial duplicate remains, the owner is redirected to the source Detail page, and the warning says the seed was saved but its duplicate could not be created.

### Cancel

1. On Add Seed, enter data without saving and click **Cancel**. Expected: the browser returns to Seed Inventory and no seed is created.
2. On Edit Seed, change data without saving and click **Cancel**. Expected: the browser returns to the existing Seed Detail page and the submitted changes are not saved.

---

## 13. Test duplicating a seed

1. Open the `Tomato / Black Cherry` seed detail page.
2. Click **Duplicate**.
3. Expected: you are redirected to an edit form for the copied seed.
4. Confirm the copy retains the exact Seed Number `TOM-001-A!`.
5. Change the copy to:
   - Seed Number: `TOM-002-B#`
   - Variety: `Black Cherry Copy Test`
6. Click **Save**.

Expected:

- The duplicated seed saves successfully.
- The original `TOM-001-A!` still exists.
- The new `TOM-002-B#` exists.
- The duplicate initially retained `TOM-001-A!`; only the owner’s manual edit changed it.

Verify in database:

```bash
mysql -u seed_library_test -p seed_library_test -e "SELECT seed_number, name, variety FROM seeds WHERE seed_number LIKE 'TOM-%' ORDER BY seed_number;"
```

---

## 14. Test deleting a seed

Use the duplicated seed for this test so the original remains available.

1. Open the `TOM-002-B#` seed detail page.
2. Click **Delete**.
3. Confirm the browser confirmation prompt.

Expected:

- You are redirected to the inventory page.
- `TOM-002-B#` no longer appears.
- `TOM-001-A!` still appears.

Verify in database:

```bash
mysql -u seed_library_test -p seed_library_test -e "SELECT seed_number, name FROM seeds WHERE seed_number IN ('TOM-001-A!', 'TOM-002-B#');"
```

Expected: only `TOM-001-A!` is returned.

---

## 15. Test search and filters

First add a second seed to make filters meaningful.

1. Go to **Add Seed**.
2. Add this seed:

   | Field | Value |
   | --- | --- |
   | Seed Number | `BAS-001` |
   | Seed Name | `Basil` |
   | Variety | `Genovese` |
   | Category | `Herb` |
   | Plant Family | `Lamiaceae` |
   | Seed Status | `Active` |
   | Plant Type | `Annual herb` |
   | Planting Method | `Direct Sow or Transplant` |
   | Start Planting Date | `May 10` |
   | Last Recommended Planting Date | `August 15` |
   | Packet Year | `2026` |
   | Storage Box | `Box B` |

3. Check:
   - Container Friendly
   - Pollinator Friendly
4. Save.

Now test filters from **Seed Inventory**:

### Search filter

1. Search for `Tomato`.
2. Expected: `Tomato / Black Cherry` appears; `Basil / Genovese` does not.

### Category filter

1. Clear search.
2. Set Category to `Herb`.
3. Expected: Basil appears; Tomato does not.

### Plant family filter

1. Set Plant Family to `Solanaceae`.
2. Expected: Tomato appears.

### Plantable month filter

1. Set Plant Month to `June`.
2. Expected: Tomato and Basil appear because their windows include June.
3. Set Plant Month to `September`.
4. Expected: neither test seed appears.

### Boolean filters

1. Set Trellis to `Yes`.
2. Expected: Tomato appears; Basil does not.
3. Set Container to `Yes`.
4. Expected: both Tomato and Basil can appear.

### Location/source/year filters

1. Filter Seed Location by `Box A`.
2. Expected: Tomato appears.
3. Filter Packet Year by `2026`.
4. Expected: both test seeds can appear.

---

## 16. Test the planting calendar

1. Go to **Planting Calendar**.
2. Select `April`.
3. Expected: Tomato appears because its planting window starts Apr 15.
4. Select `May`.
5. Expected: Tomato and Basil appear.
6. Select `June`.
7. Expected: Tomato and Basil appear.
8. Select `July`.
9. Expected: Tomato and Basil appear.
10. Select `August`.
11. Expected: Basil appears; Tomato does not if Tomato ends Jul 20.
12. Select `September`.
13. Expected: neither test seed appears.

The calendar is year-independent. It uses stored month/day windows and displays dates without a year.

---

## 17. Test the companion finder

Create a companion relationship:

1. Open the Tomato seed detail page.
2. Click **Edit**.
3. In **Companion Planting**, choose:
   - Companion: Basil
   - Type: `Good Companion`
   - Notes: `Classic tomato companion.`
4. Click **Save**.

Now test the finder:

1. Go to **Companions**.
2. Search for `Tomato`.
3. Expected: a row shows Tomato → Good Companion → Basil.
4. Search for `Basil`.
5. Expected: the same relationship appears because the finder searches both plant and companion names.
6. Set Relationship to `Good Companion`.
7. Expected: the Tomato/Basil relationship remains.
8. Set Relationship to `Avoid`.
9. Expected: the Tomato/Basil relationship is hidden.

Verify in database:

```bash
mysql -u seed_library_test -p seed_library_test -e "SELECT cr.relationship_type, s.name AS seed, cs.name AS companion, cr.notes FROM companion_relationships cr JOIN seeds s ON s.id = cr.seed_id JOIN seeds cs ON cs.id = cr.companion_seed_id;"
```

---

## 18. Test CSV import

Create a test CSV file:

```bash
cat > /tmp/seed-import-test.csv <<'CSV'
seed_number,name,variety,category,plant_type,planting_method,planting_start_month,planting_start_day,planting_end_month,planting_end_day,packet_year,seed_source,container_friendly,pollinator_friendly,notes
LET-001,Lettuce,Buttercrunch,Vegetable,Leaf vegetable,Direct Sow,3,20,5,30,2026,CSV Test Source,1,0,Imported from CSV
RAD-001,Radish,French Breakfast,Vegetable,Root vegetable,Direct Sow,4,1,6,15,2026,CSV Test Source,1,0,Imported from CSV
CSV
```

Import through the UI:

1. Go to **Tools → Import**.
2. Upload `/tmp/seed-import-test.csv`.
3. On the mapping screen, confirm these columns map to matching seed fields:
   - `seed_number`
   - `name`
   - `variety`
   - `category` → `category_id`
   - `plant_type`
   - `planting_method`
   - `planting_start_month`
   - `planting_start_day`
   - `planting_end_month`
   - `planting_end_day`
   - `packet_year`
   - `seed_source`
   - `container_friendly`
   - `pollinator_friendly`
   - `notes`
4. Choose duplicate handling: `Skip`.
5. Click **Validate & Import**.

Expected:

- Import summary shows `imported` count of at least `2`.
- Inventory search for `LET-001` finds Lettuce.
- Inventory search for `RAD-001` finds Radish.

Verify in database:

```bash
mysql -u seed_library_test -p seed_library_test -e "SELECT seed_number, name, variety FROM seeds WHERE seed_number IN ('LET-001','RAD-001') ORDER BY seed_number;"
```

### Test CSV duplicate handling: Skip

1. Import the same `/tmp/seed-import-test.csv` again.
2. Select duplicate handling `Skip`.

Expected: summary shows skipped duplicates rather than creating updates.

### Test CSV duplicate handling: Update Existing

1. Create an updated CSV:

```bash
cat > /tmp/seed-import-update.csv <<'CSV'
seed_number,name,variety,category,planting_method,planting_start_month,planting_start_day,planting_end_month,planting_end_day,notes
LET-001,Lettuce,Buttercrunch,Vegetable,Direct Sow,3,20,5,30,Updated via duplicate import
CSV
```

2. Import it.
3. Map the columns.
4. Select duplicate handling `Update Existing`.

Expected: Lettuce notes update.

Verify:

```bash
mysql -u seed_library_test -p seed_library_test -e "SELECT seed_number, notes FROM seeds WHERE seed_number = 'LET-001';"
```

---

## 19. Test XLSX import

XLSX import does not require Composer dependencies in the current implementation. It requires PHP `zip` and `SimpleXML`/XML extensions.

### Confirm XLSX extensions

```bash
php -r "echo class_exists('ZipArchive') ? 'ZipArchive OK'.PHP_EOL : 'ZipArchive MISSING'.PHP_EOL; echo function_exists('simplexml_load_string') ? 'SimpleXML OK'.PHP_EOL : 'SimpleXML MISSING'.PHP_EOL;"
```

Expected:

```text
ZipArchive OK
SimpleXML OK
```

### Create a small XLSX test file using Python

If Python `openpyxl` is available:

```bash
python3 - <<'PY'
from openpyxl import Workbook
wb = Workbook()
ws = wb.active
ws.title = 'Seeds'
ws.append(['seed_number','name','variety','category','plant_type','planting_method','planting_start_month','planting_start_day','planting_end_month','planting_end_day','packet_year','seed_source','notes'])
ws.append(['PEA-001','Pea','Sugar Snap','Vegetable','Pod vegetable','Direct Sow',3,15,5,30,2026,'XLSX Test Source','Imported from XLSX'])
ws.append(['DIL-001','Dill','Bouquet','Herb','Annual herb','Direct Sow',4,15,7,15,2026,'XLSX Test Source','Imported from XLSX'])
wb.save('/tmp/seed-import-test.xlsx')
print('/tmp/seed-import-test.xlsx')
PY
```

If `openpyxl` is missing, install it in a temporary user environment or create the XLSX manually in LibreOffice/Excel with the same headers:

```bash
python3 -m pip install --user openpyxl
```

### Import through the UI

1. Go to **Tools → Import**.
2. Upload `/tmp/seed-import-test.xlsx`.
3. Confirm the column mapping.
4. Choose duplicate handling: `Skip`.
5. Click **Validate & Import**.

Expected:

- Import summary shows `imported` count of at least `2`.
- Inventory search for `PEA-001` finds Pea.
- Inventory search for `DIL-001` finds Dill.

Verify:

```bash
mysql -u seed_library_test -p seed_library_test -e "SELECT seed_number, name, variety FROM seeds WHERE seed_number IN ('PEA-001','DIL-001') ORDER BY seed_number;"
```

---

## 20. Test CSV export

1. Go to **Tools → Export**.
2. Choose `All Seeds`.
3. Choose format `CSV`.
4. Click **Download**.

Expected:

- Browser downloads `seed-library-export.csv`.
- The CSV contains test seed rows such as `TOM-001-A!`, `BAS-001`, `LET-001`, and `RAD-001`.

### Test with curl after logging in manually is difficult

Exports are protected by session authentication, so the easiest test is through the browser. To inspect the downloaded file in terminal, move it to `/tmp` and run:

```bash
head -20 /tmp/seed-library-export.csv
```

---

## 21. Test XLSX export

XLSX export does not require Composer dependencies in the current implementation. It requires PHP `zip`.

### Confirm ZipArchive

```bash
php -r "echo class_exists('ZipArchive') ? 'ZipArchive OK'.PHP_EOL : 'ZipArchive MISSING'.PHP_EOL;"
```

1. Go to **Tools → Export**.
2. Choose `All Seeds`.
3. Choose format `XLSX`.
4. Click **Download**.

Expected:

- Browser downloads `seed-library-export.xlsx`.
- The file opens in LibreOffice, Excel, or Numbers.
- The workbook includes seed rows.

### Verify downloaded XLSX from terminal

Move the downloaded file to `/tmp/seed-library-export.xlsx`, then run:

```bash
python3 - <<'PY'
from zipfile import ZipFile
path = '/tmp/seed-library-export.xlsx'
with ZipFile(path) as z:
    names = set(z.namelist())
    required = {'[Content_Types].xml', 'xl/workbook.xml', 'xl/worksheets/sheet1.xml'}
    missing = required - names
    if missing:
        raise SystemExit(f'Missing XLSX parts: {missing}')
    print('XLSX package structure OK')
PY
```

---

## 22. Test print reports

1. Go to **Tools → Print Reports** or visit:

```text
http://127.0.0.1:8080/print
```

2. Confirm a print-friendly inventory table appears.
3. Use the browser print command:
   - Windows/Linux: `Ctrl+P`
   - macOS: `Cmd+P`
4. Confirm navigation, buttons, forms, and alerts are hidden in the print preview.
5. Test a calendar print route:

```text
http://127.0.0.1:8080/print?report=calendar
```

6. Test a companion guide print route:

```text
http://127.0.0.1:8080/print?report=companions
```

Expected:

- Reports are readable.
- Tables are visible.
- Browser print preview excludes interactive controls.

---

## 23. Test settings and management pages

### Settings

1. Go to **Manage → Settings**.
2. Confirm defaults:
   - Zone: `6B`
   - ZIP: `48239`
   - Region: `Southeast Michigan`
   - Average Last Frost: `05-05`
   - Average First Frost: `10-15`
3. Change Region to `Southeast Michigan Test`.
4. Save.
5. Change it back to `Southeast Michigan`.
6. Save.

### Categories, plant families, uses, statuses

1. Go to **Manage → Categories**.
2. Add `Test Category`.
3. Confirm it appears in the list.
4. Delete `Test Category`.
5. Repeat similarly for:
   - **Plant Families**
   - **Uses**
   - **Statuses**

Expected: each management page can add and delete records.

---

## 24. Full regression checklist

Use this checklist before considering a deployment ready:

- [ ] Login accepts valid admin credentials.
- [ ] Login rejects invalid credentials.
- [ ] Logout ends the session.
- [ ] Protected pages redirect logged-out users to `/login`.
- [ ] Dashboard metrics load.
- [ ] Add seed works and preserves exact seed number.
- [ ] Edit seed works without changing seed number unless manually edited.
- [ ] Duplicate seed works.
- [ ] Delete seed works.
- [ ] Search works.
- [ ] Multiple filters work together.
- [ ] Planting calendar returns seeds for overlapping months.
- [ ] Companion finder returns structured relationships.
- [ ] CSV import works.
- [ ] XLSX import works.
- [ ] CSV export downloads.
- [ ] XLSX export downloads and opens.
- [ ] Print reports render cleanly.
- [ ] Settings save.
- [ ] Category/family/use/status management pages save and delete.

---

## 25. Common errors and how to fix them

### `SQLSTATE[HY000] [1045] Access denied for user`

Cause: database username or password in `config.php` is wrong.

Fix:

```bash
nano config.php
mysql -u seed_library_test -p seed_library_test -e "SELECT 1;"
```

Make the `db.username`, `db.password`, and `db.database` values match a working MySQL login.

### `SQLSTATE[HY000] [1049] Unknown database`

Cause: the database named in `config.php` does not exist.

Fix for local test database:

```bash
sudo mysql -e "CREATE DATABASE seed_library_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u seed_library_test -p seed_library_test < database/schema.sql
```

### `Base table or view not found`

Cause: `database/schema.sql` was not imported into the configured database.

Fix:

```bash
mysql -u seed_library_test -p seed_library_test < database/schema.sql
mysql -u seed_library_test -p seed_library_test -e "SHOW TABLES;"
```

### `Invalid CSRF token`

Cause: stale form, expired session, or browser used an old page after logout/login.

Fix:

1. Refresh the page.
2. Submit the form again.
3. If it persists, log out and log back in.

### `XLSX import requires PHP ZipArchive`

Cause: PHP `zip` extension is missing.

Fix on Ubuntu/Debian, replacing `8.3` if needed:

```bash
sudo apt install -y php8.3-zip
sudo systemctl restart apache2 || true
php -r "echo class_exists('ZipArchive') ? 'ZipArchive OK'.PHP_EOL : 'ZipArchive MISSING'.PHP_EOL;"
```

Fix on RHEL/Rocky/AlmaLinux:

```bash
sudo dnf install -y php-zip
sudo systemctl restart httpd || true
php -r "echo class_exists('ZipArchive') ? 'ZipArchive OK'.PHP_EOL : 'ZipArchive MISSING'.PHP_EOL;"
```

### `Call to undefined function simplexml_load_string()`

Cause: PHP XML/SimpleXML extension is missing.

Fix on Ubuntu/Debian:

```bash
sudo apt install -y php8.3-xml
sudo systemctl restart apache2 || true
php -r "echo function_exists('simplexml_load_string') ? 'SimpleXML OK'.PHP_EOL : 'SimpleXML MISSING'.PHP_EOL;"
```

Fix on RHEL/Rocky/AlmaLinux:

```bash
sudo dnf install -y php-xml
sudo systemctl restart httpd || true
php -r "echo function_exists('simplexml_load_string') ? 'SimpleXML OK'.PHP_EOL : 'SimpleXML MISSING'.PHP_EOL;"
```

### `No such file or directory: config.php`

Cause: `config.php` has not been created.

Fix:

```bash
cp config.example.php config.php
nano config.php
```

### Browser shows repository files instead of app

Cause: web server document root points to the repository root instead of `public/`.

Fix: configure Apache/Nginx document root to the `public` directory.

Local test command should be:

```bash
php -S 127.0.0.1:8080 -t public
```

### CSS/JS not loading

Cause: incorrect `base_url` or web server path configuration.

Fix for local testing: keep `app.base_url` as an empty string:

```php
'base_url' => '',
```

If deploying in a subdirectory, set `base_url` to that subdirectory URL and retest asset paths.

### Admin creation script says password is too short

Cause: password is fewer than 12 characters.

Fix:

```bash
php scripts/create_admin.php "Test Admin" test-admin@example.com 'StrongTestPassword123!'
```

### Import uploads fail due to permissions

Cause: no configured, project, or system-temporary import directory is writable (or export storage is not writable for an export operation).

For imports, optionally configure an absolute writable `app.imports_path`; otherwise make project storage writable. The importer will also try `sys_get_temp_dir()/seed-library-imports`. Fix project storage for local testing with:

```bash
chmod -R u+rwX storage
```

Fix for a typical Apache VPS, adjusting user/group if needed:

```bash
sudo chown -R www-data:www-data storage
sudo chmod -R 750 storage
```

### Port `8080` already in use

Cause: another local process is using port 8080.

Fix: use another port:

```bash
php -S 127.0.0.1:8090 -t public
```

Then visit:

```text
http://127.0.0.1:8090/login
```

---

## 26. Cleanup after testing

To remove test data while keeping schema:

```bash
mysql -u seed_library_test -p seed_library_test <<'SQL'
SET FOREIGN_KEY_CHECKS=0;
TRUNCATE TABLE companion_relationships;
TRUNCATE TABLE seed_uses;
TRUNCATE TABLE seed_locations;
TRUNCATE TABLE seed_history;
TRUNCATE TABLE seeds;
SET FOREIGN_KEY_CHECKS=1;
SQL
```

To drop the local test database entirely:

```bash
sudo mysql <<'SQL'
DROP DATABASE IF EXISTS seed_library_test;
DROP USER IF EXISTS 'seed_library_test'@'localhost';
FLUSH PRIVILEGES;
SQL
```

## Phase 1 verification status

### Completed checks

The implementation review ran PHP lint over `app`, `public`, and `scripts`, `git diff --check`, and targeted PHP/static tests for exact CSV/XLSX Seed Number preservation, header trimming/mapping, strict integers and Plantable Months, real/cross-year month-day ranges, canonical duplicate Use/companion IDs, failed-form checkbox state, perennial reconciliation, companion comparison identity, friendly history formatting, no-op history guards, import error isolation, writable-storage resolution, and duplication transaction/savepoint guards.

Separately, Phase 1 isolated VPS/database verification passed and the owner’s Phase 1 live smoke test passed. The recorded result does not establish that every item in this document’s broader regression checklist—such as every form action, import path, security case, fresh-schema path, or responsive state—was part of those live runs.

The checklist above remains useful for future deployment regression runs, but it is no longer a list of outstanding Phase 1 acceptance work.

# Phase 2 dashboard, inventory, search, and filters

Run `php tests/phase2_static.php`, PHP lint across every PHP file, and `git diff --check` before database/browser testing.

With an isolated MySQL database containing records that deliberately overlap Uses and companions:

1. Verify each of the twelve dashboard cards against direct SQL counts, including a normal-year and a cross-year planting window at the current date. Confirm Food Crops/Herbs/Flowers use category data, Medicinal accepts its flag/category, and planting methods/ranges drive the two method counts.
2. Search partial mixed-case fragments unique to Seed Number, name, variety, category, family, plant type, companion name/number/variety, Use, and notes. A seed matching several companion/Use rows must appear once. Submit Dashboard Quick Search and confirm the identical inventory result/query value.
3. Exercise Category, Plant Family, Status, Plantable Month, Plant Type, Planting Method, source/brand, Packet Year, location, companion, and every Yes/No flag/capability separately. Location must match box, container, envelope, row, slot, and storage notes.
4. Select multiple Uses and confirm all selected Uses are required. Combine filters for container-friendly herbs, medicinal perennials, July planting, a direct-sow planting range, source, location, and companion. Confirm all controls retain values.
5. Test reusable `MM-DD` Start and Last Planting Date boundaries, including leap day and cross-year records. Bypass the browser pattern check or submit a crafted request with an invalid/nonexistent boundary such as `02-30`; confirm the entered value remains visible, a clear validation error identifies the boundary, no seed query/results are shown, and filtered export/print actions are unavailable until the error is corrected.
6. Click **Clear All / Reset** and confirm the URL and every control return to defaults.
7. Test every sort header and the sort controls in both directions. Confirm search, every filter, multiple Uses, rows per page, and the current query survive sorting.
8. Insert at least 205 matching seeds. Verify pages and 25/50/100/200 Rows Per Page use server-side result windows; confirm previous/next/page links preserve all state and an out-of-range page is clamped.
9. Verify an empty library displays the first-seed empty-state message. Then add records and verify a no-match combination displays the distinct filtered-results message and reset link. Both cases show `0–0 of 0` and `Page 0 of 0`; the empty-state message—not the shared numeric context—identifies whether the library itself is empty or filters returned no matches. Also verify first, middle, and last populated pages show the correct X–Y of Z and Page N of M values.
10. At phone width, confirm cards expose major seed details and usable View/Edit/Duplicate/Delete controls without relying on the desktop table.
11. From desktop and mobile results, run View and Edit; POST Duplicate and Delete with valid CSRF tokens. Confirm GET mutation attempts return 405 and invalid tokens return 419. Confirm exact/duplicate Seed Numbers, Uses, unlimited companion relationships, Plantable Months, reusable ranges, history, and locations retain all established behavior.
12. Verify filtered CSV and print actions retain the active inventory query. Confirm no claim of Phase 4 full export/backup behavior is implied.

The Phase 2 live smoke test passed. The broader database/browser scenarios above remain separate regression coverage and require an authenticated configured application with representative MySQL fixtures; the smoke test and static source checks do not prove every listed scenario. This pending status does not reopen the recorded Phase 1 isolated VPS/database pass or the owner’s Phase 1 live smoke-test pass, and it does not expand the scope proven by those runs.

# Phase 3 verification

Run static checks:

```bash
find app public scripts tests -name '*.php' -print0 | xargs -0 -n1 php -l
php tests/phase2_static.php
php tests/phase3_static.php
git diff --check
```

Companion Finder direction rules: Good Companion, Avoid, and Neutral are symmetric. Pest Deterrent, Trap Crop, Support Plant, and Pollinator Support are directional and preserve each stored source → target direction. Reciprocal or multiple records must produce one row per returned seed and relationship type, with distinct notes and directional paths merged.

Database/browser checks: apply the idempotent Phase 3 status migration twice and confirm custom statuses remain; test January through December, an explicit Plantable Months seed, and a Nov–Feb range; exercise every calendar group; save more than six companions, remove a middle row, reject self/invalid/duplicate pairs, then duplicate the seed and inspect history; search each endpoint by name/variety/number and verify unique, correctly categorized results and Good Companion/Avoid headings; change each practical status and confirm inventory filtering, detail display, and friendly history values.

# Phase 4 verification

```bash
find app public scripts tests -name '*.php' -print0 | xargs -0 -n1 php -l
php tests/phase2_static.php
php tests/phase3_static.php
php tests/phase4_static.php
git diff --check
```

Phase 4 static/behavioral checks cover failed-row retention and blocking/skip controls, post-edit exact duplicate revalidation, missing Update Existing targets, transactional storage upsert wiring, filtered-export safety, Companion plant filtering, concise report columns, neutral known-layout wording, the known-workbook heading detector and transforms, namespace/rich-text XML helpers, review resolution controls, staged within-file update logic, canonical backup containment (including a symlink), cron paths/permissions, and companion direction/deduplication.

Live owner verification must use a disposable/restorable MySQL database: upload the actual starting CSV/XLSX (there is intentionally no fabricated workbook), inspect compatibility detection, first-semicolon name/variety split, month-name dates, safe outdoor window and numeric/flag transforms, preserved original-heading Notes, Category and ambiguity correction, then trigger required/date/numeric/lookup/within-file/database duplicate errors and confirm every raw row remains visible until corrected or skipped, and exercise each per-row duplicate resolution while comparing Seed Number bytes, including a new first occurrence followed by within-file Update Existing. Map and update every storage field in the same transaction; verify omitted location fields remain unchanged and explicitly mapped blanks clear only their own fields. Correct and skip error/manual rows and verify final historical summary counts remain present. Change Seed Numbers during review and confirm targets/counts are recalculated. Confirm cancelled review writes nothing and a forced database error rolls the entire import back. Combine every Phase 2 filter and compare screen results with filtered CSV/XLSX; open both formats. Verify the standalone Export page cannot initiate an unfiltered “Filtered Results” download. Filter the Companion Guide by plant and verify symmetric/directional merged output. Print/save every concise report, all twelve calendar months, long tables, and Seed Bank's exact four columns.

As a non-owner, verify `/backup` returns 403. As owner, download a full backup, verify no file appears under `public/`, reject malformed/oversize/wrong-version/incomplete JSON, backups without a valid owner, and wrong confirmation; then restore a known disposable fixture and confirm the prior session is invalidated and a fresh owner login is required. On the VPS, run the documented backup command, inspect owner/mode/location, simulate dated files to verify 7 daily/4 weekly retention, install and observe cron, and perform a recovery drill before relying on scheduling.

## Phase 5 verification

Run `php tests/phase5_static.php`, all earlier phase tests, PHP lint, and `git diff --check`. These source-level checks have passed, but the Phase 5 authenticated browser/database and VPS checks below remain pending and are not implied by the static results. On a disposable MySQL copy, apply `database/2026-08-14-phase-5-settings-defaults.sql` twice and confirm every required starter exists once while custom lookup rows and preexisting settings remain unchanged. As an owner, add and edit every lookup type; verify duplicate and blank names show errors, names over the schema limits (Categories 100, Plant Families 120, Uses 120, and Statuses 80 characters) are rejected before SQL execution, referenced rows cannot be deleted, and unreferenced rows can. Verify a non-owner receives 403 for Settings and every management URL.

From **Manage → Storage Locations**, edit and clear all six physical fields; confirm a real change creates one meaningful history entry and a no-op save creates none, and confirm seed detail/search/import/export remain correct and Seed Number is unchanged. Save and reload all garden fields; reject invalid zone, ZIP, overlong text, and impossible/non-`MM-DD` frost dates. For every display preference, compare inventory/detail/calendar output before and after. Confirm default sort/page size applies with no query, valid explicit `sort`/`per_page` overrides it, natural and exact-text Seed Number order differ on representative labels, exact dates switch to month-only, and Plantable Months rows are entirely omitted from visible inventory and seed-detail output. Finally follow the Settings backup link and repeat the Phase 4 non-owner, CSRF, confirmation, validation, private download, and disposable restore checks.
