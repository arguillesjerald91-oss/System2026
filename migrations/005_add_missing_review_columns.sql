-- =====================================================
-- Migration 005: Add missing review tracking columns to pre_enrollment_applications
-- =====================================================
-- This migration adds `reviewed_by` and `reviewed_at` columns to support
-- admin approval/rejection tracking in Module 1: Pre-Enrollment.
-- The table already has `review_date`; this adds `reviewed_by` (admin ID)
-- and `reviewed_at` (timestamp) to align with admin management code.
-- It also extends the `application_status` ENUM to include 'Pending'.
-- =====================================================

-- Add reviewed_by column (FK to users table)
ALTER TABLE pre_enrollment_applications
    ADD COLUMN IF NOT EXISTS reviewed_by INT(11) DEFAULT NULL AFTER remarks;

-- Add reviewed_at column (timestamp of review decision)
ALTER TABLE pre_enrollment_applications
    ADD COLUMN IF NOT EXISTS reviewed_at TIMESTAMP NULL DEFAULT NULL AFTER reviewed_by;

-- Extend application_status ENUM to include 'Pending'
-- Existing: 'Draft','Submitted','Under Review','For Interview','Qualified','Not Qualified','Waitlisted','Enrolled'
-- Adding 'Pending' between 'Submitted' and 'Under Review' to match admin workflow
ALTER TABLE pre_enrollment_applications
    MODIFY COLUMN application_status ENUM('Draft','Submitted','Pending','Under Review','For Interview','Qualified','Not Qualified','Waitlisted','Enrolled') DEFAULT 'Draft';

-- =====================================================
-- End of Migration 005
-- =====================================================
