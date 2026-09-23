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
 * The WhatsApp message text (no URL wrapping) summarizing the sale and
 * pointing the customer to the full web receipt. Split out from
 * receipt_whatsapp_share_url() so the frontend can rebuild the wa.me link
 * client-side once the seller types the customer's phone number, without a
 * page reload.
 */
function receipt_whatsapp_message(string $tenantName, array $sale, string $remitoUrl): string
{
    return "¡Hola! Te comparto el comprobante de tu compra en {$tenantName}.\n"
        . 'Venta #' . (int) $sale['id'] . ' · ' . date('d/m/Y H:i', strtotime($sale['created_at'])) . "\n"
        . 'Total: $' . number_format((float) $sale['total'], 2) . "\n\n"
        . "Podés ver el remito completo acá:\n{$remitoUrl}\n\n"
        . '¡Gracias por tu compra!';
}

/**
 * A friendly, structured WhatsApp message summarizing the sale and pointing
 * the customer to the full web receipt. Without $phone, it's a wa.me link
 * with no number so the seller picks the recipient from their own WhatsApp;
 * with $phone (customer's WhatsApp number, any formatting), it targets that
 * contact directly.
 */
function receipt_whatsapp_share_url(string $tenantName, array $sale, string $remitoUrl, ?string $phone = null): string
{
    $message = receipt_whatsapp_message($tenantName, $sale, $remitoUrl);
    $digits = $phone !== null ? preg_replace('/\D+/', '', $phone) : '';

    return 'https://wa.me/' . $digits . '?text=' . rawurlencode($message);
}
