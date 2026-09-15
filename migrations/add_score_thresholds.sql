-- Migration: Add score threshold columns to exams table
-- Run this in phpMyAdmin or MySQL client

ALTER TABLE `exams` 
ADD COLUMN `score_threshold_high` INT NOT NULL DEFAULT 70 COMMENT 'Score >= this value shows green (pass)',
ADD COLUMN `score_threshold_medium` INT NOT NULL DEFAULT 50 COMMENT 'Score >= this value shows yellow (warning)';
