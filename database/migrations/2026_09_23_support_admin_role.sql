-- Migración: rol de "administrador de soporte" (ve y responde TODAS las
-- conversaciones de "Soporte técnico / Asistente SixSeven", no solo la
-- propia). Para bases de datos que ya existían antes de este cambio
-- (schema.sql ya incluye la columna para instalaciones nuevas).

ALTER TABLE users ADD COLUMN is_support_admin TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER role;

-- Marca como administrador de soporte a la cuenta que opera la plataforma
-- (ajustá el email si corresponde a otra cuenta).
UPDATE users SET is_support_admin = 1 WHERE email = 'lodelalopetshop@gmail.com';
