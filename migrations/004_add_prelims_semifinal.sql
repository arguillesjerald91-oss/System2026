-- Add prelims and semi_finals columns to grades table
ALTER TABLE grades ADD COLUMN prelims DECIMAL(5, 2) DEFAULT 0 AFTER StudID;
ALTER TABLE grades ADD COLUMN semi_finals DECIMAL(5, 2) DEFAULT 0 AFTER midterm;
