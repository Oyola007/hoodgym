-- HoodGym migration: run this once in phpMyAdmin (SQL tab) or via mysql CLI.
-- Adds: calories_per_session to classes (so trainer attendance auto-tallies calories)
--       community_spotlight table (so admin can manage "The Crew" on the dashboard)

ALTER TABLE classes
  ADD COLUMN calories_per_session INT NOT NULL DEFAULT 300 AFTER capacity;

CREATE TABLE IF NOT EXISTS community_spotlight (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  tagline VARCHAR(150) DEFAULT NULL,
  photo VARCHAR(255) DEFAULT NULL,       -- relative path under uploads/community/
  display_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
