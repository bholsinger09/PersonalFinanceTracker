-- Database migrations for Finance Tracker
-- These handle schema updates for existing databases

-- Migration 001: Add date column to transactions table if it doesn't exist
-- Check if the column exists first to avoid errors
PRAGMA table_info(transactions);

-- Add date column if it doesn't exist (SQLite specific approach)
-- We'll handle this in PHP code since SQLite doesn't have IF NOT EXISTS for columns

-- Migration 002: Add category column to transactions table if it doesn't exist
-- (This might also be missing in older databases)

-- Migration 003: Create categories table if it doesn't exist
-- (Handled by schema.sql already)

-- Migration 004: Add indexes if they don't exist
-- (Handled by schema.sql already with IF NOT EXISTS)