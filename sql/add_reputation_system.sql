-- Reputation & Trust Score System
-- Adds reputation tracking, user actions log, and badge/achievement system

USE campus_lost_found;

-- Add reputation score to User table
ALTER TABLE User 
ADD COLUMN reputation_score INT DEFAULT 0 AFTER email,
ADD COLUMN verified BOOLEAN DEFAULT FALSE AFTER reputation_score,
ADD COLUMN member_since TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER verified;

-- User Action Log - tracks all reputation-affecting events
CREATE TABLE IF NOT EXISTS UserAction (
    action_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action_type ENUM('item_posted', 'claim_submitted', 'claim_approved', 'claim_rejected', 
                     'item_returned', 'match_confirmed', 'account_verified') NOT NULL,
    points_awarded INT NOT NULL DEFAULT 0,
    related_item_id INT NULL,
    related_claim_id INT NULL,
    action_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES User(user_id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_type (action_type),
    INDEX idx_timestamp (action_timestamp)
);

-- Badge System
CREATE TABLE IF NOT EXISTS Badge (
    badge_id INT AUTO_INCREMENT PRIMARY KEY,
    badge_name VARCHAR(100) NOT NULL,
    badge_description TEXT,
    badge_icon VARCHAR(50),
    requirement_type ENUM('items_returned', 'claims_approved', 'reputation_score', 
                         'verified_account', 'time_member', 'items_posted') NOT NULL,
    requirement_value INT NOT NULL,
    badge_level ENUM('bronze', 'silver', 'gold', 'platinum') DEFAULT 'bronze',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- User Badge Junction Table
CREATE TABLE IF NOT EXISTS UserBadge (
    user_badge_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    badge_id INT NOT NULL,
    earned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES User(user_id) ON DELETE CASCADE,
    FOREIGN KEY (badge_id) REFERENCES Badge(badge_id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_badge (user_id, badge_id)
);

-- Insert default badges
INSERT INTO Badge (badge_name, badge_description, badge_icon, requirement_type, requirement_value, badge_level) VALUES
('Newcomer', 'Welcome to Campus Lost & Found!', '🆕', 'reputation_score', 0, 'bronze'),
('Helper', 'Posted 5 found items', '🤝', 'items_posted', 5, 'bronze'),
('Super Helper', 'Posted 20 found items', '⭐', 'items_posted', 20, 'silver'),
('Trusted', 'Reputation score of 50+', '✅', 'reputation_score', 50, 'bronze'),
('Highly Trusted', 'Reputation score of 100+', '💎', 'reputation_score', 100, 'silver'),
('Elite', 'Reputation score of 250+', '👑', 'reputation_score', 250, 'gold'),
('Legendary', 'Reputation score of 500+', '🏆', 'reputation_score', 500, 'platinum'),
('Reuniter', 'Successfully returned 3 items', '💝', 'items_returned', 3, 'bronze'),
('Master Reuniter', 'Successfully returned 10 items', '🎁', 'items_returned', 10, 'silver'),
('Verified Student', 'Account verified with student email', '🎓', 'verified_account', 1, 'bronze'),
('Veteran', 'Member for 90+ days', '⏰', 'time_member', 90, 'bronze'),
('Accurate Claimer', '5 approved claims', '🎯', 'claims_approved', 5, 'bronze');

-- Award newcomer badge to all existing users
INSERT INTO UserBadge (user_id, badge_id)
SELECT u.user_id, b.badge_id
FROM User u
CROSS JOIN Badge b
WHERE b.badge_name = 'Newcomer'
AND NOT EXISTS (
    SELECT 1 FROM UserBadge ub 
    WHERE ub.user_id = u.user_id AND ub.badge_id = b.badge_id
);
