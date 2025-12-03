-- Add photo column to Item table
-- Run this if your Item table doesn't have a photo column yet

USE campus_lost_found;

ALTER TABLE Item ADD COLUMN photo VARCHAR(512) DEFAULT NULL AFTER description;
