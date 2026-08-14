-- Add plant-family lookup values required by the current 224-seed library.
-- Safe to rerun; no seed records are changed.
INSERT INTO plant_families (name) VALUES
('Adoxaceae'),
('Amaranthaceae'),
('Amaryllidaceae'),
('Cactaceae'),
('Caryophyllaceae'),
('Cleomaceae'),
('Convolvulaceae'),
('Hypericaceae'),
('Malvaceae'),
('Plantaginaceae'),
('Polygonaceae'),
('Ranunculaceae'),
('Scrophulariaceae'),
('Tropaeolaceae'),
('Urticaceae'),
('Verbenaceae'),
('Violaceae'),
('Mixed / Multiple Families')
ON DUPLICATE KEY UPDATE name = VALUES(name);
