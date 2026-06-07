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
