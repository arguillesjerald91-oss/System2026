-- Idempotent migration to add rate_per_unit column to tuition_fees table
-- This supports the new flexible tuition model for regular and irregular students

ALTER TABLE tuition_fees 
ADD COLUMN IF NOT EXISTS rate_per_unit DECIMAL(10, 2) DEFAULT 0;

-- Optionally populate rate_per_unit from total_fee for existing records
-- (only if total_fee exists and rate_per_unit is still 0)
UPDATE tuition_fees 
SET rate_per_unit = CASE 
    WHEN total_fee IS NOT NULL AND total_fee > 0 THEN total_fee / 18
    ELSE 0
END
WHERE rate_per_unit = 0 AND total_fee IS NOT NULL;
