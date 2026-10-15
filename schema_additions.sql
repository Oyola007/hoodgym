-- Run this once against your `hoodgym` database before using trainer_dashboard.php
-- These two tables back the "Attendance" and "Member Notes" features.

CREATE TABLE IF NOT EXISTS class_attendance (
  id INT AUTO_INCREMENT PRIMARY KEY,
  class_id INT NOT NULL,
  user_id INT NOT NULL,
  session_date DATE NOT NULL,
  status ENUM('present','absent') NOT NULL DEFAULT 'present',
  marked_by INT NOT NULL,               -- users.id of the trainer who marked it
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_class_user_date (class_id, user_id, session_date),
  FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (marked_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS member_notes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  trainer_id INT NOT NULL,              -- users.id of the trainer who wrote it
  user_id INT NOT NULL,                 -- users.id of the member the note is about
  note TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (trainer_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
