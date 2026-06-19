 -- Apply unique constraints to email and id_number in the users table
 -- This ensures that each email and id_number is unique across all users, preventing duplicates.
ALTER TABLE users
ADD CONSTRAINT unique_email UNIQUE (email),
ADD CONSTRAINT unique_id_number UNIQUE (id_number);

