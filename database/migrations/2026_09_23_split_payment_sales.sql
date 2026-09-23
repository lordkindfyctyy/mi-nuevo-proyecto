ALTER TABLE sales
    MODIFY payment_method ENUM('cash', 'card', 'transfer', 'qr', 'other', 'mixed') NOT NULL DEFAULT 'cash';

CREATE TABLE IF NOT EXISTS sale_payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sale_id INT UNSIGNED NOT NULL,
    payment_method ENUM('cash', 'card', 'transfer', 'qr', 'other') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_sale_payments_sale (sale_id),
    CONSTRAINT fk_sale_payments_sale FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
