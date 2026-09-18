<?php

require_once __DIR__ . '/../../config/config.php';

/**
 * Envía un audio corto (comando de voz del mostrador) más el catálogo activo
 * del negocio a Gemini, y devuelve el JSON estructurado que arma el modelo
 * (items detectados + descuento opcional + transcripción).
 *
 * Esta clase solo interpreta el audio; nunca decide qué se agrega al
 * carrito. Quien la llama (api/procesar_comando_voz.php) revalida cada
 * producto/cantidad contra la base de datos real antes de usarlos.
 */
class GeminiVoiceCommandParser
{
    private const ENDPOINT_TEMPLATE = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s';

    // Gemini devuelve estos códigos cuando está momentáneamente saturado
    // (no son errores de la clave, la cuota ni el request); vale la pena
    // reintentar un par de veces antes de mostrarle el error al vendedor.
    private const RETRYABLE_HTTP_CODES = [429, 500, 502, 503, 504];
    private const MAX_ATTEMPTS = 3;
    private const RETRY_DELAY_SECONDS = 2;

    /**
     * @param array<int, array{id:int, sku:string, nombre:string, precio:float, unidad:string}> $catalog
     * @return array{items?: array<int, array<string, mixed>>, descuento?: array<string, mixed>|null, transcripcion?: string}
     */
    public static function parse(string $audioBytes, string $mimeType, array $catalog): array
    {
        if (!AI_VOICE_API_KEY) {
            throw new RuntimeException('El comando de voz por IA no está configurado en este servidor.');
        }

        $payload = [
            'contents' => [[
                'parts' => [
                    ['text' => self::buildPrompt($catalog)],
                    ['inline_data' => [
                        'mime_type' => $mimeType,
                        'data' => base64_encode($audioBytes),
                    ]],
                ],
            ]],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
                'responseSchema' => self::responseSchema(),
                'temperature' => 0,
            ],
        ];

        $url = sprintf(self::ENDPOINT_TEMPLATE, AI_VOICE_MODEL, AI_VOICE_API_KEY);
        $jsonPayload = json_encode($payload);

        $lastMessage = 'No se pudo contactar al servicio de IA.';
        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_POSTFIELDS => $jsonPayload,
                CURLOPT_TIMEOUT => 30,
            ]);
            $responseBody = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($responseBody === false) {
                $lastMessage = 'No se pudo contactar al servicio de IA (' . $curlError . ').';
                if ($attempt < self::MAX_ATTEMPTS) {
                    sleep(self::RETRY_DELAY_SECONDS);
                    continue;
                }
                throw new RuntimeException($lastMessage);
            }

            $decoded = json_decode($responseBody, true);

            if ($httpCode >= 400) {
                $lastMessage = $decoded['error']['message'] ?? ('Error HTTP ' . $httpCode . ' del servicio de IA.');
                if (in_array($httpCode, self::RETRYABLE_HTTP_CODES, true) && $attempt < self::MAX_ATTEMPTS) {
                    sleep(self::RETRY_DELAY_SECONDS);
                    continue;
                }
                throw new RuntimeException($lastMessage);
            }

            break;
        }

        $text = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? null;
        if (!$text) {
            throw new RuntimeException('La IA no devolvió ninguna respuesta interpretable.');
        }

        $result = json_decode($text, true);
        if (!is_array($result)) {
            throw new RuntimeException('La respuesta de la IA no tiene un formato JSON válido.');
        }

        return $result;
    }

    private static function buildPrompt(array $catalog): string
    {
        $catalogJson = json_encode($catalog, JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
Sos un asistente que interpreta comandos de voz en español para el mostrador de un negocio (punto de venta). Vas a recibir un audio corto con lo que dijo el vendedor mientras cargaba una venta.

Catálogo de productos activos de este negocio (usá SOLO estos productos; elegí el que mejor coincida por nombre o SKU aunque el vendedor lo pronuncie distinto, incompleto o con errores):
$catalogJson

Instrucciones:
- Identificá cada producto mencionado y devolvé su "producto_id" exacto del catálogo (o "sku" si no encontrás un id claro).
- Cantidad: si el vendedor dice una cantidad de unidades o de kilos, ponela en "cantidad" (admite decimales, ej. 0.5). Si en cambio dice un monto en pesos (ej. "deme 5000 pesos de jamón"), poné ese número en "monto" y dejá "cantidad" en null.
- Descuento: si el vendedor menciona un descuento (ej. "con 10% de descuento" o "restale 500 pesos"), completá "descuento" con "tipo" ("percent" o "fixed") y "valor". Si no menciona ningún descuento, "descuento" debe ser null.
- Si no podés identificar con confianza ningún producto del catálogo, devolvé "items" como una lista vacía.
- Incluí en "transcripcion" una transcripción literal aproximada de lo que se escuchó, en español.
PROMPT;
    }

    private static function responseSchema(): array
    {
        return [
            'type' => 'OBJECT',
            'properties' => [
                'items' => [
                    'type' => 'ARRAY',
                    'items' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'producto_id' => ['type' => 'INTEGER', 'nullable' => true],
                            'sku' => ['type' => 'STRING', 'nullable' => true],
                            'nombre_detectado' => ['type' => 'STRING'],
                            'cantidad' => ['type' => 'NUMBER', 'nullable' => true],
                            'monto' => ['type' => 'NUMBER', 'nullable' => true],
                        ],
                        'required' => ['nombre_detectado'],
                    ],
                ],
                'descuento' => [
                    'type' => 'OBJECT',
                    'nullable' => true,
                    'properties' => [
                        'tipo' => ['type' => 'STRING', 'enum' => ['percent', 'fixed']],
                        'valor' => ['type' => 'NUMBER'],
                    ],
                    'required' => ['tipo', 'valor'],
                ],
                'transcripcion' => ['type' => 'STRING'],
            ],
            'required' => ['items'],
        ];
    }
}
