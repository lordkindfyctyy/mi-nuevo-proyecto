ALTER TABLE tenants
    ADD COLUMN logo_path VARCHAR(255) NULL AFTER address,
    ADD COLUMN show_location_on_logo TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER logo_path;

ALTER TABLE contact_messages
    MODIFY COLUMN source ENUM('contact_form','live_chat','catalog_chat','catalog_lead') NOT NULL DEFAULT 'contact_form';
