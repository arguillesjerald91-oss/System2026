-- Ensure tuition_fees table exists with student-specific schema including separate rates for lec/lab/units
-- This migration creates or alters the tuition_fees table to support per-student tuition fees with flexible billing

CREATE TABLE IF NOT EXISTS tuition_fees (
  id INT AUTO_INCREMENT PRIMARY KEY,
  StudID VARCHAR(50) NOT NULL UNIQUE,
  lec DECIMAL(5, 2) DEFAULT 0,
  rate_lec DECIMAL(10, 2) DEFAULT 1000,
  lab DECIMAL(5, 2) DEFAULT 0,
  rate_lab DECIMAL(10, 2) DEFAULT 1000,
  units DECIMAL(5, 2) DEFAULT 0,
  rate_unit DECIMAL(10, 2) DEFAULT 1000,
  total_fee DECIMAL(10, 2) NOT NULL,
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (StudID) REFERENCES student(StudID) ON DELETE CASCADE
);

-- If the table already exists and doesn't have these columns, add them
ALTER TABLE tuition_fees 
  ADD COLUMN IF NOT EXISTS StudID VARCHAR(50) UNIQUE,
  ADD COLUMN IF NOT EXISTS lec DECIMAL(5, 2) DEFAULT 0,
  ADD COLUMN IF NOT EXISTS rate_lec DECIMAL(10, 2) DEFAULT 1000,
  ADD COLUMN IF NOT EXISTS lab DECIMAL(5, 2) DEFAULT 0,
  ADD COLUMN IF NOT EXISTS rate_lab DECIMAL(10, 2) DEFAULT 1000,
  ADD COLUMN IF NOT EXISTS units DECIMAL(5, 2) DEFAULT 0,
  ADD COLUMN IF NOT EXISTS rate_unit DECIMAL(10, 2) DEFAULT 1000,
  ADD COLUMN IF NOT EXISTS total_fee DECIMAL(10, 2),
  ADD COLUMN IF NOT EXISTS notes TEXT;

-- Create unique constraint on StudID if it doesn't already exist
-- This ensures each student has only one tuition fee record
ALTER TABLE tuition_fees 
  ADD CONSTRAINT IF NOT EXISTS uc_student_tuition UNIQUE (StudID);


