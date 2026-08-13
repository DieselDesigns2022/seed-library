-- Non-destructive Phase 1 upgrade. Back up the database, then run once.
ALTER TABLE seeds
  ADD COLUMN plantable_months VARCHAR(35) NULL COMMENT 'Comma-separated month numbers 1-12' AFTER planting_end_day,
  ADD COLUMN indoor_start_month TINYINT UNSIGNED NULL AFTER plantable_months,
  ADD COLUMN indoor_start_day TINYINT UNSIGNED NULL AFTER indoor_start_month,
  ADD COLUMN indoor_end_month TINYINT UNSIGNED NULL AFTER indoor_start_day,
  ADD COLUMN indoor_end_day TINYINT UNSIGNED NULL AFTER indoor_end_month,
  ADD COLUMN direct_sow_start_month TINYINT UNSIGNED NULL AFTER indoor_end_day,
  ADD COLUMN direct_sow_start_day TINYINT UNSIGNED NULL AFTER direct_sow_start_month,
  ADD COLUMN direct_sow_end_month TINYINT UNSIGNED NULL AFTER direct_sow_start_day,
  ADD COLUMN direct_sow_end_day TINYINT UNSIGNED NULL AFTER direct_sow_end_month,
  ADD COLUMN transplant_start_month TINYINT UNSIGNED NULL AFTER direct_sow_end_day,
  ADD COLUMN transplant_start_day TINYINT UNSIGNED NULL AFTER transplant_start_month,
  ADD COLUMN transplant_end_month TINYINT UNSIGNED NULL AFTER transplant_start_day,
  ADD COLUMN transplant_end_day TINYINT UNSIGNED NULL AFTER transplant_end_month,
  ADD COLUMN ideal_soil_temperature VARCHAR(80) NULL AFTER sowing_depth,
  ADD COLUMN row_spacing VARCHAR(120) NULL AFTER ideal_soil_temperature,
  ADD COLUMN thin_to_spacing VARCHAR(120) NULL AFTER row_spacing,
  ADD COLUMN minimum_container_size VARCHAR(120) NULL AFTER thin_to_spacing,
  ADD COLUMN drought_tolerant TINYINT(1) NOT NULL DEFAULT 0 AFTER heat_tolerant,
  ADD COLUMN perennial_status ENUM('Annual','Biennial','Perennial') NULL AFTER trellis_needed;
UPDATE seeds SET perennial_status = 'Perennial' WHERE perennial = 1 AND perennial_status IS NULL;
