<?php

/**
 * Public URL for a sale's web receipt (public/remito.php), given its
 * public_token (see Sale::getOrCreatePublicToken()).
 */
function receipt_public_url(string $token): string
{
    return BASE_URL . '/remito.php?token=' . urlencode($token);
}

/**
 * A friendly, structured WhatsApp message (wa.me link, no phone number so
 * the seller picks the recipient from their own WhatsApp) summarizing the
 * sale and pointing the customer to the full web receipt.
 */
function receipt_whatsapp_share_url(string $tenantName, array $sale, string $remitoUrl): string
{
    $message = "¡Hola! Te comparto el comprobante de tu compra en {$tenantName}.\n"
        . 'Venta #' . (int) $sale['id'] . ' · ' . date('d/m/Y H:i', strtotime($sale['created_at'])) . "\n"
        . 'Total: $' . number_format((float) $sale['total'], 2) . "\n\n"
        . "Podés ver el remito completo acá:\n{$remitoUrl}\n\n"
        . '¡Gracias por tu compra!';

    return 'https://wa.me/?text=' . rawurlencode($message);
}
