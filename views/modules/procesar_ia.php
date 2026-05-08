<?php
require_once __DIR__ . '/../../vendor/autoload.php';
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

header('Content-Type: application/json');

// 1. Asegúrate de que esta Key sea la de https://aistudio.google.com/
$apiKey = 'AIzaSyDCRQIAQ5QDzGFCAa2s-Ml_PTT15jWCsc8';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['imagen'])) {

    $imagePath = $_FILES['imagen']['tmp_name'];
    $base64Image = base64_encode(file_get_contents($imagePath));

    // Detectar MimeType para evitar errores de formato
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $imagePath);
    finfo_close($finfo);

    // Cliente configurado para Laragon (Sin verificar SSL y con Timeout largo)
    $client = new Client([
        'timeout' => 60.0,
        'verify' => false
    ]);

    $prompt = "Analiza la imagen. Extrae productos de inventario. Responde ÚNICAMENTE un JSON con esta estructura: {\"productos\": [{\"nombre\": \"\", \"precio_compra1\": 0, \"precio_compra2\": 0, \"precio_venta\": 0, \"stock_actual\": 0}]}. No añadas texto adicional.";

    try {
        // RUTA ESTABLE PARA DESARROLLO (Gemini 1.5 Flash)
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . trim($apiKey);

        $response = $client->post($url, [
            'json' => [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt],
                            [
                                'inline_data' => [
                                    'mime_type' => $mimeType,
                                    'data' => $base64Image
                                ]
                            ]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'temperature' => 0.1
                ]
            ]
        ]);

        $body = json_decode($response->getBody(), true);

        if (isset($body['candidates'][0]['content']['parts'][0]['text'])) {
            $textoRespuesta = $body['candidates'][0]['content']['parts'][0]['text'];

            // Limpiamos el JSON por si la IA envía ```json ... ```
            $jsonLimpio = trim(str_replace(['```json', '```'], '', $textoRespuesta));
            $data = json_decode($jsonLimpio, true);

            echo json_encode([
                'success' => true,
                'productos' => $data['productos'] ?? []
            ]);
        } else {
            echo json_encode(['error' => 'La IA no devolvió datos estructurados', 'debug' => $body]);
        }

    } catch (RequestException $e) {
        $msg = $e->hasResponse() ? (string) $e->getResponse()->getBody() : $e->getMessage();
        echo json_encode(['error' => 'Error de conexión con Google', 'detalle' => $msg]);
    }
}