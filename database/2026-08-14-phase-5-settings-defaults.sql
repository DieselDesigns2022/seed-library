-- Phase 5 is idempotent: add only missing starter values/settings.
-- Existing owner-created lookup values and saved settings are never changed.
INSERT IGNORE INTO categories (name) VALUES
('Vegetable'),('Fruit'),('Herb'),('Medicinal Herb'),('Flower'),('Pollinator Plant'),('Cover Crop'),('Grain'),('Root Crop'),('Brassica'),('Legume'),('Cucurbit'),('Nightshade'),('Allium'),('Microgreen'),('Tree/Shrub'),('Native Plant');
INSERT IGNORE INTO plant_families (name) VALUES
('Solanaceae'),('Cucurbitaceae'),('Brassicaceae'),('Fabaceae'),('Apiaceae'),('Asteraceae'),('Amaranthaceae'),('Lamiaceae'),('Poaceae'),('Amaryllidaceae'),('Malvaceae'),('Rosaceae'),('Polygonaceae'),('Boraginaceae'),('Plantaginaceae');
INSERT IGNORE INTO uses (name) VALUES
('Fresh Eating'),('Cooking'),('Canning'),('Pickling'),('Freezing'),('Dehydrating'),('Tea'),('Medicinal'),('Tincture'),('Salve'),('Pollinator'),('Cut Flower'),('Dye Plant'),('Pest Deterrent'),('Companion Plant'),('Cover Crop'),('Soil Improvement'),('Animal Feed'),('Seed Saving'),('Ornamental');
INSERT IGNORE INTO statuses (name,is_active) VALUES
('In Seed Bank',1),('Started Indoors',1),('Direct Sown',1),('Transplanted',1),('Growing',1),('Harvested',1),('Failed Germination',1),('Out of Stock',1),('Need to Buy More',1),('Save for Next Year',1);
INSERT IGNORE INTO settings (setting_key,setting_value) VALUES
('zone','6B'),('zip','48239'),('region','Southeast Michigan'),('average_last_frost','05-05'),('average_first_frost','10-15'),('garden_notes',''),('display_exact_dates','1'),('display_plantable_months','1'),('seed_number_order','natural'),('default_inventory_sort','seed_number'),('rows_per_page','25');
