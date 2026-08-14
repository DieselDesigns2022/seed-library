-- Phase 4 owner-only database backup and restore authorization.
-- Existing administrators become owners to preserve access on single-owner installations.
ALTER TABLE users ADD COLUMN is_owner TINYINT(1) NOT NULL DEFAULT 1 AFTER password_hash;
