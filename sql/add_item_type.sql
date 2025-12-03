-- Add item_type column to distinguish between found items and lost item reports
-- Run this migration: mysql -u root -p campus_lost_found < add_item_type.sql

ALTER TABLE Item 
ADD COLUMN item_type ENUM('found', 'lost') NOT NULL DEFAULT 'found'
AFTER photo;

-- All existing items are assumed to be "found" items (the original behavior)
-- New items can be marked as either 'found' or 'lost'
