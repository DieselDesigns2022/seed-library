# Database

Import `schema.sql` into a MySQL/MariaDB database before first login. The schema inserts default settings for Zone 6B, ZIP 48239, Southeast Michigan, average last frost `05-05`, and average first frost `10-15`.

## Upgrade an existing installation for Phase 1 fields

Back up the database, then apply the non-destructive migration **once** (do not re-import `schema.sql` over a live database):

```bash
mysql -u YOUR_USER -p YOUR_DATABASE < database/2026-08-12-phase-1-seed-fields.sql
```

The migration only adds nullable/defaulted columns and backfills `perennial_status = 'Perennial'` for existing `perennial = 1` rows. It does not drop, rename, renumber, or deduplicate records. Fresh installations already contain these columns in `database/schema.sql`.

Added seed columns: `plantable_months`; month/day pairs for `indoor_start`, `indoor_end`, `direct_sow_start`, `direct_sow_end`, `transplant_start`, and `transplant_end`; `ideal_soil_temperature`; `row_spacing`; `thin_to_spacing`; `minimum_container_size`; `drought_tolerant`; and `perennial_status`. Existing columns remain authoritative for compatible concepts.

## Upgrade an existing installation for Phase 3 statuses

Back up the database, then apply the Phase 3 status migration:

```bash
mysql -u YOUR_USER -p YOUR_DATABASE < database/2026-08-13-phase-3-statuses.sql
```

This migration is safe to run more than once. It inserts any missing practical Phase 3 statuses and does not delete, rename, disable, or otherwise overwrite existing custom statuses. Fresh installations already contain the same statuses in `database/schema.sql`.

## Storage and compatibility details

Plantable Months are stored as validated comma-separated month numbers in `plantable_months`; all planting ranges remain reusable month/day components with no year. The application keeps the original `perennial` boolean for existing dashboard/filter compatibility and derives it from `perennial_status` whenever the richer status is supplied (`Perennial` = 1; `Annual`/`Biennial` = 0).

The Phase 1 field migration (`2026-08-12-phase-1-seed-fields.sql`) is intentionally a one-time script and is not idempotent: applying that Phase 1 migration again will fail because the columns already exist. The Phase 3 status migration remains idempotent and safe to rerun. Test it against a restored backup before applying it to the live VPS. It does not change the existing location, Uses, companion, or history tables, and it does not add a unique constraint to `seed_number`.

## Phase 4 owner migration and backup restore

Apply the one-time owner authorization migration before using the web backup page:

```bash
mysql -u YOUR_USER -p YOUR_DATABASE < database/2026-08-13-phase-4-owner.sql
```

This adds only `users.is_owner`, defaulting existing users to owner for compatibility; it changes no seed records or Seed Numbers. Fresh installs already have the column. Phase 4 seed imports upsert mapped `seed_locations` fields inside the same transaction as seed inserts/updates; an import failure rolls both back. Full database backups are versioned JSON containing every application table, unlike CSV/XLSX seed exports. Restore is an owner-only, CSRF-protected, explicitly confirmed replacement of all application tables inside a transaction. Restore rejects backups without a valid owner account and invalidates the current authenticated session after success, requiring a fresh login against restored users. The backup script resolves the configured path after creation and rejects the public directory, descendants, traversal resolutions, and symlinks into `public/`, while allowing similarly named sibling directories. See the Phase 4 README section for the `/var/backups/seed-library` off-web-root schedule, `www-data` ownership, writable `/var/log/seed-library/backup.log`, 7-daily/4-weekly retention, cron setup, and restore procedure.

### Phase 4 structured growing data
Run `2026-08-14-phase-4-growing-data.sql` on an existing installation. The migration checks each column and is safe to rerun after either a complete or interrupted earlier run. It only adds nullable columns and backfills the new maturity minimum/maximum from the legacy value; it does not delete or replace seeds, relationships, uses, months, statuses, or any other data. Fresh installations already include these columns in `schema.sql`.
