-- El asistente de IA pasa a ser un add-on pago ($5.000 ARS/mes): vuelve a
-- estar apagado por defecto para negocios nuevos. ai_assistant_requested_at
-- evita repetir la notificación cada vez que abre el modal.
-- ai_assistant_granted_at marca si el negocio TIENE el add-on contratado
-- (lo carga el admin a mano tras cobrarlo) — separado de
-- ai_assistant_enabled, que es el on/off del día a día que el negocio ya
-- habilitado puede pausar y reactivar él mismo sin perder el acceso.
ALTER TABLE tenants
    MODIFY COLUMN ai_assistant_enabled TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN ai_assistant_requested_at DATETIME NULL AFTER ai_assistant_enabled,
    ADD COLUMN ai_assistant_granted_at DATETIME NULL AFTER ai_assistant_requested_at;

-- "Grandfathea" a los negocios que ya lo tenían activado antes de este
-- cambio (ej. el tuyo), para que no pierdan el acceso ni el control del
-- on/off — sin esto, el panel les mostraría el cartel de venta de nuevo
-- aunque ya lo tengan andando.
UPDATE tenants SET ai_assistant_granted_at = NOW() WHERE ai_assistant_enabled = 1;
