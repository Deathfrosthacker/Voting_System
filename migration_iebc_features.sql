-- Create regions table
CREATE TABLE IF NOT EXISTS regions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create affiliations table
CREATE TABLE IF NOT EXISTS affiliations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description VARCHAR(255) DEFAULT NULL,
    color_code VARCHAR(7) DEFAULT '#2563eb',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default regions
INSERT IGNORE INTO regions (name, description) VALUES
('Main Campus', 'Primary campus location'),
('City Campus', 'Urban/satellite campus'),
('Online/Distance', 'Remote learning participants');

-- Insert default affiliations
INSERT IGNORE INTO affiliations (name, description, color_code) VALUES
('Independent', 'No institutional affiliation', '#6b7280'),
('School of Computing', 'Computing and IT department', '#3b82f6'),
('School of Business', 'Business and Economics', '#10b981'),
('School of Engineering', 'Engineering department', '#f59e0b');

-- Add region_id to users table
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS region_id INT DEFAULT NULL,
ADD FOREIGN KEY (region_id) REFERENCES regions(id) ON DELETE SET NULL;

-- Add affiliation fields to candidates table
ALTER TABLE candidates
ADD COLUMN IF NOT EXISTS affiliation_id INT DEFAULT NULL,
ADD COLUMN IF NOT EXISTS is_independent TINYINT(1) DEFAULT 0,
ADD FOREIGN KEY (affiliation_id) REFERENCES affiliations(id) ON DELETE SET NULL;

-- Add region_id to positions table
ALTER TABLE positions
ADD COLUMN IF NOT EXISTS region_id INT DEFAULT NULL,
ADD FOREIGN KEY (region_id) REFERENCES regions(id) ON DELETE SET NULL;

-- Update election_results table
ALTER TABLE election_results
ADD COLUMN IF NOT EXISTS region_name VARCHAR(100) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS affiliation_name VARCHAR(100) DEFAULT NULL;