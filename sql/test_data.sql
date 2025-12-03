-- Sample Test Data for AI Matching System
-- This creates realistic lost/found item pairs to test the matching algorithm
-- Run: mysql -u root campus_lost_found < test_data.sql

-- Assume user_id 1 exists for testing
SET @user_id = 1;

-- Test Case 1: High Confidence Match (iPhone)
-- Found Item
INSERT INTO Item (title, description, photo, item_type, status) 
VALUES ('Black iPhone 12', 'Found in the library second floor near the study area. Has a cracked screen protector.', 
        'https://via.placeholder.com/300x200?text=iPhone', 'found', 'available');
SET @found_iphone = LAST_INSERT_ID();

-- Lost Item (should match above)
INSERT INTO Item (title, description, photo, item_type, status) 
VALUES ('iPhone 12 Pro', 'Lost my black iPhone in the library. It has a cracked screen protector and blue case.', 
        'https://via.placeholder.com/300x200?text=Lost+iPhone', 'lost', 'available');
SET @lost_iphone = LAST_INSERT_ID();

-- Test Case 2: Medium Confidence Match (Laptop)
-- Found Item
INSERT INTO Item (title, description, photo, item_type, status) 
VALUES ('Silver MacBook', 'Found silver laptop in computer lab B. Has stickers on it.', 
        'https://via.placeholder.com/300x200?text=MacBook', 'found', 'available');
SET @found_laptop = LAST_INSERT_ID();

-- Lost Item (should match above)
INSERT INTO Item (title, description, photo, item_type, status) 
VALUES ('MacBook Air', 'Lost my laptop somewhere in the engineering building. Its silver with band stickers.', 
        'https://via.placeholder.com/300x200?text=Lost+Laptop', 'lost', 'available');
SET @lost_laptop = LAST_INSERT_ID();

-- Test Case 3: Low Confidence Match (Water Bottle)
-- Found Item
INSERT INTO Item (title, description, photo, item_type, status) 
VALUES ('Blue Water Bottle', 'Found in the gym locker room. Hydroflask brand.', 
        'https://via.placeholder.com/300x200?text=Water+Bottle', 'found', 'available');
SET @found_bottle = LAST_INSERT_ID();

-- Lost Item (vague description, lower match)
INSERT INTO Item (title, description, photo, item_type, status) 
VALUES ('Water Bottle', 'Lost my water bottle somewhere on campus.', 
        'https://via.placeholder.com/300x200?text=Lost+Bottle', 'lost', 'available');
SET @lost_bottle = LAST_INSERT_ID();

-- Test Case 4: No Match (Different Items)
-- Found Item
INSERT INTO Item (title, description, photo, item_type, status) 
VALUES ('Red Backpack', 'Found Jansport backpack near the cafeteria.', 
        'https://via.placeholder.com/300x200?text=Backpack', 'found', 'available');
SET @found_backpack = LAST_INSERT_ID();

-- Lost Item (textbook - should NOT match backpack)
INSERT INTO Item (title, description, photo, item_type, status) 
VALUES ('Biology Textbook', 'Lost my biology 101 textbook in science hall.', 
        'https://via.placeholder.com/300x200?text=Textbook', 'lost', 'available');
SET @lost_textbook = LAST_INSERT_ID();

-- Link items to user via Posts table
INSERT INTO Posts (user_id, item_id) VALUES 
    (@user_id, @found_iphone),
    (@user_id, @lost_iphone),
    (@user_id, @found_laptop),
    (@user_id, @lost_laptop),
    (@user_id, @found_bottle),
    (@user_id, @lost_bottle),
    (@user_id, @found_backpack),
    (@user_id, @lost_textbook);

-- Add locations for better matching
INSERT INTO Location (building, room) VALUES ('Library', '2nd Floor');
SET @loc_lib = LAST_INSERT_ID();

INSERT INTO Location (building, room) VALUES ('Engineering Building', 'Computer Lab B');
SET @loc_eng = LAST_INSERT_ID();

INSERT INTO Location (building, room) VALUES ('Recreation Center', 'Gym');
SET @loc_gym = LAST_INSERT_ID();

INSERT INTO Location (building, room) VALUES ('Student Union', 'Cafeteria');
SET @loc_caf = LAST_INSERT_ID();

-- Link items to locations via At table
INSERT INTO At (location_id, item_id) VALUES 
    (@loc_lib, @found_iphone),
    (@loc_lib, @lost_iphone),
    (@loc_eng, @found_laptop),
    (@loc_eng, @lost_laptop),
    (@loc_gym, @found_bottle),
    (@loc_gym, @lost_bottle),
    (@loc_caf, @found_backpack);

SELECT 'Test data inserted successfully!' as Status;
SELECT '8 items created (4 found, 4 lost)' as Items;
SELECT 'Run matching algorithm to see results: php run_matching.php' as NextStep;
