-- Add is_active column to schedules table if it doesn't exist
ALTER TABLE schedules ADD COLUMN IF NOT EXISTS is_active TINYINT(1) NOT NULL DEFAULT 1;

-- Add is_active column to counselors table if it doesn't exist
ALTER TABLE counselors ADD COLUMN IF NOT EXISTS is_active TINYINT(1) NOT NULL DEFAULT 1;

-- Add is_active column to supporters table if it doesn't exist
ALTER TABLE supporters ADD COLUMN IF NOT EXISTS is_active TINYINT(1) NOT NULL DEFAULT 1;

-- Add is_active column to advisors table if it doesn't exist
ALTER TABLE advisors ADD COLUMN IF NOT EXISTS is_active TINYINT(1) NOT NULL DEFAULT 1;

-- Add is_active column to users table if it doesn't exist
ALTER TABLE users ADD COLUMN IF NOT EXISTS is_active TINYINT(1) NOT NULL DEFAULT 1; 