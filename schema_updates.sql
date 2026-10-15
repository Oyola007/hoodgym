-- ============================================================
-- HoodGym: schema additions for Package management + Trainer/Class assignment
-- Run this against your `hoodgym` database.
-- Safe to run even if `packages` already exists (uses IF NOT EXISTS
-- for the table, and separate ALTERs guarded with checks below).
-- ============================================================

-- 1. Packages table (create if it doesn't exist yet)
CREATE TABLE IF NOT EXISTS packages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    price DECIMAL(10,2) NOT NULL DEFAULT 0,
    duration_days INT NOT NULL DEFAULT 30,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- If your `packages` table already existed (since bookings/payments
-- already join on it) but is missing any of these columns, run the
-- matching line below manually (MySQL doesn't support
-- "ADD COLUMN IF NOT EXISTS" on all versions, so check first with
-- `DESCRIBE packages;`):
--
-- ALTER TABLE packages ADD COLUMN description TEXT NULL;
-- ALTER TABLE packages ADD COLUMN price DECIMAL(10,2) NOT NULL DEFAULT 0;
-- ALTER TABLE packages ADD COLUMN duration_days INT NOT NULL DEFAULT 30;
-- ALTER TABLE packages ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

-- 2. Classes table - a class taught by a trainer, optionally tied to a package
CREATE TABLE IF NOT EXISTS classes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_name VARCHAR(100) NOT NULL,
    trainer_id INT NOT NULL,
    package_id INT NULL,
    schedule_day VARCHAR(20) NOT NULL,
    schedule_time TIME NOT NULL,
    capacity INT NOT NULL DEFAULT 20,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_classes_trainer FOREIGN KEY (trainer_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_classes_package FOREIGN KEY (package_id) REFERENCES packages(id) ON DELETE SET NULL
) ENGINE=InnoDB;
