-- Create Matches table for AI-powered item matching
-- This stores potential matches between lost items and found items
-- Run this migration: mysql -u root campus_lost_found < create_matches_table.sql

CREATE TABLE IF NOT EXISTS ItemMatch (
    match_id INT AUTO_INCREMENT PRIMARY KEY,
    lost_item_id INT NOT NULL,
    found_item_id INT NOT NULL,
    confidence_score DECIMAL(5,2) NOT NULL, -- 0.00 to 100.00
    match_reasoning TEXT, -- Explanation of why items match
    status ENUM('pending', 'confirmed', 'dismissed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (lost_item_id) REFERENCES Item(item_id) ON DELETE CASCADE,
    FOREIGN KEY (found_item_id) REFERENCES Item(item_id) ON DELETE CASCADE,
    UNIQUE KEY unique_match (lost_item_id, found_item_id),
    INDEX idx_lost_item (lost_item_id),
    INDEX idx_found_item (found_item_id),
    INDEX idx_status (status),
    INDEX idx_confidence (confidence_score)
);

-- Table to track which users have been notified about matches
CREATE TABLE IF NOT EXISTS MatchNotification (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    match_id INT NOT NULL,
    user_id INT NOT NULL,
    notified_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    viewed BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (match_id) REFERENCES ItemMatch(match_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES User(user_id) ON DELETE CASCADE,
    UNIQUE KEY unique_notification (match_id, user_id)
);
