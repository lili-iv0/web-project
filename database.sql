-- Fitness Tracker schema (reconstructed from the code) + a starter admin account.
-- Import into MySQL/MariaDB (phpMyAdmin > Import). Safe to run on an existing DB:
-- it only creates what's missing.
CREATE DATABASE IF NOT EXISTS `users` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `users`;

CREATE TABLE IF NOT EXISTS sections (
  id INT AUTO_INCREMENT PRIMARY KEY,
  section_code VARCHAR(50) NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS teachers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  first_name VARCHAR(100) DEFAULT NULL,
  username VARCHAR(50) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  profile_picture VARCHAR(255) DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS students (
  id INT AUTO_INCREMENT PRIMARY KEY,
  first_name VARCHAR(100) NOT NULL,
  last_name VARCHAR(100) NOT NULL,
  username VARCHAR(50) NOT NULL UNIQUE,
  age INT NOT NULL,
  section VARCHAR(50) NOT NULL,
  password VARCHAR(255) NOT NULL,
  profile_picture VARCHAR(255) DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS fitness_profiles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  test_id TINYINT NOT NULL DEFAULT 1,
  height_cm DECIMAL(5,1), weight_kg DECIMAL(5,1), bmi DECIMAL(5,1), vo2max DECIMAL(5,1),
  flexibility DECIMAL(4,1), strength DECIMAL(4,1), agility DECIMAL(4,1), speed DECIMAL(4,1), endurance DECIMAL(4,1),
  age INT, resting_hr INT, max_hr INT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (student_id),
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS student_remarks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  teacher_id INT NOT NULL,
  text TEXT NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE
);

INSERT IGNORE INTO sections (section_code) VALUES
 ('BSIT 2A-G1'),('BSIT 2A-G2'),('BSIT 2G-G1'),('BSIT 2G-G2'),('BSIT 2H-G1'),('BSIT 2H-G2');

-- Starter admin (only created if no teacher named "admin" exists).
-- Username: admin   Password: Admin@12345   <-- change it after first login!
INSERT IGNORE INTO teachers (first_name, username, password)
VALUES ('Admin', 'admin', '$2y$10$.rrzwiywG5RyUaaeOYsPWez8ae.2IP5FF6364AlK5zXvVH/v2OWXi');
