-- A partir de ahora, los negocios que se registren tienen el asistente de
-- IA del catálogo activado por defecto. Los negocios ya existentes no se
-- tocan (mantienen el valor que ya tenían).
ALTER TABLE tenants
    MODIFY COLUMN ai_assistant_enabled TINYINT(1) UNSIGNED NOT NULL DEFAULT 1;
