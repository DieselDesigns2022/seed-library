CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  is_owner TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  description TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE plant_families (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL UNIQUE,
  description TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE uses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL UNIQUE,
  description TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE statuses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL UNIQUE,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE settings (
  setting_key VARCHAR(100) PRIMARY KEY,
  setting_value TEXT NOT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE seeds (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  seed_number VARCHAR(100) NOT NULL,
  name VARCHAR(190) NOT NULL,
  variety VARCHAR(190) NULL,
  category_id INT UNSIGNED NULL,
  plant_family_id INT UNSIGNED NULL,
  status_id INT UNSIGNED NULL,
  plant_type VARCHAR(80) NULL,
  planting_method ENUM('Direct Sow','Start Indoors','Transplant','Direct Sow or Transplant') NULL,
  days_to_germination_min SMALLINT UNSIGNED NULL,
  days_to_germination_max SMALLINT UNSIGNED NULL,
  days_to_maturity SMALLINT UNSIGNED NULL,
  days_to_maturity_min SMALLINT UNSIGNED NULL,
  days_to_maturity_max SMALLINT UNSIGNED NULL,
  maturity_qualifier VARCHAR(120) NULL,
  planting_start_month TINYINT UNSIGNED NULL,
  planting_start_day TINYINT UNSIGNED NULL,
  planting_end_month TINYINT UNSIGNED NULL,
  planting_end_day TINYINT UNSIGNED NULL,
  plantable_months VARCHAR(35) NULL COMMENT 'Comma-separated month numbers 1-12',
  indoor_start_status ENUM('Not Recommended','Not Applicable') NULL,
  indoor_start_month TINYINT UNSIGNED NULL,
  indoor_start_day TINYINT UNSIGNED NULL,
  indoor_end_month TINYINT UNSIGNED NULL,
  indoor_end_day TINYINT UNSIGNED NULL,
  direct_sow_status ENUM('Not Recommended','Not Applicable') NULL,
  direct_sow_start_month TINYINT UNSIGNED NULL,
  direct_sow_start_day TINYINT UNSIGNED NULL,
  direct_sow_end_month TINYINT UNSIGNED NULL,
  direct_sow_end_day TINYINT UNSIGNED NULL,
  transplant_status ENUM('Not Recommended','Not Applicable') NULL,
  transplant_start_month TINYINT UNSIGNED NULL,
  transplant_start_day TINYINT UNSIGNED NULL,
  transplant_end_month TINYINT UNSIGNED NULL,
  transplant_end_day TINYINT UNSIGNED NULL,
  indoor_start_weeks_before_frost TINYINT UNSIGNED NULL,
  transplant_weeks_after_frost TINYINT UNSIGNED NULL,
  succession_days SMALLINT UNSIGNED NULL,
  sun_requirements VARCHAR(120) NULL,
  water_requirements VARCHAR(120) NULL,
  soil_requirements VARCHAR(190) NULL,
  spacing VARCHAR(120) NULL,
  sowing_depth VARCHAR(80) NULL,
  ideal_soil_temperature VARCHAR(80) NULL,
  row_spacing VARCHAR(120) NULL,
  thin_to_spacing VARCHAR(120) NULL,
  minimum_container_size VARCHAR(120) NULL,
  plant_height VARCHAR(80) NULL,
  container_friendly TINYINT(1) NOT NULL DEFAULT 0,
  pollinator_friendly TINYINT(1) NOT NULL DEFAULT 0,
  medicinal TINYINT(1) NOT NULL DEFAULT 0,
  perennial TINYINT(1) NOT NULL DEFAULT 0,
  frost_tolerant TINYINT(1) NOT NULL DEFAULT 0,
  heat_tolerant TINYINT(1) NOT NULL DEFAULT 0,
  drought_tolerant TINYINT(1) NOT NULL DEFAULT 0,
  trellis_needed TINYINT(1) NOT NULL DEFAULT 0,
  winter_sowing_suitability ENUM('Suitable','Not Suitable','Unknown') NOT NULL DEFAULT 'Unknown',
  winter_sowing_months VARCHAR(11) NULL COMMENT 'Explicit comma-separated eligible months limited to 12,1,2,3',
  cold_stratification ENUM('Required','Beneficial','Not Required','Unknown') NOT NULL DEFAULT 'Unknown',
  winter_hardiness ENUM('Hardy','Tender','Unknown') NOT NULL DEFAULT 'Unknown',
  winter_sowing_notes TEXT NULL,
  winter_sowing_citation TEXT NULL,
  perennial_status ENUM('Annual','Biennial','Perennial') NULL,
  seed_source VARCHAR(190) NULL,
  packet_year SMALLINT UNSIGNED NULL,
  quantity VARCHAR(100) NULL,
  purchase_date DATE NULL,
  expiration_year SMALLINT UNSIGNED NULL,
  notes TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_seeds_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
  CONSTRAINT fk_seeds_family FOREIGN KEY (plant_family_id) REFERENCES plant_families(id) ON DELETE SET NULL,
  CONSTRAINT fk_seeds_status FOREIGN KEY (status_id) REFERENCES statuses(id) ON DELETE SET NULL,
  INDEX idx_seed_number (seed_number),
  INDEX idx_seed_name (name),
  INDEX idx_seed_filters (category_id, plant_family_id, status_id, packet_year),
  INDEX idx_seed_flags (container_friendly, pollinator_friendly, medicinal, perennial)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE seed_locations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  seed_id INT UNSIGNED NOT NULL UNIQUE,
  storage_box VARCHAR(120) NULL,
  container VARCHAR(120) NULL,
  envelope VARCHAR(120) NULL,
  row_label VARCHAR(80) NULL,
  slot VARCHAR(80) NULL,
  notes TEXT NULL,
  CONSTRAINT fk_locations_seed FOREIGN KEY (seed_id) REFERENCES seeds(id) ON DELETE CASCADE,
  INDEX idx_location_box (storage_box, container, row_label, slot)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE seed_uses (
  seed_id INT UNSIGNED NOT NULL,
  use_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (seed_id, use_id),
  CONSTRAINT fk_seed_uses_seed FOREIGN KEY (seed_id) REFERENCES seeds(id) ON DELETE CASCADE,
  CONSTRAINT fk_seed_uses_use FOREIGN KEY (use_id) REFERENCES uses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE companion_relationships (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  seed_id INT UNSIGNED NOT NULL,
  companion_seed_id INT UNSIGNED NOT NULL,
  relationship_type ENUM('Good Companion','Avoid','Neutral','Pest Deterrent','Trap Crop','Support Plant','Pollinator Support') NOT NULL,
  notes TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_comp_seed FOREIGN KEY (seed_id) REFERENCES seeds(id) ON DELETE CASCADE,
  CONSTRAINT fk_comp_companion FOREIGN KEY (companion_seed_id) REFERENCES seeds(id) ON DELETE CASCADE,
  UNIQUE KEY uq_companion_pair (seed_id, companion_seed_id, relationship_type),
  INDEX idx_companion_lookup (companion_seed_id, relationship_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE seed_history (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  seed_id INT UNSIGNED NULL,
  user_id INT UNSIGNED NULL,
  action VARCHAR(80) NOT NULL,
  changes_json JSON NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_history_seed FOREIGN KEY (seed_id) REFERENCES seeds(id) ON DELETE SET NULL,
  CONSTRAINT fk_history_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_history_seed (seed_id, created_at),
  INDEX idx_history_action (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO categories (name, description) VALUES
('Vegetable','Edible vegetable crops'),('Fruit',NULL),('Herb','Culinary herbs'),('Medicinal','Medicinal plants'),('Medicinal Herb',NULL),('Flower','Flowering plants'),('Pollinator Plant',NULL),('Cover Crop',NULL),('Grain',NULL),('Root Crop',NULL),('Brassica',NULL),('Legume',NULL),('Cucurbit',NULL),('Nightshade',NULL),('Allium',NULL),('Microgreen',NULL),('Tree/Shrub',NULL),('Native Plant',NULL)
ON DUPLICATE KEY UPDATE name = VALUES(name);
INSERT INTO plant_families (name) VALUES
('Solanaceae'),('Cucurbitaceae'),('Brassicaceae'),('Fabaceae'),('Apiaceae'),('Asteraceae'),('Amaranthaceae'),('Lamiaceae'),('Poaceae'),('Amaryllidaceae'),('Malvaceae'),('Rosaceae'),('Polygonaceae'),('Boraginaceae'),('Plantaginaceae')
ON DUPLICATE KEY UPDATE name = VALUES(name);
INSERT INTO uses (name) VALUES
('Culinary'),('Container'),('Dye'),('Fresh Eating'),('Cooking'),('Canning'),('Pickling'),('Freezing'),('Dehydrating'),('Tea'),('Medicinal'),('Tincture'),('Salve'),('Pollinator'),('Cut Flower'),('Dye Plant'),('Pest Deterrent'),('Companion Plant'),('Cover Crop'),('Soil Improvement'),('Animal Feed'),('Seed Saving'),('Ornamental')
ON DUPLICATE KEY UPDATE name = VALUES(name);
INSERT INTO statuses (name, is_active) VALUES
('Active',1),('Low Stock',1),('Expired',0),('Archived',0),('Wish List',1),
('In Seed Bank',1),('Started Indoors',1),('Direct Sown',1),('Transplanted',1),('Growing',1),
('Harvested',1),('Failed Germination',1),('Out of Stock',1),('Need to Buy More',1),('Save for Next Year',1)
ON DUPLICATE KEY UPDATE name = VALUES(name);
INSERT INTO settings (setting_key, setting_value) VALUES
('zone','6B'),('zip','48239'),('region','Southeast Michigan'),('average_last_frost','05-05'),('average_first_frost','10-15'),
('garden_notes',''),('display_exact_dates','1'),('display_plantable_months','1'),('seed_number_order','natural'),('default_inventory_sort','seed_number'),('rows_per_page','25')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
