-- Migración: recuperación de contraseña + teléfono personal de usuario.
-- Para bases de datos que ya existían antes de este cambio (schema.sql ya
-- incluye estas definiciones para instalaciones nuevas). Ejecutar una sola
-- vez contra la base de datos existente (local y luego producción).

ALTER TABLE users ADD COLUMN phone VARCHAR(30) NULL AFTER email;
ALTER TABLE users ADD KEY idx_users_phone (phone);

CREATE TABLE IF NOT EXISTS password_resets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_password_resets_user (user_id),
    KEY idx_password_resets_token_hash (token_hash),
    CONSTRAINT fk_password_resets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
