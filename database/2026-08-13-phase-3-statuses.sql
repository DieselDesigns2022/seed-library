-- Phase 3 practical statuses. Safe to rerun; existing/custom statuses are preserved.
INSERT INTO statuses (name, is_active) VALUES
('In Seed Bank',1),('Started Indoors',1),('Direct Sown',1),('Transplanted',1),
('Growing',1),('Harvested',1),('Failed Germination',1),('Out of Stock',1),
('Need to Buy More',1),('Save for Next Year',1)
ON DUPLICATE KEY UPDATE name=VALUES(name);
