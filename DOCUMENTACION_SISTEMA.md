# Sistema de Facturacion Electronica - Hotel Penthouse 1004

## Indice

1. [Resumen Ejecutivo](#1-resumen-ejecutivo)
2. [Arquitectura del Sistema](#2-arquitectura-del-sistema)
3. [Estructura de Archivos](#3-estructura-de-archivos)
4. [Base de Datos](#4-base-de-datos)
5. [Integraciones Externas](#5-integraciones-externas)
6. [Sistema de Autenticacion y Seguridad](#6-sistema-de-autenticacion-y-seguridad) **[NUEVO]**
7. [Flujo Principal de Facturacion](#7-flujo-principal-de-facturacion)
8. [Responsabilidades de Archivos](#8-responsabilidades-de-archivos)
9. [Diagramas de Flujo](#9-diagramas-de-flujo)
10. [Tipos de Facturas Soportadas](#10-tipos-de-facturas-soportadas)
11. [Estado Actual de Factura Tipo T](#11-estado-actual-de-factura-tipo-t)
12. [Plan de Implementacion - Factura Tipo T](#12-plan-de-implementacion---factura-tipo-t)

---

## 1. Resumen Ejecutivo

### Proposito del Sistema
Sistema de facturacion electronica desarrollado como **plugin de WordPress** para el hotel **Penthouse 1004** ubicado en San Carlos de Bariloche, Argentina. El sistema integra:

- **CloudBeds**: Sistema de gestion hotelera (PMS) para obtener reservas y transacciones
- **AFIP (ARCA)**: Autoridad fiscal argentina para emision de comprobantes electronicos
- **WordPress**: Plataforma base para el panel administrativo

### Datos del Emisor
| Campo | Valor |
|-------|-------|
| Razon Social | DEVA S.A.S |
| CUIT | 30-71844697-6 |
| Domicilio | San Martin 127, Piso 10, Dpto 1004, Bariloche, Rio Negro (CP 8400) |
| Condicion IVA | IVA Responsable Inscripto |
| Ingresos Brutos | 47187980 |
| Inicio Actividades | 01/04/2025 |

### URL de Produccion
```
https://contable.penthouse1004.com
```

---

## 2. Arquitectura del Sistema

### Diagrama de Arquitectura General

```
+------------------------------------------------------------------+
|                     WORDPRESS ADMIN PANEL                         |
|                        (init.php)                                 |
+----------------------------------+-------------------------------+
                                   |
           +-----------------------+------------------------+
           |                       |                        |
    +------v------+         +------v------+          +------v------+
    |  config_    |         |transactions_|          | panel_admin |
    |  init/*.php |         | list.php    |          |    .php     |
    +------+------+         +------+------+          +-------------+
           |                       |
           +-----------+-----------+
                       |
               +-------v--------+
               |                |
               | MAIN_GLOBAL.PHP|  <-- CORE DEL SISTEMA (2247 lineas)
               |                |
               +-------+--------+
                       |
     +-----------------+-----------------+
     |                 |                 |
+----v----+      +-----v-----+     +-----v-----+
|  AFIP   |      | CloudBeds |     |  DomPDF   |
|  SOAP   |      |  REST API |     | Generator |
+---------+      +-----------+     +-----------+
     |                 |                 |
+----v----+      +-----v-----+     +-----v-----+
| WSAA    |      | OAuth 2.0 |     | PDF/QR    |
| WSFE    |      | Tokens    |     | Files     |
| WSCT    |      +-----------+     +-----------+
+---------+
```

### Patron de Arquitectura
- **Tipo**: Plugin WordPress Monolitico + Middleware SOAP/REST
- **Acceso a Datos**: Directo via `$wpdb` (sin ORM)
- **Comunicacion Frontend**: AJAX (jQuery)
- **Autenticacion Multi-nivel**: WordPress -> CloudBeds OAuth -> AFIP Certificados

---

## 3. Estructura de Archivos

```
/home/camidev/Projects/afiph1004/
|
+-- init.php                          # Plugin principal WordPress (punto de entrada)
+-- composer.json                     # Dependencias: dompdf, php-barcode-generator
+-- composer.lock
+-- .gitignore
|
+-- css/                              # Estilos
|   +-- style.css                     # Estilos principales
|   +-- style-admin.css               # Estilos panel admin
|   +-- bootstrap.min.css             # Framework CSS
|   +-- smart_wizard_*.css            # Wizard UI
|   +-- print.min.css                 # Estilos impresion
|   +-- Toast-Message/                # Notificaciones toast
|
+-- js/                               # JavaScript
|   +-- main.js                       # Logica frontend principal (AJAX handlers)
|   +-- web_main.js                   # Utilidades web
|   +-- jquery.smartWizard.js         # Wizard component
|   +-- jquery-ui.js                  # jQuery UI
|   +-- bootstrap.min.js              # Bootstrap JS
|   +-- print.min.js                  # Print functionality
|
+-- img/                              # Imagenes
|   +-- codesqr/                      # Codigos QR generados (1000+ archivos)
|   +-- loading.gif                   # Indicador de carga
|
+-- php/                              # Backend PHP
|   |
|   +-- afip/                         # Certificados y WSDL de AFIP
|   |   +-- cert/                     # Certificados produccion
|   |   |   +-- facturacion2025.pem   # Certificado X.509
|   |   |   +-- MiClavePrivada.key    # Clave privada
|   |   |   +-- certificado.crt       # Certificado CRT
|   |   +-- cert_preprod/             # Certificados pre-produccion
|   |   +-- wsaa.wsdl                 # WSDL Autenticacion
|   |   +-- wsfe.wsdl                 # WSDL Facturacion Tipo B
|   |   +-- wsfe-production.wsdl      # WSDL Produccion
|   |   +-- TRA_WSFE.xml              # Ticket Request Auth (Tipo B)
|   |   +-- TRA_WSCT.xml              # Ticket Request Auth (Tipo T)
|   |   +-- response-loginCms.xml     # Respuesta autenticacion
|   |
|   +-- config_init/                  # Configuracion y listados
|   |   +-- config-list.php           # Lista configuraciones API (106 lineas)
|   |   +-- config-create.php         # Crear configuracion (94 lineas)
|   |   +-- config-update.php         # Actualizar configuracion (104 lineas)
|   |   +-- transactions-list.php     # Panel transacciones PROD (447 lineas)
|   |   +-- transactions-list_dev.php # Panel transacciones DEV (640 lineas)
|   |
|   +-- consultas/                    # Logica de negocio principal
|   |   +-- main_global.php           # CORE - Toda la logica AFIP/CloudBeds (2247 lineas)
|   |   +-- main_global_preprod.php   # Version pre-produccion (1076 lineas)
|   |   +-- barcode.php               # Generador codigos de barras (152 lineas)
|   |   +-- pdfs/                     # PDFs temporales
|   |   +-- qrcodes/                  # QR codes temporales
|   |
|   +-- varios/                       # Utilidades
|   |   +-- functions.php             # Funciones auxiliares (87 lineas)
|   |   +-- panel_admin.php           # Panel admin WooCommerce (107 lineas)
|   |
|   +-- invoicesxml/                  # XMLs de facturas AFIP
|   |   +-- {transactionID}.xml       # Respuestas exitosas
|   |   +-- {transactionID}_ERROR.xml # Respuestas con error
|   |   +-- code.php                  # Parser XML
|   |
|   +-- invoicespdf/                  # PDFs generados
|       +-- {reservationID}_*.pdf     # Facturas PDF
|       +-- log_pdf.json              # Log de PDFs generados
|       +-- log_pdf_error.json        # Log de errores
|
+-- vendor/                           # Dependencias Composer
    +-- dompdf/dompdf/                # Generador PDF
    +-- picqer/php-barcode-generator/ # Codigos de barras
    +-- phenx/php-font-lib/           # Fuentes para PDF
```

---

## 4. Base de Datos

### Tablas del Sistema

#### `wp_hotels_config`
Almacena la configuracion de la API de CloudBeds.

| Columna | Tipo | Descripcion |
|---------|------|-------------|
| `id` | INT(10) PK AUTO | ID unico |
| `api_endpoint` | VARCHAR(256) | URL del endpoint API |
| `version` | VARCHAR(256) | Version de la API |
| `client_id` | VARCHAR(256) | Client ID OAuth |
| `client_secret` | VARCHAR(256) | Client Secret OAuth |
| `redirect_url` | VARCHAR(256) | URL de callback OAuth |
| `code_auth` | VARCHAR(256) | Codigo de autorizacion |
| `access_token` | LONGTEXT | Token de acceso |
| `refresh_token` | LONGTEXT | Token de refresco |
| `status` | ENUM('0','1') | Estado (activo/inactivo) |

#### `wp_hotels_transactions`
Almacena las transacciones importadas de CloudBeds.

| Columna | Tipo | Descripcion |
|---------|------|-------------|
| `id` | INT(10) PK AUTO | ID unico |
| `propertyID` | VARCHAR(256) | ID de la propiedad |
| `transactionID` | VARCHAR(256) | ID de transaccion CloudBeds |
| `reservationID` | VARCHAR(256) | ID de reserva |
| `guestID` | VARCHAR(256) | ID del huesped |
| `transactionDateTime` | DATETIME | Fecha/hora transaccion |
| `completeName` | VARCHAR(256) | Nombre completo huesped |
| `description` | VARCHAR(256) | Descripcion del cargo |
| `passportNumber` | VARCHAR(256) | DNI/Pasaporte |
| `amount` | VARCHAR(256) | Monto de la transaccion |
| `currency` | VARCHAR(256) | Moneda |
| `country` | VARCHAR(256) | Pais del huesped |
| `city` | VARCHAR(256) | Ciudad |
| `address` | LONGTEXT | Direccion |
| `invoiceUrl` | VARCHAR(256) | URL/nombre del PDF generado |
| `transactionType` | VARCHAR(256) | Tipo (credit/debit) |

---

## 5. Integraciones Externas

### 5.1 CloudBeds API (REST)

Sistema de gestion hotelera que proporciona datos de reservas y huespedes.

| Endpoint | Metodo | Proposito |
|----------|--------|-----------|
| `https://hotels.cloudbeds.com/api/v1.2/oauth` | GET | Iniciar flujo OAuth |
| `https://hotels.cloudbeds.com/api/v1.2/access_token` | POST | Obtener/refrescar tokens |
| `https://hotels.cloudbeds.com/api/v1.2/getTransactions` | GET | Listar transacciones |
| `https://hotels.cloudbeds.com/api/v1.2/getGuest` | GET | Datos del huesped |
| `https://hotels.cloudbeds.com/api/v1.2/postReservationDocument` | POST | Subir PDF a reserva |

**Flujo de Autenticacion OAuth 2.0:**
```
1. Usuario inicia sesion -> Redirect a CloudBeds
2. CloudBeds autentica -> Redirect con ?code=xxx
3. Sistema intercambia code por access_token + refresh_token
4. Tokens se almacenan en wp_hotels_config
5. Refresh automatico cuando expira el access_token
```

### 5.2 AFIP - Web Services (SOAP)

#### WSAA - Web Service de Autenticacion y Autorizacion
| URL | Proposito |
|-----|-----------|
| `https://wsaa.afip.gov.ar/ws/services/LoginCms` | Produccion |
| `https://wsaahomo.afip.gov.ar/ws/services/LoginCms` | Homologacion |

**Proceso de Autenticacion:**
1. Crear TRA (Ticket de Requerimiento de Acceso) XML
2. Firmar TRA con certificado y clave privada (PKCS#7)
3. Enviar CMS a WSAA
4. Recibir Token + Sign (validos por 12 horas)

#### WSFE - Web Service de Facturacion Electronica (Tipo B)
| URL | Proposito |
|-----|-----------|
| `https://servicios1.afip.gov.ar/wsfev1/service.asmx` | Produccion |

**Operaciones utilizadas:**
- `FECAESolicitar`: Solicitar CAE para factura
- `FECompUltimoAutorizado`: Obtener ultimo comprobante autorizado

#### WSCT - Web Service de Comprobantes de Turismo (Tipo T)
| URL | Proposito |
|-----|-----------|
| `https://serviciosjava.afip.gob.ar/wsct/CTService` | Produccion |

**Operaciones utilizadas:**
- `autorizarComprobante`: Solicitar autorizacion comprobante turismo
- `consultarUltimoComprobanteAutorizado`: Ultimo comprobante
- `consultarCondicionesIVA`: Listar condiciones IVA
- `consultarTiposItem`: Tipos de items
- `consultarCodigosItemTurismo`: Codigos especificos turismo
- `consultarPaises`: Listar paises
- `consultarCUITsPaises`: CUIT genericos por pais

---

## 6. Sistema de Autenticacion y Seguridad

Esta seccion detalla todos los mecanismos de autenticacion, certificados digitales, llaves criptograficas y flujos de tokens utilizados en el sistema.

---

### 6.1 Arquitectura de Seguridad General

```
+------------------------------------------------------------------+
|                    CAPAS DE AUTENTICACION                         |
+------------------------------------------------------------------+
|                                                                   |
|  CAPA 1: WordPress                                                |
|  +------------------------------------------------------------+  |
|  | - Sesion de usuario WordPress                               |  |
|  | - Roles y capacidades (admin)                               |  |
|  | - Cookies de autenticacion WP                               |  |
|  +------------------------------------------------------------+  |
|                              |                                    |
|                              v                                    |
|  CAPA 2: CloudBeds OAuth 2.0                                      |
|  +------------------------------------------------------------+  |
|  | - client_id + client_secret                                 |  |
|  | - Authorization Code Flow                                   |  |
|  | - access_token (corta duracion)                             |  |
|  | - refresh_token (larga duracion)                            |  |
|  +------------------------------------------------------------+  |
|                              |                                    |
|                              v                                    |
|  CAPA 3: AFIP Certificados Digitales                              |
|  +------------------------------------------------------------+  |
|  | - Certificado X.509 (.pem/.crt)                             |  |
|  | - Clave Privada RSA (.key)                                  |  |
|  | - Firma PKCS#7 (CMS)                                        |  |
|  | - Token + Sign WSAA (12 horas)                              |  |
|  +------------------------------------------------------------+  |
|                                                                   |
+------------------------------------------------------------------+
```

---

### 6.2 Certificados Digitales AFIP

#### 6.2.1 Archivos de Certificados

| Archivo | Ubicacion | Proposito | Formato |
|---------|-----------|-----------|---------|
| `facturacion2025.pem` | `php/afip/cert/` | Certificado X.509 publico | PEM (Base64) |
| `MiClavePrivada.key` | `php/afip/cert/` | Clave privada RSA | PEM (Base64) |
| `certificado.crt` | `php/afip/cert/` | Certificado formato CRT | DER/PEM |

#### 6.2.2 Estructura del Certificado X.509

```
+------------------------------------------+
|           CERTIFICADO X.509              |
+------------------------------------------+
| Version: 3                               |
| Serial Number: [unico]                   |
| Signature Algorithm: sha256WithRSA       |
+------------------------------------------+
| ISSUER (Emisor - AFIP):                  |
|   CN = Autoridad Certificante de AFIP    |
|   O  = AFIP                              |
|   C  = AR                                |
+------------------------------------------+
| SUBJECT (Titular - DEVA S.A.S):          |
|   serialNumber = CUIT 30718446976        |
|   CN = facturaciondava2025               |
|   O  = [Razon Social]                    |
|   C  = AR                                |
+------------------------------------------+
| Validity (Vigencia):                     |
|   Not Before: [fecha emision]            |
|   Not After:  [fecha vencimiento]        |
+------------------------------------------+
| Public Key: RSA 2048 bits                |
+------------------------------------------+
| Extensions:                              |
|   - Key Usage: Digital Signature         |
|   - Extended Key Usage: Client Auth      |
+------------------------------------------+
```

#### 6.2.3 Informacion del Certificado Actual

| Campo | Valor |
|-------|-------|
| CUIT Titular | 30718446976 |
| Common Name (CN) | facturaciondava2025 |
| Algoritmo | RSA 2048 bits |
| Firma | SHA-256 with RSA |
| Servicios Habilitados | wsfe, wsct |
| Passphrase | (sin passphrase) |

#### 6.2.4 Clave Privada RSA

```
+------------------------------------------+
|           CLAVE PRIVADA RSA              |
+------------------------------------------+
| Archivo: MiClavePrivada.key              |
| Formato: PEM (PKCS#8)                    |
| Algoritmo: RSA                           |
| Longitud: 2048 bits                      |
| Encriptacion: Sin cifrar (no passphrase)|
+------------------------------------------+
| Estructura PEM:                          |
| -----BEGIN RSA PRIVATE KEY-----          |
| [Base64 encoded key data]                |
| -----END RSA PRIVATE KEY-----            |
+------------------------------------------+
```

**Uso en codigo** (`main_global.php:18-22`):
```php
define("CERT", "../afip/cert/facturacion2025.pem");
define("PRIVATEKEY", "../afip/cert/MiClavePrivada.key");
define("PASSPHRASE", "");  // Sin passphrase
```

---

### 6.3 Proceso de Autenticacion AFIP (WSAA)

#### 6.3.1 Diagrama Detallado del Flujo

```
+------------------+
| 1. CREAR TRA     |
| (Ticket Request  |
|  Access)         |
+--------+---------+
         |
         v
+------------------+
| 2. GENERAR XML   |
| loginTicketReq   |
+--------+---------+
         |
         v
+------------------+
| 3. FIRMAR CON    |
| PKCS#7 (CMS)     |
| usando cert+key  |
+--------+---------+
         |
         v
+------------------+
| 4. CODIFICAR     |
| Base64           |
+--------+---------+
         |
         v
+------------------+
| 5. ENVIAR A WSAA |
| loginCms()       |
+--------+---------+
         |
         v
+------------------+
| 6. RECIBIR       |
| Token + Sign     |
| (validos 12h)    |
+------------------+
```

#### 6.3.2 Estructura del TRA (Ticket de Requerimiento de Acceso)

**Archivo generado**: `php/afip/TRA_WSFE.xml` o `TRA_WSCT.xml`

```xml
<?xml version="1.0" encoding="UTF-8"?>
<loginTicketRequest version="1.0">
    <header>
        <!-- Identificacion del solicitante -->
        <source>serialNumber=CUIT 30718446976, cn=facturaciondava2025</source>

        <!-- Destino: WSAA de AFIP -->
        <destination>cn=wsaa,o=afip,c=ar,serialNumber=CUIT 33693450239</destination>

        <!-- ID unico (timestamp Unix) -->
        <uniqueId>1735257600</uniqueId>

        <!-- Ventana de validez del TRA (2 minutos) -->
        <generationTime>2025-12-26T10:59:00-03:00</generationTime>
        <expirationTime>2025-12-26T11:01:00-expirationTime>
    </header>

    <!-- Servicio solicitado -->
    <service>wsfe</service>  <!-- o "wsct" para Tipo T -->
</loginTicketRequest>
```

**Codigo que genera el TRA** (`main_global.php:981-995`):
```php
function CreateTRA($SERVICE)
{
    $TRA = new SimpleXMLElement(
        '<?xml version="1.0" encoding="UTF-8"?>' .
        '<loginTicketRequest version="1.0">' .
        '</loginTicketRequest>');

    $TRA->addChild('header');
    $TRA->header->addChild('source',
        'serialNumber=CUIT 30718446976, cn=facturaciondava2025');
    $TRA->header->addChild('destination',
        'cn=wsaa,o=afip,c=ar,serialNumber=CUIT 33693450239');
    $TRA->header->addChild('uniqueId', date('U'));
    $TRA->header->addChild('generationTime', date('c', date('U')-60));
    $TRA->header->addChild('expirationTime', date('c', date('U')+60));
    $TRA->addChild('service', $SERVICE);

    $TRA->asXML(plugin_dir_path(__FILE__) . '../afip/TRA_WSFE.xml');
}
```

#### 6.3.3 Proceso de Firma PKCS#7

**Que es PKCS#7/CMS?**
- PKCS#7 (Cryptographic Message Syntax) es un estandar para firmar y/o cifrar datos
- Genera un "sobre" digital que contiene: datos originales + firma + certificado
- AFIP requiere firma "detached" = firma separada de los datos

**Flujo de firma**:
```
+------------------+     +------------------+     +------------------+
|   TRA.xml        | --> |  openssl_pkcs7   | --> |   TRA.tmp        |
|   (datos)        |     |  _sign()         |     |   (firmado)      |
+------------------+     +------------------+     +------------------+
                               |
                   +-----------+-----------+
                   |                       |
            +------v------+         +------v------+
            | Certificado |         | Clave       |
            | .pem        |         | Privada     |
            +-------------+         | .key        |
                                    +-------------+
```

**Codigo de firma** (`main_global.php:1000-1028`):
```php
function SignTRA()
{
    // Rutas a certificado y clave
    $certificado = "file://" . plugin_dir_path(__FILE__) .
                   "../afip/cert/certificado.crt";
    $privatekey = "file://" . plugin_dir_path(__FILE__) .
                  "../afip/cert/MiClavePrivada.key";

    // Firmar con OpenSSL PKCS#7
    $STATUS = openssl_pkcs7_sign(
        plugin_dir_path(__FILE__) . "../afip/TRA_WSFE.xml",  // Input
        plugin_dir_path(__FILE__) . "../afip/TRA_WSFE.tmp",  // Output
        "file://" . plugin_dir_path(__FILE__) . CERT,        // Certificado
        array(
            "file://" . plugin_dir_path(__FILE__) . PRIVATEKEY,
            PASSPHRASE  // Passphrase (vacio)
        ),
        array(),           // Headers adicionales
        !PKCS7_DETACHED    // Firma attached (incluye datos)
    );

    if (!$STATUS) {
        exit("ERROR generating PKCS#7 signature\n");
    }

    // Leer archivo firmado, saltar headers MIME (primeras 4 lineas)
    $inf = fopen(plugin_dir_path(__FILE__) . "../afip/TRA_WSFE.tmp", "r");
    $i = 0;
    $CMS = "";
    while (!feof($inf)) {
        $buffer = fgets($inf);
        if ($i++ >= 4) {
            $CMS .= $buffer;  // Solo contenido Base64
        }
    }
    fclose($inf);

    // Eliminar archivo temporal
    unlink(plugin_dir_path(__FILE__) . "../afip/TRA_WSFE.tmp");

    return $CMS;  // Retorna firma CMS en Base64
}
```

**Estructura del archivo .tmp generado**:
```
MIME-Version: 1.0                          <- Linea 1 (ignorar)
Content-Type: application/x-pkcs7-mime...  <- Linea 2 (ignorar)
Content-Transfer-Encoding: base64          <- Linea 3 (ignorar)
                                           <- Linea 4 (vacia, ignorar)
MIIGxgYJKoZIhvcNAQcCoIIGtzCCBrMCAQExDzAN <- Linea 5+ (CMS Base64)
BglghkgBZQMEAgEFADCCAWEGCSqGSIb3DQEHATA...
...
```

#### 6.3.4 Llamada a WSAA

**Codigo de llamada** (`main_global.php:1030-1045`):
```php
function CallWSAA($CMS)
{
    // Cliente SOAP para WSAA
    $client = new SoapClient(
        plugin_dir_path(__FILE__) . WSDL,  // wsaa.wsdl
        array(
            'soap_version' => SOAP_1_2,
            'location'     => URL,  // https://wsaa.afip.gov.ar/...
            'trace'        => 1,
            'exceptions'   => 0
        )
    );

    // Llamar operacion loginCms con CMS codificado en Base64
    $results = $client->loginCms(
        array('in0' => base64_encode($CMS))
    );

    // Guardar request/response para debug
    file_put_contents(
        plugin_dir_path(__FILE__) . "../afip/request-loginCms.xml",
        $client->__getLastRequest()
    );
    file_put_contents(
        plugin_dir_path(__FILE__) . "../afip/response-loginCms.xml",
        $client->__getLastResponse()
    );

    if (is_soap_fault($results)) {
        exit("SOAP Fault: " . $results->faultcode . "\n" .
             $results->faultstring . "\n");
    }

    return $results->loginCmsReturn;
}
```

#### 6.3.5 Estructura de la Respuesta WSAA

**Archivo**: `php/afip/response-loginCms.xml`

```xml
<?xml version="1.0" encoding="UTF-8"?>
<loginTicketResponse version="1.0">
    <header>
        <source>cn=wsaa,o=afip,c=ar,serialNumber=CUIT 33693450239</source>
        <destination>serialNumber=CUIT 30718446976, cn=facturaciondava2025</destination>
        <uniqueId>1234567890</uniqueId>
        <generationTime>2025-12-26T11:00:00-03:00</generationTime>
        <expirationTime>2025-12-26T23:00:00-03:00</expirationTime>
    </header>
    <credentials>
        <!-- TOKEN: Identificador de sesion (usado en cada request) -->
        <token>PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiPz4K
        PHNzby...[muy largo, ~2KB en Base64]...</token>

        <!-- SIGN: Firma del token (usado en cada request) -->
        <sign>ZTc3YWRlZWEzZjVhOGQ5OWYyODVhMjRlYjk4MGY4ZDY4...</sign>
    </credentials>
</loginTicketResponse>
```

**Campos importantes**:

| Campo | Descripcion | Duracion |
|-------|-------------|----------|
| `token` | Identificador de sesion autenticada | 12 horas |
| `sign` | Firma digital del token | 12 horas |
| `expirationTime` | Fecha/hora de expiracion | - |

---

### 6.4 Autenticacion CloudBeds (OAuth 2.0)

#### 6.4.1 Credenciales OAuth

**Almacenamiento**: Tabla `wp_hotels_config`

| Campo | Descripcion | Ejemplo |
|-------|-------------|---------|
| `client_id` | Identificador de la aplicacion | `abc123...` |
| `client_secret` | Secreto de la aplicacion | `xyz789...` |
| `redirect_url` | URL de callback | `https://contable.penthouse1004.com/wp-admin/admin.php?page=transactions_list` |
| `code_auth` | Codigo de autorizacion (temporal) | `AUTH_CODE_xxx` |
| `access_token` | Token de acceso (corta duracion) | `eyJ0eXAiOiJKV1QiLCJhbGc...` |
| `refresh_token` | Token de refresco (larga duracion) | `def456...` |

#### 6.4.2 Flujo OAuth 2.0 - Authorization Code

```
+-------------+                                +-------------+
|   Usuario   |                                |  CloudBeds  |
|   (Browser) |                                |    OAuth    |
+------+------+                                +------+------+
       |                                              |
       | 1. Click "Conectar CloudBeds"                |
       |                                              |
       v                                              |
+------+------+                                       |
|   Sistema   |                                       |
|   Plugin    |                                       |
+------+------+                                       |
       |                                              |
       | 2. Redirect a CloudBeds OAuth                |
       |    GET /oauth?client_id=XXX&                 |
       |        redirect_uri=CALLBACK&                |
       |        response_type=code&                   |
       |        scope=read_write                      |
       +--------------------------------------------->|
       |                                              |
       |                         3. Usuario autentica |
       |                            en CloudBeds      |
       |                                              |
       |                         4. CloudBeds genera  |
       |                            authorization_code|
       |<---------------------------------------------+
       | 5. Redirect a CALLBACK?code=AUTH_CODE        |
       |                                              |
+------v------+                                       |
|   Sistema   |                                       |
|   Plugin    |                                       |
+------+------+                                       |
       |                                              |
       | 6. POST /access_token                        |
       |    {client_id, client_secret,                |
       |     code, redirect_uri,                      |
       |     grant_type=authorization_code}           |
       +--------------------------------------------->|
       |                                              |
       |<---------------------------------------------+
       | 7. Respuesta:                                |
       |    {access_token, refresh_token,             |
       |     expires_in, token_type}                  |
       |                                              |
+------v------+                                       |
| Guardar en  |                                       |
| wp_hotels_  |                                       |
| config      |                                       |
+-------------+                                       |
```

#### 6.4.3 Codigo de Obtencion de Token

**Funcion `getToken()`** (`main_global.php:555-578`):
```php
function getToken($code)
{
    $url = ACCESS_TOKEN_URL;  // https://hotels.cloudbeds.com/api/v1.2/access_token

    $data = array(
        'grant_type'    => 'authorization_code',
        'client_id'     => CLIENT_ID,
        'client_secret' => CLIENT_SECRET,
        'redirect_uri'  => CALLBACK_URL,
        'code'          => $code
    );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response);
    // Retorna: {access_token, refresh_token, expires_in, token_type}
}
```

#### 6.4.4 Codigo de Refresh Token

**Funcion `refreshToken()`** (`main_global.php:580-602`):
```php
function refreshToken($refresh_token)
{
    $url = ACCESS_TOKEN_URL;

    $data = array(
        'grant_type'    => 'refresh_token',
        'client_id'     => CLIENT_ID,
        'client_secret' => CLIENT_SECRET,
        'refresh_token' => $refresh_token
    );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response);
    // Retorna nuevos: {access_token, refresh_token, expires_in}
}
```

#### 6.4.5 Ciclo de Vida de Tokens CloudBeds

```
+------------------+     +------------------+     +------------------+
|  authorization   | --> |  access_token    | --> |  refresh_token   |
|  code            |     |                  |     |                  |
+------------------+     +------------------+     +------------------+
| Duracion: ~10min |     | Duracion: ~1hora |     | Duracion: ~30dias|
| Uso: 1 vez       |     | Uso: multiple    |     | Uso: multiple    |
| Obtencion: OAuth |     | Obtencion: POST  |     | Obtencion: POST  |
+------------------+     +------------------+     +------------------+
        |                        |                        |
        v                        v                        v
   [Intercambiar           [Usar en cada           [Usar cuando
    por tokens]             API call]               access expira]
```

---

### 6.5 Almacenamiento de Tokens y Credenciales

#### 6.5.1 Tokens AFIP (Archivos XML)

| Archivo | Contenido | Duracion |
|---------|-----------|----------|
| `TRA_WSFE.xml` | TRA para servicio WSFE | Cache hasta uso |
| `TRA_WSCT.xml` | TRA para servicio WSCT | Cache hasta uso |
| `response-loginCms.xml` | Token + Sign activos | 12 horas |
| `request-loginCms.xml` | Ultimo request (debug) | - |

**Nota**: Los tokens AFIP se cachean en `response-loginCms.xml`. El sistema deberia verificar `expirationTime` antes de cada operacion y renovar si expiro.

#### 6.5.2 Tokens CloudBeds (Base de Datos)

**Tabla**: `wp_hotels_config`

```sql
SELECT
    access_token,   -- Token actual (usar en API calls)
    refresh_token,  -- Token para renovar
    code_auth       -- Codigo de autorizacion original
FROM wp_hotels_config
WHERE status = 1;
```

#### 6.5.3 Flujo de Verificacion de Tokens

```
+---------------------+
| Inicio operacion    |
+----------+----------+
           |
           v
+----------+----------+
| Token CloudBeds     |
| valido?             |
+----------+----------+
           |
     +-----+-----+
     |           |
     v           v
   [NO]        [SI]
     |           |
     v           |
+----+----+      |
|Refresh  |      |
|Token    |      |
+----+----+      |
     |           |
     +-----+-----+
           |
           v
+----------+----------+
| Token AFIP          |
| valido? (12h)       |
+----------+----------+
           |
     +-----+-----+
     |           |
     v           v
   [NO]        [SI]
     |           |
     v           |
+----+----+      |
|CreateTRA|      |
|SignTRA  |      |
|CallWSAA |      |
+----+----+      |
     |           |
     +-----+-----+
           |
           v
+----------+----------+
| Ejecutar operacion  |
| AFIP (FECAESolic.)  |
+---------------------+
```

---

### 6.6 Seguridad - Consideraciones y Riesgos

#### 6.6.1 Riesgos Identificados

| Riesgo | Severidad | Descripcion |
|--------|-----------|-------------|
| Credenciales hardcodeadas | ALTA | CUIT, URLs en codigo fuente |
| Clave privada sin cifrar | ALTA | `MiClavePrivada.key` sin passphrase |
| Certificados en repo | ALTA | Archivos .pem/.key en git |
| Sin validacion de entrada | MEDIA | Posible SQL injection |
| Tokens en BD sin cifrar | MEDIA | access_token visible en BD |
| Logs con datos sensibles | BAJA | XMLs con info de transacciones |

#### 6.6.2 Recomendaciones de Seguridad

1. **Variables de entorno**: Mover credenciales a `.env`
2. **Cifrar clave privada**: Agregar passphrase al .key
3. **Excluir de git**: Agregar `/php/afip/cert/*` a `.gitignore`
4. **Prepared statements**: Usar `$wpdb->prepare()` en todas las queries
5. **Cifrar tokens**: Encriptar access_token en BD
6. **Rotar certificados**: Renovar antes de vencimiento

#### 6.6.3 Archivos Sensibles (NO versionar)

```gitignore
# Agregar a .gitignore:
php/afip/cert/*.pem
php/afip/cert/*.key
php/afip/cert/*.crt
php/afip/cert/*.p12
php/afip/TRA_*.xml
php/afip/response-*.xml
php/afip/request-*.xml
php/invoicesxml/*.xml
php/invoicespdf/*.pdf
php/invoicespdf/*.json
```

---

### 6.7 Resumen de Archivos de Autenticacion

| Archivo | Tipo | Proposito | Sensible |
|---------|------|-----------|----------|
| `facturacion2025.pem` | Certificado | Autenticar ante AFIP | SI |
| `MiClavePrivada.key` | Clave RSA | Firmar TRAs | **CRITICO** |
| `certificado.crt` | Certificado | Formato alternativo | SI |
| `wsaa.wsdl` | WSDL | Definicion servicio WSAA | NO |
| `wsfe.wsdl` | WSDL | Definicion servicio WSFE | NO |
| `TRA_WSFE.xml` | XML | Cache TRA Tipo B | SI |
| `TRA_WSCT.xml` | XML | Cache TRA Tipo T | SI |
| `response-loginCms.xml` | XML | Token+Sign activos | SI |
| `wp_hotels_config` | BD | Tokens CloudBeds | SI |

---

## 7. Flujo Principal de Facturacion

### 7.1 Flujo Completo

```
+------------------+
| Usuario accede   |
| al panel WP      |
+--------+---------+
         |
         v
+------------------+     NO     +------------------+
| Tiene tokens     +----------->| Iniciar OAuth    |
| CloudBeds?       |            | con CloudBeds    |
+--------+---------+            +--------+---------+
         | SI                            |
         v                               v
+------------------+            +------------------+
| Refrescar token  |            | Callback con     |
| si es necesario  |            | ?code=xxx        |
+--------+---------+            +--------+---------+
         |                               |
         +---------------+---------------+
                         |
                         v
              +----------+----------+
              | Obtener transacciones|
              | de CloudBeds API    |
              +----------+----------+
                         |
                         v
              +----------+----------+
              | Guardar en BD       |
              | wp_hotels_transactions|
              +----------+----------+
                         |
                         v
              +----------+----------+
              | Obtener datos       |
              | huesped (getGuest)  |
              +----------+----------+
                         |
                         v
              +----------+----------+
              | Mostrar lista       |
              | transacciones       |
              +----------+----------+
                         |
                         v
              +----------+----------+
              | Usuario selecciona  |
              | transaccion         |
              +----------+----------+
                         |
         +---------------+---------------+
         |                               |
         v                               v
+--------+--------+             +--------+--------+
| Boton "FB"      |             | Boton "FT"      |
| (Factura B)     |             | (Factura T)     |
+--------+--------+             +--------+--------+
         |                               |
         v                               v
+--------+--------+             +--------+--------+
| gen_facturar    |             | gen_facturar_   |
| (AJAX)          |             | tipo_t (AJAX)   |
+--------+--------+             +--------+--------+
         |                               |
         v                               v
+------------------+            +------------------+
| Verificar TRA    |            | Verificar TRA    |
| WSFE vigente     |            | WSCT vigente     |
+--------+---------+            +--------+---------+
         |                               |
    +----+----+                     +----+----+
    | Expirado|                     | Expirado|
    +----+----+                     +----+----+
         |                               |
         v                               v
+--------+---------+            +--------+---------+
| CreateTRA()      |            | CreateTRATipoT() |
| SignTRA()        |            | SignTRATipoT()   |
| CallWSAA()       |            | CallWSAA()       |
+--------+---------+            +--------+---------+
         |                               |
         +---------------+---------------+
                         |
                         v
              +----------+----------+
              | Obtener ultimo Cbte |
              | autorizado          |
              +----------+----------+
                         |
                         v
              +----------+----------+
              | Construir XML SOAP  |
              | con datos factura   |
              +----------+----------+
                         |
         +---------------+---------------+
         |                               |
         v                               v
+--------+--------+             +--------+--------+
| FECAESolicitar  |             | autorizarCom-   |
| CbteTipo=6      |             | probante        |
| (Tipo B)        |             | CbteTipo=195    |
+--------+--------+             +--------+--------+
         |                               |
         +---------------+---------------+
                         |
                         v
              +----------+----------+
              | Respuesta AFIP      |
              | (A=Aprobado,        |
              |  R=Rechazado)       |
              +----------+----------+
                         |
         +---------------+---------------+
         |                               |
         v                               v
+--------+--------+             +--------+--------+
| SI Aprobado:    |             | SI Rechazado:   |
| - Guardar XML   |             | - Guardar ERROR |
| - Generar PDF   |             | - Log error     |
| - Generar QR    |             | - Notificar     |
+--------+--------+             +--------+--------+
         |
         v
+------------------+
| Enviar PDF a     |
| CloudBeds API    |
| (postReservation |
| Document)        |
+--------+---------+
         |
         v
+------------------+
| Actualizar BD    |
| con invoiceUrl   |
+--------+---------+
         |
         v
+------------------+
| Descargar PDF    |
| al navegador     |
+------------------+
```

### 7.2 Calculo de Montos (IVA 21%)

```php
// Desde el monto total (IVA incluido):
$monto_neto = $amount * 100 / 121;  // Base imponible
$monto_iva = $monto_neto * 0.21;    // IVA 21%
$total = $amount;                    // Total = Neto + IVA
```

---

## 8. Responsabilidades de Archivos

### 8.1 Archivos PHP Principales

| Archivo | Lineas | Responsabilidad |
|---------|--------|-----------------|
| `init.php` | 148 | Punto de entrada del plugin. Registra hooks de activacion/desactivacion, crea tablas, define menus de WordPress |
| `main_global.php` | 2247 | **CORE**: Toda la logica de negocio. AJAX handlers, autenticacion AFIP, facturacion, generacion PDF, integracion CloudBeds |
| `transactions-list.php` | 447 | Vista del panel de transacciones. Listado, filtros, paginacion, botones de accion |
| `transactions-list_dev.php` | 640 | Version desarrollo con funcionalidades adicionales |
| `config-list.php` | 106 | Lista configuraciones API CloudBeds |
| `config-create.php` | 94 | Formulario crear configuracion |
| `config-update.php` | 104 | Formulario actualizar configuracion |
| `panel_admin.php` | 107 | Metaboxes para WooCommerce (despachos) |
| `functions.php` | 87 | Funciones auxiliares |
| `barcode.php` | 152 | Generacion codigos de barras |

### 8.2 Funciones Principales en `main_global.php`

#### Handlers AJAX
| Funcion | Linea | Proposito |
|---------|-------|-----------|
| `ajax_foo_handler()` | 31-551 | Router principal AJAX. Maneja todas las acciones |

#### Autenticacion CloudBeds
| Funcion | Linea | Proposito |
|---------|-------|-----------|
| `getToken()` | 555-578 | Intercambia code por access_token |
| `refreshToken()` | 580-602 | Refresca token expirado |

#### Transacciones CloudBeds
| Funcion | Linea | Proposito |
|---------|-------|-----------|
| `getTransactions()` | 604-631 | Obtiene todas las transacciones |
| `getTransactionsByDate()` | 633-663 | Transacciones por rango de fechas |
| `saveTransactions()` | 665-692 | Guarda transacciones en BD |
| `checkDatabase()` | 694-703 | Verifica si transaccion ya existe |
| `getDataByGuest()` | 705-754 | Obtiene datos del huesped |
| `getGuest()` | 800-829 | Llama API getGuest de CloudBeds |
| `fetch_import()` | 870-903 | Importa transacciones con filtros |

#### Autenticacion AFIP (Tipo B)
| Funcion | Linea | Proposito |
|---------|-------|-----------|
| `CreateTRA()` | 981-995 | Crea XML TRA para WSFE |
| `SignTRA()` | 1000-1028 | Firma TRA con PKCS#7 |
| `CallWSAA()` | 1030-1045 | Llama WSAA, obtiene token/sign |

#### Autenticacion AFIP (Tipo T)
| Funcion | Linea | Proposito |
|---------|-------|-----------|
| `CreateTRATipoT()` | 1052-1066 | Crea XML TRA para WSCT |
| `SignTRATipoT()` | 1069-1097 | Firma TRA para WSCT |

#### Facturacion AFIP
| Funcion | Linea | Proposito |
|---------|-------|-----------|
| `FECAESolicitar()` | 1112-1248 | Emite Factura Tipo B (CbteTipo=6) |
| `FECAESolicitarTipoT()` | 1251-1384 | Emite Factura Tipo T (CbteTipo=195) - **INCOMPLETA** |
| `FECompUltimoAutorizado()` | 1386-1425 | Ultimo comprobante Tipo B |
| `FECompUltimoAutorizadoTipoT()` | 1427-1482 | Ultimo comprobante Tipo T |

#### Generacion de Documentos
| Funcion | Linea | Proposito |
|---------|-------|-----------|
| `generatePDF()` | 1484-1781 | Genera PDF de factura con DomPDF |
| `generate_qr()` | 1878-1901 | Genera codigo QR para factura |
| `sendPDF()` | 831-868 | Envia PDF a CloudBeds |
| `saveUrlFile()` | 1784-1798 | Guarda nombre PDF en BD |

#### Consultas WSCT (Tipo T)
| Funcion | Linea | Proposito |
|---------|-------|-----------|
| `WSCT_call()` | 1940-1970 | Helper base para llamadas WSCT |
| `WSCT_ConsultarPaises()` | 1975-1987 | Lista paises |
| `WSCT_ObtenerCodigoPais()` | 1990-2011 | Obtiene codigo de pais |
| `WSCT_ConsultarCUITsPaises()` | 2016-2028 | CUIT por pais |
| `WSCT_ResolverIdImpositivoPais()` | 2039-2062 | ID impositivo por pais |
| `WSCT_ConsultarCondicionesIVA()` | 2064-2117 | Condiciones IVA |
| `WSCT_ConsultarTiposItem()` | 2119-2182 | Tipos de items |
| `WSCT_ConsultarCodigosItemTurismo()` | 2184-2248 | Codigos turismo |

#### Utilidades
| Funcion | Linea | Proposito |
|---------|-------|-----------|
| `calc_neto()` | 1800-1805 | Calcula monto neto (sin IVA) |
| `calc_iva()` | 1807-1812 | Calcula monto IVA |
| `mungXML()` | 1839-1876 | Elimina namespaces de XML |
| `add_zeros()` | 1903-1935 | Formatea numero factura con ceros |
| `actualizar_monto_por_id()` | 955-974 | Actualiza monto en BD |
| `CheckNumber()` | 1828-1836 | Verifica si numero es positivo/negativo |

### 8.3 Archivos JavaScript

| Archivo | Responsabilidad |
|---------|-----------------|
| `main.js` | Handlers AJAX para todas las operaciones del panel |

#### Funciones en `main.js`
| Funcion | Linea | Proposito |
|---------|-------|-----------|
| `initAuthCodeFunctionToken()` | 9-31 | Inicia autenticacion OAuth |
| `importTransactions()` | 44-70 | Importa transacciones por rango |
| `importTransactionsDateToday()` | 73-97 | Importa transacciones de hoy |
| `checkGetTransactionsNow()` | 99-122 | Actualiza transacciones |
| `initLoadRecordsTransactions()` | 124-146 | Carga inicial de registros |
| `genFacturar()` | 148-311 | Genera Factura Tipo B |
| `genFacturarT()` | 313-476 | Genera Factura Tipo T |
| `editModal()` | 479-490 | Abre modal edicion monto |
| `closeModal()` | 493-502 | Cierra modal |
| `guardarMonto()` | 505-558 | Guarda nuevo monto |
| `validateCamps()` | 602-672 | Valida campos de busqueda |

---

## 9. Diagramas de Flujo

### 9.1 Autenticacion AFIP

```
+-------------------+
|  Inicio Request   |
+--------+----------+
         |
         v
+-------------------+
| Verificar TRA.xml |
| existe?           |
+--------+----------+
         |
    +----+----+
    |         |
    v         v
  [NO]      [SI]
    |         |
    v         v
+-------+  +------------------+
|Crear  |  | Leer expiration  |
|nuevo  |  | del TRA existente|
|TRA    |  +--------+---------+
+---+---+           |
    |          +----+----+
    |          |         |
    |          v         v
    |      [Expirado] [Vigente]
    |          |         |
    |          v         |
    |    +-----+-----+   |
    |    |Crear nuevo|   |
    |    |TRA        |   |
    |    +-----+-----+   |
    |          |         |
    +----+-----+         |
         |               |
         v               |
+--------+---------+     |
| SignTRA() con    |     |
| OpenSSL PKCS#7   |     |
+--------+---------+     |
         |               |
         v               |
+--------+---------+     |
| CallWSAA()       |     |
| Enviar CMS       |     |
+--------+---------+     |
         |               |
         v               |
+--------+---------+     |
| Recibir Token +  |     |
| Sign             |     |
+--------+---------+     |
         |               |
         v               v
+--------+---------+-----+
| Usar Token + Sign      |
| para operaciones       |
+------------------------+
```

### 9.2 Emision Factura Tipo B

```
+--------------------+
| AJAX: gen_facturar |
| code_transaction   |
+--------+-----------+
         |
         v
+--------------------+
| Obtener Token/Sign |
| AFIP (ver 8.1)     |
+--------+-----------+
         |
         v
+--------------------+
| Consultar BD       |
| transaccion por ID |
+--------+-----------+
         |
         v
+--------------------+
| Ya tiene           |
| invoiceUrl?        |
+--------+-----------+
         |
    +----+----+
    |         |
    v         v
  [SI]      [NO]
    |         |
    v         v
+-------+  +------------------+
|Generar|  |FECompUltimo      |
|PDF    |  |Autorizado()      |
|exist. |  +--------+---------+
+---+---+           |
    |               v
    |    +------------------+
    |    |Calcular montos:  |
    |    |Neto = Total/1.21 |
    |    |IVA = Neto * 0.21 |
    |    +--------+---------+
    |               |
    |               v
    |    +------------------+
    |    |Determinar DocTipo|
    |    |96=DNI AR         |
    |    |94=Pasaporte      |
    |    |99=Sin doc        |
    |    +--------+---------+
    |               |
    |               v
    |    +------------------+
    |    |Construir XML SOAP|
    |    |CbteTipo=6        |
    |    |PtoVta=2          |
    |    +--------+---------+
    |               |
    |               v
    |    +------------------+
    |    |FECAESolicitar()  |
    |    |POST a AFIP       |
    |    +--------+---------+
    |               |
    |          +----+----+
    |          |         |
    |          v         v
    |       [A]       [R]
    |          |         |
    |          v         v
    |    +--------+ +--------+
    |    |Guardar | |Guardar |
    |    |XML ok  | |_ERROR  |
    |    +----+---+ +----+---+
    |         |          |
    |         v          v
    |    +--------+ +--------+
    |    |generar | |Notifi- |
    |    |PDF     | |car     |
    |    +----+---+ |error   |
    |         |     +--------+
    +----+----+
         |
         v
+--------+---------+
| generate_qr()    |
+--------+---------+
         |
         v
+--------+---------+
| generatePDF()    |
| con DomPDF       |
+--------+---------+
         |
         v
+--------+---------+
| sendPDF() a      |
| CloudBeds        |
+--------+---------+
         |
         v
+--------+---------+
| saveUrlFile()    |
| en BD            |
+--------+---------+
         |
         v
+--------+---------+
| Retornar URL     |
| para descarga    |
+------------------+
```

### 9.3 Flujo de Datos

```
+-------------+     OAuth      +-------------+
|  CloudBeds  |<-------------->|   Sistema   |
|     API     |   REST API     |   Plugin    |
+------+------+                +------+------+
       |                              |
       | Transacciones                | Certificados
       | Huespedes                    | Token/Sign
       |                              |
       v                              v
+------+------+                +------+------+
|    MySQL    |                |    AFIP     |
| WordPress   |                |    WSAA     |
| wp_hotels_* |                |    WSFE     |
+-------------+                |    WSCT     |
                               +-------------+
                                      |
                                      | CAE
                                      | Nro Factura
                                      |
                                      v
                               +------+------+
                               |    PDF      |
                               |  DomPDF +   |
                               |  QRCode     |
                               +-------------+
```

---

## 10. Tipos de Facturas Soportadas

### 10.1 Factura Tipo B (Consumidor Final)

| Caracteristica | Valor |
|----------------|-------|
| Codigo AFIP | CbteTipo = 6 |
| Web Service | WSFE |
| Endpoint | `servicios1.afip.gov.ar/wsfev1/service.asmx` |
| Punto de Venta | 2 |
| Estado | **FUNCIONAL** |

**Tipos de Documento soportados:**
- `DocTipo=96`: DNI (Argentina)
- `DocTipo=94`: Pasaporte/CDI (Extranjeros)
- `DocTipo=99`: Sin documento (montos menores)

**Estructura XML:**
```xml
<FECAESolicitar>
  <Auth>
    <Token>...</Token>
    <Sign>...</Sign>
    <Cuit>30718446976</Cuit>
  </Auth>
  <FeCAEReq>
    <FeCabReq>
      <CantReg>1</CantReg>
      <PtoVta>2</PtoVta>
      <CbteTipo>6</CbteTipo>  <!-- Tipo B -->
    </FeCabReq>
    <FeDetReq>
      <FECAEDetRequest>
        <Concepto>1</Concepto>
        <DocTipo>96</DocTipo>
        <DocNro>12345678</DocNro>
        <CbteDesde>X</CbteDesde>
        <CbteHasta>X</CbteHasta>
        <CbteFch>20251226</CbteFch>
        <ImpTotal>1210.00</ImpTotal>
        <ImpNeto>1000.00</ImpNeto>
        <ImpIVA>210.00</ImpIVA>
        <MonId>PES</MonId>
        <MonCotiz>1</MonCotiz>
        <Iva>
          <AlicIva>
            <Id>5</Id>  <!-- 21% -->
            <BaseImp>1000.00</BaseImp>
            <Importe>210.00</Importe>
          </AlicIva>
        </Iva>
      </FECAEDetRequest>
    </FeDetReq>
  </FeCAEReq>
</FECAESolicitar>
```

### 10.2 Factura Tipo T (Turismo/Extranjeros)

| Caracteristica | Valor |
|----------------|-------|
| Codigo AFIP | codigoTipoComprobante = 195 |
| Web Service | WSCT |
| Endpoint | `serviciosjava.afip.gob.ar/wsct/CTService` |
| Punto de Venta | 2 |
| Estado | **EN DESARROLLO** |

**Caracteristicas especiales:**
- Beneficio de reintegro de IVA para turistas extranjeros
- Requiere datos adicionales del turista (pais, ID impositivo)
- Codigos de turismo especificos (alojamiento, desayuno, etc.)
- El IVA se "reintegra" (resta del total)

**Codigos de Turismo:**
| Codigo | Descripcion |
|--------|-------------|
| 1 | Alojamiento sin desayuno |
| 2 | Alojamiento con desayuno |
| 3 | Desayuno |
| ... | Otros servicios turisticos |

---

## 11. Estado Actual de Factura Tipo T

### Analisis del Codigo Existente

La funcion `FECAESolicitarTipoT()` (lineas 1251-1384) esta **parcialmente implementada pero comentada**:

```php
function FECAESolicitarTipoT($Token, $Sign, $Cuit, $code_transaction, $timenow) {
    // --- MONTOS DE PRUEBA ---
    $gravado = 10.00;
    $iva     = $gravado * 0.21;
    $total   = $gravado + $iva;

    // ... codigo XML comentado ...

    return $code_transaction;  // Solo retorna el ID, no hace nada
}
```

### Elementos Ya Implementados

1. **Autenticacion WSCT**: `CreateTRATipoT()` y `SignTRATipoT()` funcionan correctamente
2. **Ultimo comprobante**: `FECompUltimoAutorizadoTipoT()` esta funcional
3. **Consultas auxiliares**: Todas las funciones WSCT_Consultar* estan implementadas
4. **Handler AJAX**: `gen_facturar_tipo_t` existe pero llama a funcion incompleta
5. **Boton UI**: Existe en `transactions-list_dev.php` (linea 386: "FT")

### Elementos Faltantes

1. **Construccion XML real**: El XML SOAP esta comentado
2. **Obtencion datos reales**: Usa datos hardcodeados de prueba
3. **Logica de respuesta**: No procesa la respuesta de AFIP
4. **Generacion PDF Tipo T**: No existe template diferenciado
5. **Validacion de pais**: No determina si el huesped es extranjero
6. **Mapeo de paises**: No convierte codigo pais CloudBeds a AFIP

---

## 12. Plan de Implementacion - Factura Tipo T

### Fase 1: Preparacion y Validaciones

#### 1.1 Verificar Habilitacion AFIP
**Archivo**: N/A (manual)
**Tareas**:
- Verificar que el CUIT 30-71844697-6 este habilitado para WSCT
- Confirmar punto de venta 2 habilitado para comprobantes tipo 195
- Verificar certificado digital valido para servicio `wsct`

#### 1.2 Agregar Campo de Pais a BD
**Archivo**: `init.php`
**Modificacion**: La tabla ya tiene campo `country`, verificar que se guarda correctamente

#### 1.3 Crear Mapeo de Paises
**Archivo**: `main_global.php` (nuevo array o funcion)
**Contenido**:
```php
// Mapeo codigo pais ISO (CloudBeds) a codigo AFIP
$PAISES_AFIP = [
    'UY' => 225,  // Uruguay
    'BR' => 105,  // Brasil
    'CL' => 113,  // Chile
    'US' => 212,  // Estados Unidos
    'ES' => 123,  // Espana
    // ... agregar segun necesidad
];
```

### Fase 2: Logica de Negocio

#### 2.1 Modificar `FECAESolicitarTipoT()`
**Archivo**: `main_global.php` (lineas 1251-1384)
**Tareas**:
1. Obtener datos reales de transaccion desde BD
2. Determinar codigo de pais AFIP
3. Obtener ID impositivo segun pais
4. Calcular montos correctamente (con reintegro IVA)
5. Descomentar y completar XML SOAP
6. Implementar logica de respuesta

#### 2.2 Estructura XML Tipo T Correcta
```xml
<soapenv:Envelope xmlns:soapenv="..." xmlns:cts="...">
  <soapenv:Body>
    <cts:autorizarComprobanteRequest>
      <authRequest>
        <token>...</token>
        <sign>...</sign>
        <cuitRepresentada>30718446976</cuitRepresentada>
      </authRequest>
      <comprobanteRequest>
        <codigoTipoComprobante>195</codigoTipoComprobante>
        <numeroPuntoVenta>2</numeroPuntoVenta>
        <numeroComprobante>X</numeroComprobante>
        <fechaEmision>2025-12-26</fechaEmision>
        <codigoTipoAutorizacion>E</codigoTipoAutorizacion>
        <codigoTipoDocumento>94</codigoTipoDocumento>
        <numeroDocumento>PASAPORTE</numeroDocumento>
        <idImpositivo>ID_PAIS</idImpositivo>
        <codigoPais>XXX</codigoPais>
        <domicilioReceptor>DIRECCION</domicilioReceptor>
        <codigoRelacionEmisorReceptor>1</codigoRelacionEmisorReceptor>
        <importeGravado>1000.00</importeGravado>
        <importeReintegro>-210.00</importeReintegro>
        <importeTotal>1000.00</importeTotal>
        <codigoMoneda>PES</codigoMoneda>
        <cotizacionMoneda>1</cotizacionMoneda>
        <arrayItems>
          <item>
            <tipo>0</tipo>
            <codigoTurismo>1</codigoTurismo>
            <descripcion>Alojamiento</descripcion>
            <codigoAlicuotaIVA>5</codigoAlicuotaIVA>
            <importeIVA>210.00</importeIVA>
            <importeItem>1210.00</importeItem>
          </item>
        </arrayItems>
        <arraySubtotalesIVA>
          <subtotalIVA>
            <codigo>5</codigo>
            <importe>210.00</importe>
          </subtotalIVA>
        </arraySubtotalesIVA>
      </comprobanteRequest>
    </cts:autorizarComprobanteRequest>
  </soapenv:Body>
</soapenv:Envelope>
```

### Fase 3: Interfaz de Usuario

#### 3.1 Agregar Logica de Seleccion Automatica
**Archivo**: `transactions-list.php` o `main_global.php`
**Logica**:
```
SI pais != 'AR' ENTONCES
    Mostrar boton "FT" (Factura Turismo)
SINO
    Mostrar boton "FB" (Factura B)
FIN SI
```

#### 3.2 Modificar Vista de Transacciones
**Archivo**: `transactions-list.php`
**Tareas**:
- Agregar columna "Pais"
- Mostrar boton apropiado segun nacionalidad
- O mostrar ambos botones y dejar elegir al usuario

### Fase 4: Generacion de PDF

#### 4.1 Crear Template PDF Tipo T
**Archivo**: `main_global.php` (nueva funcion `generatePDFTipoT()`)
**Diferencias con Tipo B**:
- Encabezado: "FACTURA T COD. 195" en lugar de "B COD. 006"
- Seccion adicional: Datos del turista extranjero
- Seccion: Reintegro de IVA
- Leyenda especifica de turismo

### Fase 5: Testing

#### 5.1 Pruebas en Homologacion
1. Configurar endpoint de homologacion WSCT
2. Probar autenticacion
3. Probar emision con datos de prueba
4. Verificar respuestas

#### 5.2 Pruebas en Produccion
1. Emision con transaccion real de turista
2. Verificar PDF generado
3. Verificar envio a CloudBeds

### Resumen de Archivos a Modificar

| Archivo | Modificaciones |
|---------|----------------|
| `main_global.php` | Completar `FECAESolicitarTipoT()`, agregar mapeo paises, crear `generatePDFTipoT()` |
| `transactions-list.php` | Agregar logica seleccion tipo factura, mostrar pais |
| `main.js` | Completar handler `genFacturarT()` con logica de respuesta |
| `init.php` | (Opcional) Agregar campos adicionales a BD si se requiere |

### Estimacion de Complejidad

| Fase | Complejidad | Dependencias |
|------|-------------|--------------|
| Fase 1 | Baja | Habilitacion AFIP |
| Fase 2 | Alta | Fase 1 |
| Fase 3 | Media | Fase 2 |
| Fase 4 | Media | Fase 2 |
| Fase 5 | Media | Fases 1-4 |

---

## Anexos

### A. Constantes Definidas

```php
// AFIP
define("WSDL", "../afip/wsaa.wsdl");
define("CERT", "../afip/cert/facturacion2025.pem");
define("PRIVATEKEY", "../afip/cert/MiClavePrivada.key");
define("PASSPHRASE", "");
define("PROXY_HOST", "10.20.152.112");
define("PROXY_PORT", "80");
define("URL", "https://wsaa.afip.gov.ar/ws/services/LoginCms");
define("CUIT", "30718446976");

// URLs archivos
define("URL_WEB_FILES", "https://contable.penthouse1004.com/.../invoicesxml/");
define("URL_WEB_FILES_PDF", "https://contable.penthouse1004.com/.../invoicespdf/");
define("URL_WEB_FILES_QR", "https://contable.penthouse1004.com/.../hotels/");

// CloudBeds
define("CALLBACK_URL", "https://contable.penthouse1004.com/wp-admin/admin.php?page=transactions_list");
define("AUTH_URL", "https://hotels.cloudbeds.com/api/v1.2/oauth");
define("ACCESS_TOKEN_URL", "https://hotels.cloudbeds.com/api/v1.2/access_token");
define("TRANSACTIONS_URL", "https://hotels.cloudbeds.com/api/v1.2/getTransactions");
define("GUEST_URL", "https://hotels.cloudbeds.com/api/v1.2/getGuest");
define("SENDPDF_URL", "https://hotels.cloudbeds.com/api/v1.2/postReservationDocument");
```

### B. Codigos de Tipo de Documento AFIP

| Codigo | Descripcion |
|--------|-------------|
| 80 | CUIT |
| 86 | CUIL |
| 87 | CDI |
| 89 | LE |
| 90 | LC |
| 91 | CI Extranjera |
| 92 | En tramite |
| 93 | Acta Nacimiento |
| 94 | Pasaporte |
| 95 | CI (Bs As RNP) |
| 96 | DNI |
| 99 | Doc. (Otro) / CF sin doc. |

### C. Codigos de Tipo de Comprobante AFIP

| Codigo | Descripcion |
|--------|-------------|
| 1 | Factura A |
| 2 | Nota de Debito A |
| 3 | Nota de Credito A |
| 6 | Factura B |
| 7 | Nota de Debito B |
| 8 | Nota de Credito B |
| 11 | Factura C |
| 195 | Factura T (Turismo) |
| 196 | Nota de Debito T |
| 197 | Nota de Credito T |

### D. Codigos de Alicuota IVA

| Codigo | Alicuota |
|--------|----------|
| 3 | 0% |
| 4 | 10.5% |
| 5 | 21% |
| 6 | 27% |
| 8 | 5% |
| 9 | 2.5% |

---

*Documento generado el 26 de Diciembre de 2025*
*Sistema: Plugin WordPress "Services API Hotel" v1.1*
*Autor original del plugin: Osward Pacheco*
