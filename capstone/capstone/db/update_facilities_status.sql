-- Migration script to add 'archived' status to facilities table
-- Run this script to update your existing database

ALTER TABLE facilities 
MODIFY COLUMN status ENUM('available', 'unavailable', 'archived') DEFAULT 'available';

-- This will allow the archive functionality to work properly
