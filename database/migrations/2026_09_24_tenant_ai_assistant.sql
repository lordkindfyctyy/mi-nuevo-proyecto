ALTER TABLE tenants
    ADD COLUMN ai_assistant_enabled TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER whatsapp_phone;
