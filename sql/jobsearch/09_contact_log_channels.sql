ALTER TABLE contact_logs
    MODIFY channel ENUM('email','external_email','onsite','phone','meeting','video','whatsapp','sms','message','letter','note','other') NOT NULL;
