ALTER TABLE seeds
  ADD COLUMN winter_sowing_suitability ENUM('Suitable','Not Suitable','Unknown') NOT NULL DEFAULT 'Unknown' AFTER trellis_needed,
  ADD COLUMN winter_sowing_months VARCHAR(11) NULL COMMENT 'Explicit comma-separated eligible months limited to 12,1,2,3' AFTER winter_sowing_suitability,
  ADD COLUMN cold_stratification ENUM('Required','Beneficial','Not Required','Unknown') NOT NULL DEFAULT 'Unknown' AFTER winter_sowing_months,
  ADD COLUMN winter_hardiness ENUM('Hardy','Tender','Unknown') NOT NULL DEFAULT 'Unknown' AFTER cold_stratification,
  ADD COLUMN winter_sowing_notes TEXT NULL AFTER winter_hardiness,
  ADD COLUMN winter_sowing_citation TEXT NULL AFTER winter_sowing_notes;

CREATE TABLE garden_plantings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  seed_id INT UNSIGNED NOT NULL,
  planted_date DATE NOT NULL,
  planting_method ENUM('Direct Sown','Started Indoors','Transplanted','Winter Sown','Other') NOT NULL,
  quantity SMALLINT UNSIGNED NOT NULL,
  location VARCHAR(190) NOT NULL,
  notes TEXT NULL,
  actual_transplant_date DATE NULL,
  actual_harvest_date DATE NULL,
  status ENUM('Planned','Sown','Germinating','Growing','Transplanted','Harvesting','Harvested','Failed','Archived') NOT NULL DEFAULT 'Planned',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_garden_plantings_seed FOREIGN KEY (seed_id) REFERENCES seeds(id) ON DELETE RESTRICT,
  INDEX idx_garden_seed (seed_id),
  INDEX idx_garden_status_dates (status, planted_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
