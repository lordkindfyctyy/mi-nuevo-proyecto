<?php

require_once __DIR__ . '/debug_log.php';

/**
 * Calls Gemini (Google AI Studio) to generate a reply for the catalog's
 * customer-facing AI assistant, grounded in the tenant's real product data
 * so it doesn't invent prices or stock. Reintenta una vez ante fallas
 * transitorias (503 de alta demanda, timeouts) y, si aun asi no logra
 * respuesta, devuelve un mensaje de resguardo prolijo en vez de null, para
 * que el cliente nunca se quede sin respuesta. Solo devuelve null cuando la
 * IA no esta configurada (sin API key) — en ese caso el llamador ya sabe
 * que no debe intentar responder.
 *
 * @param array $tenant Tenant row (needs at least 'name').
 * @param array $products Tenant's products (name, price, stock_quantity, sale_unit, brand, category, status).
 * @param array $recentMessages Prior contact_messages rows for this conversation, oldest first (needs 'sender', 'message').
 * @param string $customerMessage The new message the customer just sent.
 */
function gemini_catalog_reply(array $tenant, array $products, array $recentMessages, string $customerMessage): ?string
{
    if (GEMINI_API_KEY === '') {
        return null;
    }

    $catalogLines = [];
    foreach ($products as $product) {
        if (($product['status'] ?? 'active') !== 'active' || empty($product['show_in_catalog'])) {
            continue;
        }
        if (count($catalogLines) >= 200) {
            break;
        }

        $unit = ($product['sale_unit'] ?? 'unit') === 'weight' ? 'kg' : 'un.';
        $stock = (float) ($product['stock_quantity'] ?? 0);
        $stockLabel = $stock > 0
            ? rtrim(rtrim(number_format($stock, 3), '0'), '.') . " $unit disponibles"
            : 'sin stock';

        $catalogLines[] = sprintf(
            '- %s%s: $%s, %s%s',
            $product['name'],
            !empty($product['brand']) ? ' (' . $product['brand'] . ')' : '',
            number_format((float) $product['price'], 2),
            $stockLabel,
            !empty($product['category']) ? ', categoría: ' . $product['category'] : ''
        );
    }

    $tenantName = $tenant['name'] ?? 'este comercio';
    $systemPrompt = "Sos el asistente virtual de atención al cliente de \"{$tenantName}\", un comercio que vende a través de su catálogo online en la plataforma SixSeven.\n\n"
        . "CÓMO HABLAR:\n"
        . "- Español rioplatense con \"vos\", cordial, profesional y cercano — como un buen vendedor de local que atiende bien a la gente, nunca cortante ni robótico.\n"
        . "- Respuestas cortas y claras (2 a 4 oraciones). Si te saludan o hacen una consulta general, respondé con calidez antes de ir al grano.\n"
        . "- Sin markdown ni emojis (el chat no los muestra bien).\n\n"
        . "REGLAS DE CONTENIDO:\n"
        . "- Para precios y stock, usá ÚNICAMENTE la información del catálogo de abajo — nunca inventes productos, precios ni disponibilidad que no estén listados ahí.\n"
        . "- Si te preguntan algo que no podés responder con este catálogo (formas de pago, envíos, horarios, o un producto que no está en la lista), decilo con sinceridad, sin inventar una respuesta, y avisá que el vendedor se va a poner en contacto a la brevedad.\n\n"
        . "Catálogo actual de \"{$tenantName}\":\n" . ($catalogLines ? implode("\n", $catalogLines) : '(el catálogo está vacío por el momento)');

    $contents = [];
    foreach (array_slice($recentMessages, -8) as $msg) {
        $contents[] = [
            'role' => ($msg['sender'] ?? 'visitor') === 'visitor' ? 'user' : 'model',
            'parts' => [['text' => (string) $msg['message']]],
        ];
    }
    $contents[] = ['role' => 'user', 'parts' => [['text' => $customerMessage]]];

    $payload = [
        'system_instruction' => ['parts' => [['text' => $systemPrompt]]],
        'contents' => $contents,
        'generationConfig' => [
            'maxOutputTokens' => 300,
            'temperature' => 0.4,
            // Esto es una simple respuesta de FAQ con datos de catálogo, no un
            // problema que necesite razonamiento — desactivarlo baja bastante
            // la latencia y el costo por mensaje.
            'thinkingConfig' => ['thinkingBudget' => 0],
        ],
    ];

    $maxAttempts = 2;
    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        $text = gemini_send_request($payload);
        if ($text !== null) {
            return $text;
        }
        if ($attempt < $maxAttempts) {
            // Los 503 de "alta demanda" de Gemini suelen ser picos momentáneos
            // (así lo dice el propio mensaje de error de Google) — un breve
            // reintento suele alcanzar para que el cliente reciba respuesta.
            usleep(700000);
        }
    }

    app_debug_log('[gemini] sin respuesta tras ' . $maxAttempts . ' intentos, se envía mensaje de resguardo');

    // Para que el cliente nunca se quede sin respuesta ("colgado"), si la IA
    // no pudo responder tras reintentar le devolvemos un mensaje de
    // resguardo prolijo en vez de silencio.
    return "¡Gracias por tu mensaje! En este momento no puedo consultarlo automáticamente, pero ya quedó registrado y {$tenantName} te va a responder a la brevedad.";
}

/**
 * Hace un único intento de llamada a Gemini. Devuelve el texto de la
 * respuesta, o null si falló (red, HTTP distinto de 200, o respuesta sin
 * texto utilizable) para que el llamador decida si reintentar.
 */
function gemini_send_request(array $payload): ?string
{
    $ch = curl_init('https://generativelanguage.googleapis.com/v1beta/models/gemini-3.5-flash:generateContent?key=' . GEMINI_API_KEY);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        // Un timeout más corto por intento, para que aun con un reintento
        // de por medio el cliente no espere una eternidad la respuesta.
        CURLOPT_TIMEOUT => 10,
        // Algunos entornos tienen rutas IPv6 rotas/muy lentas hacia Google
        // que hacen colgar la conexión hasta el timeout sin esto.
        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        app_debug_log('[gemini] curl error: ' . $curlError);
        return null;
    }

    $data = json_decode($response, true);
    if ($httpCode !== 200 || !is_array($data)) {
        app_debug_log('[gemini] HTTP ' . $httpCode . ': ' . substr((string) $response, 0, 500));
        return null;
    }

    $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
    if (!is_string($text) || trim($text) === '') {
        app_debug_log('[gemini] respuesta sin texto utilizable: ' . substr((string) $response, 0, 500));
        return null;
    }

    return trim($text);
}
