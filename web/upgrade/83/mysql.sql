-- Add an "approved" flag for self-registered users awaiting admin approval.
-- Existing rows default to 1 (already approved) so upgrading does not lock
-- out any existing accounts. Only new self-registrations are inserted with 0.

ALTER TABLE %DB_TBL_PREFIX%users
ADD COLUMN approved tinyint DEFAULT 1 NOT NULL AFTER reset_key_expiry;
