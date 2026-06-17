-- ============================================================================
-- MIGRATION: Add UNIQUE constraint to votes table to prevent duplicate voting
-- This fixes the race condition vulnerability identified in vote.php
-- ============================================================================

-- First, check if the constraint already exists and remove duplicates if any
-- This query finds and reports duplicate votes (same user voting for same position)
SELECT user_id, candidate_id, COUNT(*) as vote_count
FROM votes
GROUP BY user_id, candidate_id
HAVING vote_count > 1;

-- To remove duplicate votes (keep only the earliest vote per user per position),
-- uncomment and run the following:
-- 
-- CREATE TEMPORARY TABLE votes_to_keep AS
-- SELECT MIN(id) as id
-- FROM votes
-- GROUP BY user_id, candidate_id;
-- 
-- DELETE FROM votes WHERE id NOT IN (SELECT id FROM votes_to_keep);
-- 
-- DROP TEMPORARY TABLE votes_to_keep;

-- ============================================================================
-- ADD UNIQUE CONSTRAINT
-- This prevents the same user from voting for the same candidate twice
-- ============================================================================
ALTER TABLE votes 
ADD UNIQUE KEY unique_user_candidate_vote (user_id, candidate_id);

-- ============================================================================
-- ALTERNATIVE: If you want to prevent duplicate votes at the position level
-- (user can only vote once per position, regardless of candidate),
-- use this approach instead (requires a position column in votes table):
--
-- ALTER TABLE votes ADD COLUMN position_name VARCHAR(255) AFTER candidate_id;
-- 
-- UPDATE votes v 
-- JOIN candidates c ON v.candidate_id = c.id 
-- SET v.position_name = c.position;
-- 
-- ALTER TABLE votes 
-- ADD UNIQUE KEY unique_vote_per_position (user_id, position_name);
-- ============================================================================

-- ============================================================================
-- ALSO RECOMMENDED: Add indexes for performance
-- ============================================================================
ALTER TABLE votes ADD INDEX idx_user_id (user_id);
ALTER TABLE votes ADD INDEX idx_candidate_id (candidate_id);

-- ============================================================================
-- Ensure positions table has unique position names (case-insensitive handled in code)
-- ============================================================================
ALTER TABLE positions ADD UNIQUE KEY unique_position_name (position_name);

-- ============================================================================
-- Ensure users table has unique constraints on id_number and email
-- ============================================================================
ALTER TABLE users ADD UNIQUE KEY unique_id_number (id_number);
ALTER TABLE users ADD UNIQUE KEY unique_email (email);