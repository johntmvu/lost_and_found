-- SQL schema for Campus Lost & Found
CREATE DATABASE IF NOT EXISTS campus_lost_found DEFAULT CHARACTER SET = 'utf8mb4' COLLATE = 'utf8mb4_unicode_ci';
USE campus_lost_found;

-- User
CREATE TABLE IF NOT EXISTS `User` (
  user_id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(255) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- Item
CREATE TABLE IF NOT EXISTS `Item` (
  item_id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  description TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Claim
CREATE TABLE IF NOT EXISTS `Claim` (
  claim_id INT AUTO_INCREMENT PRIMARY KEY,
  description TEXT,
  photo VARCHAR(512),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Location
CREATE TABLE IF NOT EXISTS `Location` (
  location_id INT AUTO_INCREMENT PRIMARY KEY,
  building VARCHAR(255) NOT NULL,
  room VARCHAR(100)
) ENGINE=InnoDB;

-- Junctions
CREATE TABLE IF NOT EXISTS `Submits` (
  user_id INT NOT NULL,
  claim_id INT NOT NULL,
  PRIMARY KEY (user_id, claim_id),
  FOREIGN KEY (user_id) REFERENCES `User`(user_id) ON DELETE CASCADE,
  FOREIGN KEY (claim_id) REFERENCES Claim(claim_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `Posts` (
  user_id INT NOT NULL,
  item_id INT NOT NULL,
  PRIMARY KEY (user_id, item_id),
  FOREIGN KEY (user_id) REFERENCES `User`(user_id) ON DELETE CASCADE,
  FOREIGN KEY (item_id) REFERENCES Item(item_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `Targets` (
  item_id INT NOT NULL,
  claim_id INT NOT NULL,
  PRIMARY KEY (item_id, claim_id),
  FOREIGN KEY (item_id) REFERENCES Item(item_id) ON DELETE CASCADE,
  FOREIGN KEY (claim_id) REFERENCES Claim(claim_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `At` (
  location_id INT NOT NULL,
  item_id INT NOT NULL,
  PRIMARY KEY (location_id, item_id),
  FOREIGN KEY (location_id) REFERENCES Location(location_id) ON DELETE CASCADE,
  FOREIGN KEY (item_id) REFERENCES Item(item_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Optional indexes for searching
-- CREATE INDEX IF NOT EXISTS idx_item_title ON Item(title(100));
-- CREATE INDEX IF NOT EXISTS idx_item_description ON Item(description(255));
