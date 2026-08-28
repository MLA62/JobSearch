ALTER TABLE user_smtp_settings
    ADD COLUMN save_sent_copy TINYINT(1) NOT NULL DEFAULT 1 AFTER mail_footer,
    ADD COLUMN imap_host VARCHAR(255) NULL AFTER save_sent_copy,
    ADD COLUMN imap_port SMALLINT UNSIGNED NOT NULL DEFAULT 993 AFTER imap_host,
    ADD COLUMN imap_encryption ENUM('tls','ssl','none') NOT NULL DEFAULT 'ssl' AFTER imap_port,
    ADD COLUMN imap_sent_folder VARCHAR(255) NULL AFTER imap_encryption;
