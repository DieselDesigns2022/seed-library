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
