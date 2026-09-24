-- Permite decidir, producto por producto, si aparece en el catálogo online
-- (para clientes) o si es solo para vender por mostrador desde Vender.
-- Por defecto queda visible (1), para no ocultar de golpe el catálogo de
-- nadie que ya tenga productos cargados.
ALTER TABLE products
    ADD COLUMN show_in_catalog TINYINT(1) UNSIGNED NOT NULL DEFAULT 1 AFTER status;
