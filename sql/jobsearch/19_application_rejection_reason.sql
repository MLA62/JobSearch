ALTER TABLE applications
    ADD COLUMN rejection_reason VARCHAR(249) NULL AFTER status;
