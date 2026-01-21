<?php
/**
 * Script para consultar formato de países en CloudBeds
 */

// Cargar configuración de WordPress para acceder a la BD
require_once(__DIR__ . '/../../../../../wp-load.php');

global $wpdb;

echo "=== Test Formato País CloudBeds ===\n\n";

// Obtener configuración de CloudBeds
$config = $wpdb->get_row("SELECT * FROM wp_hotels_config WHERE status = '1' LIMIT 1");

if (!$config) {
    echo "ERROR: No hay configuración de CloudBeds activa\n";
    exit(1);
}

$access_token = $config->access_token;
$refresh_token = $config->refresh_token;

echo "Access Token: " . substr($access_token, 0, 30) . "...\n\n";

// Primero, veamos qué países tenemos guardados en la BD local
echo "=== PAÍSES EN BD LOCAL (wp_hotels_transactions) ===\n\n";

$paises_locales = $wpdb->get_results("
    SELECT DISTINCT country, COUNT(*) as cantidad
    FROM wp_hotels_transactions
    WHERE country IS NOT NULL AND country != ''
    GROUP BY country
    ORDER BY cantidad DESC
    LIMIT 50
");

if ($paises_locales) {
    echo sprintf("%-30s | %-10s\n", "COUNTRY (CloudBeds)", "CANTIDAD");
    echo str_repeat("-", 45) . "\n";
    foreach ($paises_locales as $p) {
        echo sprintf("%-30s | %-10s\n", $p->country, $p->cantidad);
    }
} else {
    echo "No hay transacciones con país registrado.\n";
}

echo "\n\n=== CONSULTANDO API CLOUDBEDS (getTransactions) ===\n\n";

// Consultar transacciones recientes de CloudBeds
$url = "https://hotels.cloudbeds.com/api/v1.2/getTransactions";
$params = [
    'propertyID' => 'all',
    'pageNumber' => 1,
    'pageSize' => 10
];

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url . '?' . http_build_query($params),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $access_token,
        'Content-Type: application/json'
    ],
    CURLOPT_TIMEOUT => 30
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $httpCode\n";

if ($curlError) {
    echo "CURL Error: $curlError\n";
    exit(1);
}

$data = json_decode($response, true);

if (isset($data['success']) && $data['success'] === true) {
    echo "Transacciones obtenidas: " . count($data['data']) . "\n\n";

    // Obtener guestIDs únicos
    $guestIds = [];
    foreach ($data['data'] as $t) {
        if (!empty($t['guestID']) && !in_array($t['guestID'], $guestIds)) {
            $guestIds[] = $t['guestID'];
        }
    }

    echo "=== CONSULTANDO DATOS DE HUÉSPEDES ===\n\n";

    // Consultar datos de cada huésped
    foreach (array_slice($guestIds, 0, 5) as $guestId) {
        $guestUrl = "https://hotels.cloudbeds.com/api/v1.2/getGuest";
        $guestParams = ['guestID' => $guestId];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $guestUrl . '?' . http_build_query($guestParams),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $access_token,
                'Content-Type: application/json'
            ],
            CURLOPT_TIMEOUT => 30
        ]);

        $guestResponse = curl_exec($ch);
        $guestHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($guestHttpCode == 200) {
            $guestData = json_decode($guestResponse, true);

            if (isset($guestData['success']) && $guestData['success'] === true && isset($guestData['data'])) {
                $g = $guestData['data'];
                echo "--- Huésped ID: $guestId ---\n";
                echo "Nombre: " . ($g['guestFirstName'] ?? '') . " " . ($g['guestLastName'] ?? '') . "\n";
                echo "Country: " . ($g['guestCountry'] ?? 'N/A') . "\n";
                echo "Country Code: " . ($g['guestCountryCode'] ?? 'N/A') . "\n";
                echo "State: " . ($g['guestState'] ?? 'N/A') . "\n";
                echo "City: " . ($g['guestCity'] ?? 'N/A') . "\n";
                echo "Document Type: " . ($g['guestDocumentType'] ?? 'N/A') . "\n";
                echo "Document Number: " . ($g['guestDocumentNumber'] ?? 'N/A') . "\n";
                echo "\n";
            }
        } else {
            echo "Error obteniendo huésped $guestId: HTTP $guestHttpCode\n";
        }

        // Pequeña pausa para no saturar la API
        usleep(200000);
    }

} else {
    echo "Error en respuesta:\n";
    print_r($data);

    // Si el token expiró, intentar refrescarlo
    if (isset($data['message']) && strpos($data['message'], 'token') !== false) {
        echo "\n=== REFRESCANDO TOKEN ===\n";

        $refreshUrl = "https://hotels.cloudbeds.com/api/v1.2/access_token";
        $refreshData = [
            'grant_type' => 'refresh_token',
            'client_id' => $config->client_id,
            'client_secret' => $config->client_secret,
            'refresh_token' => $refresh_token
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $refreshUrl,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($refreshData),
            CURLOPT_RETURNTRANSFER => true
        ]);

        $refreshResponse = curl_exec($ch);
        curl_close($ch);

        $refreshResult = json_decode($refreshResponse, true);
        print_r($refreshResult);

        if (isset($refreshResult['access_token'])) {
            // Actualizar en BD
            $wpdb->update(
                'wp_hotels_config',
                [
                    'access_token' => $refreshResult['access_token'],
                    'refresh_token' => $refreshResult['refresh_token']
                ],
                ['id' => $config->id]
            );
            echo "\nToken actualizado. Ejecuta el script nuevamente.\n";
        }
    }
}

echo "\n=== FIN ===\n";
