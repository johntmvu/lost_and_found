-- Add status field to Claim table and item status to Item table
-- Run this to enable claim approval workflow

USE campus_lost_found;

-- Add status to Claim table
ALTER TABLE Claim ADD COLUMN status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending' AFTER photo;

-- Add status to Item table to track if item has been returned
ALTER TABLE Item ADD COLUMN status ENUM('available', 'claimed', 'returned') DEFAULT 'available' AFTER photo;

-- Add timestamp for when claims are reviewed
ALTER TABLE Claim ADD COLUMN reviewed_at TIMESTAMP NULL AFTER status;
