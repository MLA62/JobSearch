ALTER TABLE applications
    ADD COLUMN application_url VARCHAR(1000) NULL AFTER channel,
    ADD COLUMN portal_account VARCHAR(254) NULL AFTER application_url,
    ADD COLUMN online_notes TEXT NULL AFTER reference_number;
