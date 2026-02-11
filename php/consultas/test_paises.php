<?php
/**
 * Script temporal para obtener lista de países de AFIP WSCT
 */

// Configuración
define("CERT_PATH", __DIR__ . "/../afip/cert/facturacion2025.pem");
define("KEY_PATH", __DIR__ . "/../afip/cert/MiClavePrivada.key");
define("CUIT", "30718446976");
define("WSAA_URL", "https://wsaa.afip.gov.ar/ws/services/LoginCms");
define("WSCT_URL", "https://serviciosjava.afip.gob.ar/wsct/CTService");

echo "=== Test Consulta Países AFIP WSCT ===\n\n";

// Paso 1: Crear TRA para WSCT
echo "1. Creando TRA para servicio WSCT...\n";

$tra = '<?xml version="1.0" encoding="UTF-8"?>' .
    '<loginTicketRequest version="1.0">' .
    '<header>' .
    '<source>SERIALNUMBER=CUIT 30718446976, CN=facturaciondava2025</source>' .
    '<destination>cn=wsaa,o=afip,c=ar,serialNumber=CUIT 33693450239</destination>' .
    '<uniqueId>' . date('U') . '</uniqueId>' .
    '<generationTime>' . date('c', time() - 60) . '</generationTime>' .
    '<expirationTime>' . date('c', time() + 60) . '</expirationTime>' .
    '</header>' .
    '<service>wsct</service>' .
    '</loginTicketRequest>';

$traFile = __DIR__ . "/temp_tra_wsct.xml";
file_put_contents($traFile, $tra);
echo "   TRA creado.\n";

// Paso 2: Firmar TRA con PKCS#7
echo "2. Firmando TRA con certificado...\n";

$signedFile = __DIR__ . "/temp_tra_wsct.tmp";

$signResult = openssl_pkcs7_sign(
    $traFile,
    $signedFile,
    "file://" . CERT_PATH,
    ["file://" . KEY_PATH, ""],
    [],
    !PKCS7_DETACHED
);

if (!$signResult) {
    echo "   ERROR: No se pudo firmar el TRA\n";
    echo "   OpenSSL error: " . openssl_error_string() . "\n";
    exit(1);
}

// Leer CMS (saltar primeras 4 líneas de headers MIME)
$signedContent = file($signedFile);
$cms = "";
for ($i = 4; $i < count($signedContent); $i++) {
    $cms .= $signedContent[$i];
}

// Limpiar archivos temporales
unlink($traFile);
unlink($signedFile);

echo "   TRA firmado correctamente.\n";

// Paso 3: Llamar WSAA
echo "3. Autenticando con WSAA...\n";

$wsaaRequest = '<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:wsaa="http://wsaa.view.sua.dvadac.desein.afip.gov">
   <soapenv:Header/>
   <soapenv:Body>
      <wsaa:loginCms>
         <wsaa:in0>' . $cms . '</wsaa:in0>
      </wsaa:loginCms>
   </soapenv:Body>
</soapenv:Envelope>';

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => WSAA_URL,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $wsaaRequest,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: text/xml; charset=utf-8',
        'SOAPAction: ""'
    ],
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => true
]);

$wsaaResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    echo "   ERROR CURL: $curlError\n";
    exit(1);
}

echo "   HTTP Code: $httpCode\n";

// Decodificar entidades HTML en la respuesta
$wsaaResponseDecoded = html_entity_decode($wsaaResponse);

// Extraer Token y Sign de la respuesta
if (preg_match('/<token>(.+?)<\/token>/s', $wsaaResponseDecoded, $tokenMatch) &&
    preg_match('/<sign>(.+?)<\/sign>/s', $wsaaResponseDecoded, $signMatch)) {

    $token = trim($tokenMatch[1]);
    $sign = trim($signMatch[1]);

    echo "   Token obtenido: " . substr($token, 0, 50) . "...\n";
    echo "   Sign obtenido: " . substr($sign, 0, 50) . "...\n";
} else {
    echo "   ERROR: No se pudo extraer Token/Sign\n";
    echo "   Respuesta WSAA:\n$wsaaResponse\n";
    exit(1);
}

// Paso 4: Consultar Países
echo "\n4. Consultando países en WSCT...\n";

$wsctRequest = '<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"
                  xmlns:cts="http://ar.gob.afip.wsct/CTService/">
  <soapenv:Header/>
  <soapenv:Body>
    <cts:consultarPaisesRequest>
      <authRequest>
        <token>' . htmlspecialchars($token, ENT_XML1, 'UTF-8') . '</token>
        <sign>' . htmlspecialchars($sign, ENT_XML1, 'UTF-8') . '</sign>
        <cuitRepresentada>' . CUIT . '</cuitRepresentada>
      </authRequest>
    </cts:consultarPaisesRequest>
  </soapenv:Body>
</soapenv:Envelope>';

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => WSCT_URL,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $wsctRequest,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: text/xml; charset=utf-8',
        'SOAPAction: "http://ar.gob.afip.wsct/CTService/consultarPaises"'
    ],
    CURLOPT_TIMEOUT => 30
]);

$wsctResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    echo "   ERROR CURL: $curlError\n";
    exit(1);
}

echo "   HTTP Code: $httpCode\n\n";

// Parsear respuesta y mostrar países
echo "=== LISTA DE PAÍSES AFIP ===\n\n";

// Limpiar namespaces para parseo más fácil
$cleanXml = preg_replace('/xmlns[^=]*="[^"]*"/', '', $wsctResponse);
$cleanXml = preg_replace('/<[a-zA-Z0-9]+:/', '<', $cleanXml);
$cleanXml = preg_replace('/<\/[a-zA-Z0-9]+:/', '</', $cleanXml);

if (preg_match_all('/<codigoDescripcionString>\s*<codigo>(\d+)<\/codigo>\s*<descripcion>([^<]+)<\/descripcion>\s*<\/codigoDescripcionString>/s', $cleanXml, $matches, PREG_SET_ORDER)) {

    echo sprintf("%-10s | %-50s\n", "CODIGO", "PAIS");
    echo str_repeat("-", 65) . "\n";

    $paises = [];
    foreach ($matches as $match) {
        $codigo = $match[1];
        $descripcion = trim($match[2]);
        $paises[$codigo] = $descripcion;
        echo sprintf("%-10s | %-50s\n", $codigo, $descripcion);
    }

    echo "\n=== TOTAL: " . count($paises) . " países ===\n";

    // Guardar en JSON para referencia
    $jsonFile = __DIR__ . "/paises_afip.json";
    file_put_contents($jsonFile, json_encode($paises, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "\nLista guardada en: $jsonFile\n";

} else {
    echo "No se pudieron extraer los países de la respuesta.\n";
    echo "\nRespuesta raw:\n";
    echo $wsctResponse;
}

echo "\n=== FIN ===\n";
