# Plan de Refactorizacion - Plugin AFIP Hotel 1004

## CONTEXTO PARA CLAUDE

Este documento sirve como prompt completo para una sesion de Claude donde se refactorizara el plugin de facturacion electronica desde cero. Lee este documento completo antes de comenzar cualquier trabajo.

---

## ⚠️ ADVERTENCIAS CRITICAS - LEER ANTES DE COMENZAR

### ARCHIVOS QUE NO DEBEN ELIMINARSE

La carpeta `php/afip/` contiene archivos **CRITICOS** que NO deben eliminarse ni moverse sin cuidado:

```
php/afip/
├── cert/                        # ⛔ NO ELIMINAR - Certificados de produccion
│   ├── facturacion2025.pem      # Certificado X.509 AFIP
│   ├── MiClavePrivada.key       # Clave privada RSA
│   └── certificado.crt          # Certificado formato CRT
│
├── cert_preprod/                # ⛔ NO ELIMINAR - Certificados pre-produccion
│
├── wsaa.wsdl                    # ⛔ NO ELIMINAR - Definicion servicio autenticacion
├── wsfe.wsdl                    # ⛔ NO ELIMINAR - Definicion servicio Factura B
├── wsfe-production.wsdl         # ⛔ NO ELIMINAR - WSDL produccion
├── wsct.wsdl                    # ⛔ NO ELIMINAR - Definicion servicio Factura T (si existe)
│
├── TRA_WSFE.xml                 # ✓ Se regenera - Token Request Auth Tipo B
├── TRA_WSCT.xml                 # ✓ Se regenera - Token Request Auth Tipo T
├── response-loginCms.xml        # ✓ Se regenera - Respuesta autenticacion
└── request-loginCms.xml         # ✓ Se regenera - Request debug
```

### REGLAS PARA LA REFACTORIZACION

1. **NUNCA eliminar** archivos `.wsdl`, `.pem`, `.key`, `.crt`
2. **COPIAR** estos archivos a la nueva estructura, no mover
3. **VERIFICAR** que los certificados funcionan antes de eliminar la carpeta original
4. Los archivos `TRA_*.xml` y `response-*.xml` se regeneran automaticamente
5. **MANTENER** la carpeta `php/afip/` funcional hasta completar la migracion

### CARPETAS A ELIMINAR (Migrar a WordPress Uploads)

Las siguientes carpetas **seran eliminadas** del plugin y migradas a `wp-content/uploads/`:

```
php/invoicespdf/     # ❌ ELIMINAR - Migrar PDFs a uploads
php/invoicespdft/    # ❌ ELIMINAR - Migrar PDFs a uploads
php/invoicesxml/     # ❌ ELIMINAR - Migrar XMLs a uploads
php/invoicesxmlt/    # ❌ ELIMINAR - Migrar XMLs a uploads
php/consultas/pdfs/  # ❌ ELIMINAR - Temporal
php/consultas/qrcodes/ # ❌ ELIMINAR - Temporal
```

### NUEVA UBICACION DE ARCHIVOS (WordPress Uploads)

```
wp-content/uploads/sah-invoices/
├── 2024/
│   ├── 01/
│   │   ├── pdf/
│   │   │   ├── factura-b/
│   │   │   └── factura-t/
│   │   └── xml/
│   │       ├── factura-b/
│   │       └── factura-t/
│   └── 02/
│       └── ...
├── 2025/
│   └── ...
└── 2026/
    └── ...
```

**Ventajas de usar WordPress Uploads:**
- Los archivos persisten aunque se actualice/elimine el plugin
- Respeta la estructura de WordPress
- Facil backup con herramientas estandar
- URLs publicas manejadas por WordPress
- Compatible con CDN si se configura

---

## 1. RESUMEN EJECUTIVO DEL PROYECTO

### 1.1 Que es este proyecto?

Plugin de WordPress para el **Hotel Penthouse 1004** (Bariloche, Argentina) que:

1. **Importa transacciones** desde CloudBeds (sistema de gestion hotelera) via API REST
2. **Genera facturas electronicas** ante AFIP (autoridad fiscal argentina):
   - **Factura B** (CbteTipo=6): Para consumidores finales argentinos
   - **Factura T** (CbteTipo=195): Para turistas extranjeros (reintegro IVA)
3. **Genera PDFs** de las facturas con codigo QR
4. **Sube los PDFs** de vuelta a CloudBeds

### 1.2 Datos del Emisor

| Campo | Valor |
|-------|-------|
| Razon Social | DEVA S.A.S |
| CUIT | 30-71844697-6 |
| Punto de Venta | 2 |
| Condicion IVA | Responsable Inscripto |

### 1.3 Integraciones Externas

| Servicio | Protocolo | Proposito |
|----------|-----------|-----------|
| CloudBeds API | REST + OAuth 2.0 | Obtener transacciones y huespedes |
| AFIP WSAA | SOAP + Certificados X.509 | Autenticacion (token/sign) |
| AFIP WSFE | SOAP | Factura B (Web Service Facturacion Electronica) |
| AFIP WSCT | SOAP | Factura T (Web Service Comprobantes Turismo) |

---

## 2. ESTADO ACTUAL DEL CODIGO (PROBLEMAS)

### 2.1 Metricas Criticas

| Metrica | Valor Actual | Objetivo |
|---------|--------------|----------|
| Lineas en archivo mas grande | 3,196 (main_global.php) | < 400 |
| Total clases PHP | 3 | > 20 |
| Namespaces usados | 0 | 100% |
| Cobertura de tests | 0% | > 80% |
| Archivos duplicados | 3+ pares | 0 |
| Queries preparadas | ~30% | 100% |

### 2.2 Problemas Arquitectonicos

#### PROBLEMA 1: Archivo Monolitico
```
main_global.php (3,196 lineas) contiene:
- Handlers AJAX
- Autenticacion AFIP (WSAA)
- Facturacion Tipo B (WSFE)
- Facturacion Tipo T (WSCT)
- Generacion de PDF
- Generacion de QR/Barcode
- Integracion CloudBeds
- Queries a base de datos
- Todo mezclado sin separacion
```

#### PROBLEMA 2: Codigo Procedural
```php
// Ejemplo actual - funcion de 500+ lineas
function ajax_foo_handler() {
    global $wpdb, $table_name_config, $table_name_transactions;

    if (isset($_POST['action1'])) { /* 100 lineas */ }
    if (isset($_POST['action2'])) { /* 100 lineas */ }
    if (isset($_POST['gen_facturar'])) { /* 200 lineas */ }
    if (isset($_POST['gen_facturar_tipo_t'])) { /* 200 lineas */ }
    // ... mas acciones
}
```

#### PROBLEMA 3: Sin Autoloading
```php
// init.php actual - requires manuales
require_once(SAH_PLUGIN_DIR . 'php/config_init/config-list.php');
require_once(SAH_PLUGIN_DIR . 'php/config_init/config-create.php');
require_once(SAH_PLUGIN_DIR . 'php/config_init/config-update.php');
require_once(SAH_PLUGIN_DIR . 'php/config_init/transactions-list.php');
require_once(SAH_PLUGIN_DIR . 'php/consultas/main_global.php');
// ... 15+ mas
```

#### PROBLEMA 4: Archivos Duplicados
```
transactions-list.php     vs  transactions-list_dev.php
logs-list.php            vs  logs-dev.php
main_global.php          vs  main_global_preprod.php
```

#### PROBLEMA 5: Valores Hardcodeados
```php
define("PROXY_HOST", "10.20.152.112");
define("PROXY_PORT", "80");
define("CUIT", "30718446976");
$baseURLN = 'datacita';  // Magic string
```

#### PROBLEMA 6: SQL No Preparado
```php
// Vulnerable a SQL injection
$sql = "SELECT * FROM $table_name WHERE id = " . $_POST['id'];
```

### 2.3 Estructura de Directorios Actual

```
afiph1004/
├── css/                    # Estilos (OK)
├── includes/               # Solo 3 clases
│   ├── class-activator.php
│   ├── class-deactivator.php
│   └── class-logger.php
├── js/
│   └── main.js             # 680 lineas jQuery
├── php/                    # CAOS
│   ├── afip/               # Certificados AFIP
│   ├── config/             # 1 archivo config
│   ├── config_init/        # Pages admin (duplicadas)
│   ├── consultas/          # main_global.php (3196 lineas!)
│   ├── invoicespdf/        # Output Factura B
│   ├── invoicespdft/       # Output Factura T
│   ├── invoicesxml/        # XML Factura B
│   ├── invoicesxmlt/       # XML Factura T
│   └── varios/             # Miscelaneo
├── init.php                # Entry point
└── composer.json           # Solo 2 deps
```

---

## 3. ARQUITECTURA PROPUESTA

### 3.1 Nueva Estructura de Directorios

```
afiph1004/
├── assets/
│   ├── css/
│   │   ├── admin.css
│   │   └── invoice.css
│   └── js/
│       ├── admin/
│       │   ├── transactions.js
│       │   ├── config.js
│       │   └── logs.js
│       └── shared/
│           └── ajax-handler.js
│
├── config/
│   ├── afip/
│   │   ├── cert/                    # Certificados (gitignored)
│   │   ├── wsdl/                    # Archivos WSDL
│   │   └── tra/                     # TRA XMLs (gitignored)
│   └── plugin-config.php            # Constantes del plugin
│
├── src/                             # CODIGO PRINCIPAL (PSR-4)
│   ├── Admin/
│   │   ├── AdminMenu.php            # Registro de menus
│   │   ├── Pages/
│   │   │   ├── ConfigPage.php
│   │   │   ├── TransactionsPage.php
│   │   │   └── LogsPage.php
│   │   └── Ajax/
│   │       ├── AjaxHandler.php      # Router AJAX
│   │       ├── ConfigAjax.php
│   │       ├── TransactionAjax.php
│   │       └── InvoiceAjax.php
│   │
│   ├── Services/
│   │   ├── AFIP/
│   │   │   ├── AFIPAuthService.php      # WSAA (autenticacion)
│   │   │   ├── AFIPInvoiceService.php   # WSFE + WSCT
│   │   │   ├── TRAGenerator.php         # Genera TRA XML
│   │   │   └── SOAPClient.php           # Cliente SOAP wrapper
│   │   │
│   │   ├── CloudBeds/
│   │   │   ├── CloudBedsClient.php      # API REST client
│   │   │   ├── OAuthService.php         # OAuth 2.0 flow
│   │   │   └── TransactionSync.php      # Sincronizacion
│   │   │
│   │   ├── Invoice/
│   │   │   ├── InvoiceService.php       # Logica de negocio
│   │   │   ├── InvoiceBGenerator.php    # Factura B
│   │   │   ├── InvoiceTGenerator.php    # Factura T
│   │   │   └── InvoiceCalculator.php    # Calculos IVA/Neto
│   │   │
│   │   ├── PDF/
│   │   │   ├── PDFGenerator.php         # Wrapper DomPDF
│   │   │   ├── InvoiceBTemplate.php     # Template Factura B
│   │   │   └── InvoiceTTemplate.php     # Template Factura T
│   │   │
│   │   └── QR/
│   │       └── QRGenerator.php          # Codigo QR AFIP
│   │
│   ├── Repositories/
│   │   ├── ConfigRepository.php         # wp_hotels_config
│   │   ├── TransactionRepository.php    # wp_hotels_transactions
│   │   └── LogRepository.php            # wp_hotels_logs
│   │
│   ├── Models/
│   │   ├── Config.php
│   │   ├── Transaction.php
│   │   ├── Invoice.php
│   │   └── Guest.php
│   │
│   ├── Helpers/
│   │   ├── CountryMapper.php            # ISO -> AFIP codes
│   │   ├── DocumentTypeMapper.php       # DNI, Pasaporte, etc
│   │   └── DateHelper.php
│   │
│   ├── Storage/
│   │   └── FileStorage.php              # Manejo de archivos en WordPress uploads
│   │
│   └── Core/
│       ├── Plugin.php                   # Clase principal
│       ├── Activator.php
│       ├── Deactivator.php
│       └── Logger.php
│
├── templates/
│   ├── admin/
│   │   ├── transactions-list.php
│   │   ├── config-form.php
│   │   └── logs-list.php
│   └── invoice/
│       ├── factura-b.php
│       └── factura-t.php
│
├── tests/
│   ├── Unit/
│   │   ├── Services/
│   │   ├── Repositories/
│   │   └── Helpers/
│   └── Integration/
│       ├── AFIPIntegrationTest.php
│       └── CloudBedsIntegrationTest.php
│
├── vendor/                              # Composer (gitignored)
├── init.php                             # Entry point (minimo)
├── uninstall.php
├── composer.json
├── phpunit.xml
└── .env.example
```

### 3.2 Namespace Structure

```php
namespace SAH;                           // Root
namespace SAH\Admin;                     // Admin pages & AJAX
namespace SAH\Admin\Pages;
namespace SAH\Admin\Ajax;
namespace SAH\Services;                  // Business logic
namespace SAH\Services\AFIP;
namespace SAH\Services\CloudBeds;
namespace SAH\Services\Invoice;
namespace SAH\Services\PDF;
namespace SAH\Services\QR;
namespace SAH\Storage;                   // File storage (WordPress uploads)
namespace SAH\Repositories;              // Data access
namespace SAH\Models;                    // Data structures
namespace SAH\Helpers;                   // Utilities
namespace SAH\Core;                      // Plugin core
```

### 3.3 Composer.json Propuesto

```json
{
    "name": "deva/afip-hotel-1004",
    "description": "Plugin de facturacion electronica AFIP para Hotel Penthouse 1004",
    "type": "wordpress-plugin",
    "license": "proprietary",
    "authors": [
        {
            "name": "DEVA S.A.S",
            "email": "dev@penthouse1004.com"
        }
    ],
    "require": {
        "php": ">=8.0",
        "dompdf/dompdf": "^2.0",
        "picqer/php-barcode-generator": "^2.2",
        "monolog/monolog": "^3.0"
    },
    "require-dev": {
        "phpunit/phpunit": "^10.0",
        "mockery/mockery": "^1.6",
        "squizlabs/php_codesniffer": "^3.7",
        "phpstan/phpstan": "^1.10"
    },
    "autoload": {
        "psr-4": {
            "SAH\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "SAH\\Tests\\": "tests/"
        }
    },
    "scripts": {
        "test": "phpunit",
        "lint": "phpcs --standard=WordPress src/",
        "analyze": "phpstan analyse src/ --level=6"
    },
    "config": {
        "optimize-autoloader": true,
        "sort-packages": true
    }
}
```

---

## 4. PLAN DE IMPLEMENTACION POR FASES

### FASE 0: PREPARACION (1-2 dias)

#### Objetivos
- [ ] Crear backup del proyecto actual
- [ ] Configurar entorno de desarrollo
- [ ] Establecer estructura base

#### Tareas

**0.1 Backup y Version Control**
```bash
# Crear tag del estado actual
git tag -a v1.0-legacy -m "Estado antes de refactorizacion"
git push origin v1.0-legacy

# Crear rama de refactorizacion
git checkout -b refactor/v2.0
```

**0.2 Crear Estructura de Directorios**
```bash
mkdir -p src/{Admin/{Pages,Ajax},Services/{AFIP,CloudBeds,Invoice,PDF,QR},Repositories,Models,Helpers,Core}
mkdir -p assets/{css,js/{admin,shared}}
mkdir -p config/afip/{cert,wsdl,tra}
mkdir -p templates/{admin,invoice}
mkdir -p tests/{Unit/{Services,Repositories,Helpers},Integration}

# Crear estructura en WordPress uploads (se crea dinamicamente en el codigo)
# wp-content/uploads/sah-invoices/{year}/{month}/{pdf|xml}/{factura-b|factura-t}/
```

**0.3 Configurar Composer**
- Actualizar composer.json con autoload PSR-4
- Ejecutar `composer dump-autoload`
- Agregar dependencias de desarrollo

**0.4 Crear archivo .env.example**
```env
# AFIP Configuration
AFIP_ENVIRONMENT=production
AFIP_CUIT=30718446976
AFIP_PUNTO_VENTA=2
AFIP_CERT_PATH=config/afip/cert/facturacion2025.pem
AFIP_KEY_PATH=config/afip/cert/MiClavePrivada.key
AFIP_PASSPHRASE=

# CloudBeds Configuration
CLOUDBEDS_CLIENT_ID=
CLOUDBEDS_CLIENT_SECRET=
CLOUDBEDS_REDIRECT_URI=

# Plugin Settings
DEBUG_MODE=false
LOG_LEVEL=info
```

---

### FASE 1: CORE Y MODELOS (2-3 dias)

#### Objetivos
- [ ] Crear clase Plugin principal
- [ ] Implementar Activator/Deactivator
- [ ] Definir modelos de datos
- [ ] Migrar Logger existente

#### Archivos a Crear

**1.1 src/Core/Plugin.php**
```php
<?php
namespace SAH\Core;

class Plugin {
    private static ?Plugin $instance = null;
    private string $version = '2.0.0';

    public static function getInstance(): Plugin {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init(): void {
        $this->loadDependencies();
        $this->defineAdminHooks();
        $this->definePublicHooks();
    }

    private function loadDependencies(): void {
        // Composer autoload handles everything
    }

    private function defineAdminHooks(): void {
        $adminMenu = new \SAH\Admin\AdminMenu();
        add_action('admin_menu', [$adminMenu, 'register']);

        $ajaxHandler = new \SAH\Admin\Ajax\AjaxHandler();
        add_action('wp_ajax_sah_action', [$ajaxHandler, 'handle']);
    }

    public function activate(): void {
        Activator::activate();
    }

    public function deactivate(): void {
        Deactivator::deactivate();
    }
}
```

**1.2 src/Models/Transaction.php**
```php
<?php
namespace SAH\Models;

class Transaction {
    public int $id;
    public string $propertyID;
    public string $transactionID;
    public string $reservationID;
    public string $guestID;
    public \DateTime $transactionDateTime;
    public string $completeName;
    public string $description;
    public ?string $passportNumber;
    public float $amount;
    public string $currency;
    public string $country;
    public ?string $city;
    public ?string $address;
    public ?string $invoiceUrl;
    public string $transactionType;

    public static function fromArray(array $data): self {
        $transaction = new self();
        // Map array to properties
        return $transaction;
    }

    public function toArray(): array {
        return get_object_vars($this);
    }

    public function isArgentinian(): bool {
        return strtoupper($this->country) === 'AR';
    }

    public function hasInvoice(): bool {
        return !empty($this->invoiceUrl);
    }
}
```

**1.3 src/Models/Invoice.php**
```php
<?php
namespace SAH\Models;

class Invoice {
    public const TYPE_B = 6;
    public const TYPE_T = 195;

    public int $type;
    public int $puntoVenta;
    public int $numero;
    public \DateTime $fecha;
    public string $cae;
    public \DateTime $fechaVencimientoCae;

    public float $importeTotal;
    public float $importeNeto;
    public float $importeIVA;
    public float $importeReintegro = 0;

    public Transaction $transaction;
    public Guest $guest;

    public function isFacturaB(): bool {
        return $this->type === self::TYPE_B;
    }

    public function isFacturaT(): bool {
        return $this->type === self::TYPE_T;
    }

    public function getCodigoComprobante(): string {
        return str_pad($this->type, 3, '0', STR_PAD_LEFT);
    }

    public function getNumeroFormateado(): string {
        $pv = str_pad($this->puntoVenta, 4, '0', STR_PAD_LEFT);
        $num = str_pad($this->numero, 8, '0', STR_PAD_LEFT);
        return "{$pv}-{$num}";
    }
}
```

**1.4 src/Models/Guest.php**
```php
<?php
namespace SAH\Models;

class Guest {
    public string $id;
    public string $name;
    public string $lastName;
    public ?string $documentType;
    public ?string $documentNumber;
    public string $country;
    public ?string $city;
    public ?string $address;
    public ?string $email;

    public function getFullName(): string {
        return trim("{$this->name} {$this->lastName}");
    }

    public function getAFIPDocType(): int {
        if ($this->isArgentinian()) {
            return 96; // DNI
        }
        return 94; // Pasaporte
    }

    public function isArgentinian(): bool {
        return strtoupper($this->country) === 'AR';
    }
}
```

**1.5 src/Storage/FileStorage.php**
```php
<?php
namespace SAH\Storage;

/**
 * Servicio para manejar archivos en WordPress uploads
 *
 * Estructura de directorios:
 * wp-content/uploads/sah-invoices/
 * ├── 2024/
 * │   ├── 01/
 * │   │   ├── pdf/
 * │   │   │   ├── factura-b/
 * │   │   │   └── factura-t/
 * │   │   └── xml/
 * │   │       ├── factura-b/
 * │   │       └── factura-t/
 */
class FileStorage {
    private const BASE_FOLDER = 'sah-invoices';

    private string $basePath;
    private string $baseUrl;

    public function __construct() {
        $uploadDir = wp_upload_dir();
        $this->basePath = $uploadDir['basedir'] . '/' . self::BASE_FOLDER;
        $this->baseUrl = $uploadDir['baseurl'] . '/' . self::BASE_FOLDER;
    }

    /**
     * Guarda un archivo PDF de factura
     *
     * @param string $content Contenido del PDF
     * @param string $filename Nombre del archivo (sin ruta)
     * @param string $invoiceType 'factura-b' o 'factura-t'
     * @return array ['path' => ruta absoluta, 'url' => URL publica, 'relative' => ruta relativa]
     */
    public function savePdf(string $content, string $filename, string $invoiceType): array {
        $relativePath = $this->buildPath('pdf', $invoiceType, $filename);
        $absolutePath = $this->basePath . '/' . $relativePath;

        $this->ensureDirectoryExists(dirname($absolutePath));

        file_put_contents($absolutePath, $content);

        return [
            'path' => $absolutePath,
            'url' => $this->baseUrl . '/' . $relativePath,
            'relative' => self::BASE_FOLDER . '/' . $relativePath,
        ];
    }

    /**
     * Guarda un archivo XML de factura
     *
     * @param string $content Contenido del XML
     * @param string $filename Nombre del archivo (sin ruta)
     * @param string $invoiceType 'factura-b' o 'factura-t'
     * @return array ['path' => ruta absoluta, 'url' => URL publica, 'relative' => ruta relativa]
     */
    public function saveXml(string $content, string $filename, string $invoiceType): array {
        $relativePath = $this->buildPath('xml', $invoiceType, $filename);
        $absolutePath = $this->basePath . '/' . $relativePath;

        $this->ensureDirectoryExists(dirname($absolutePath));

        file_put_contents($absolutePath, $content);

        return [
            'path' => $absolutePath,
            'url' => $this->baseUrl . '/' . $relativePath,
            'relative' => self::BASE_FOLDER . '/' . $relativePath,
        ];
    }

    /**
     * Obtiene la URL publica de un archivo
     *
     * @param string $relativePath Ruta relativa desde sah-invoices/
     * @return string URL completa
     */
    public function getUrl(string $relativePath): string {
        // Si ya tiene el prefijo base, quitarlo
        if (strpos($relativePath, self::BASE_FOLDER) === 0) {
            $relativePath = substr($relativePath, strlen(self::BASE_FOLDER) + 1);
        }

        return $this->baseUrl . '/' . $relativePath;
    }

    /**
     * Obtiene la ruta absoluta de un archivo
     *
     * @param string $relativePath Ruta relativa desde sah-invoices/
     * @return string Ruta absoluta en el filesystem
     */
    public function getPath(string $relativePath): string {
        if (strpos($relativePath, self::BASE_FOLDER) === 0) {
            $relativePath = substr($relativePath, strlen(self::BASE_FOLDER) + 1);
        }

        return $this->basePath . '/' . $relativePath;
    }

    /**
     * Verifica si un archivo existe
     *
     * @param string $relativePath Ruta relativa
     * @return bool
     */
    public function exists(string $relativePath): bool {
        return file_exists($this->getPath($relativePath));
    }

    /**
     * Elimina un archivo
     *
     * @param string $relativePath Ruta relativa
     * @return bool
     */
    public function delete(string $relativePath): bool {
        $path = $this->getPath($relativePath);

        if (file_exists($path)) {
            return unlink($path);
        }

        return false;
    }

    /**
     * Lista archivos en un directorio
     *
     * @param string $directory Subdirectorio (ej: '2024/01/pdf/factura-b')
     * @return array Lista de archivos
     */
    public function listFiles(string $directory): array {
        $path = $this->basePath . '/' . $directory;

        if (!is_dir($path)) {
            return [];
        }

        $files = [];
        $iterator = new \DirectoryIterator($path);

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $files[] = [
                    'name' => $file->getFilename(),
                    'path' => $file->getPathname(),
                    'size' => $file->getSize(),
                    'modified' => $file->getMTime(),
                ];
            }
        }

        return $files;
    }

    /**
     * Construye la ruta de almacenamiento organizada por fecha
     *
     * @param string $type 'pdf' o 'xml'
     * @param string $invoiceType 'factura-b' o 'factura-t'
     * @param string $filename Nombre del archivo
     * @return string Ruta relativa (ej: '2024/01/pdf/factura-b/archivo.pdf')
     */
    private function buildPath(string $type, string $invoiceType, string $filename): string {
        $year = date('Y');
        $month = date('m');

        return "{$year}/{$month}/{$type}/{$invoiceType}/{$filename}";
    }

    /**
     * Asegura que el directorio existe, creandolo si es necesario
     *
     * @param string $directory Ruta absoluta del directorio
     */
    private function ensureDirectoryExists(string $directory): void {
        if (!is_dir($directory)) {
            wp_mkdir_p($directory);
        }
    }

    /**
     * Obtiene el directorio base de uploads
     *
     * @return string Ruta absoluta del directorio base
     */
    public function getBasePath(): string {
        return $this->basePath;
    }

    /**
     * Obtiene la URL base de uploads
     *
     * @return string URL base
     */
    public function getBaseUrl(): string {
        return $this->baseUrl;
    }

    /**
     * Migra un archivo desde la ubicacion antigua del plugin a WordPress uploads
     *
     * @param string $oldPath Ruta absoluta del archivo antiguo
     * @param string $invoiceType 'factura-b' o 'factura-t'
     * @param string $fileType 'pdf' o 'xml'
     * @return array|false Datos del nuevo archivo o false si falla
     */
    public function migrateFromLegacy(string $oldPath, string $invoiceType, string $fileType): array|false {
        if (!file_exists($oldPath)) {
            return false;
        }

        $filename = basename($oldPath);
        $content = file_get_contents($oldPath);

        if ($fileType === 'pdf') {
            return $this->savePdf($content, $filename, $invoiceType);
        } else {
            return $this->saveXml($content, $filename, $invoiceType);
        }
    }
}
```

---

### FASE 2: REPOSITORIOS Y BASE DE DATOS (2-3 dias)

#### Objetivos
- [ ] Crear capa de acceso a datos
- [ ] Implementar prepared statements
- [ ] Abstraer queries SQL

#### Archivos a Crear

**2.1 src/Repositories/BaseRepository.php**
```php
<?php
namespace SAH\Repositories;

abstract class BaseRepository {
    protected \wpdb $db;
    protected string $table;

    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;
    }

    protected function getTableName(): string {
        return $this->db->prefix . $this->table;
    }

    public function findById(int $id): ?array {
        return $this->db->get_row(
            $this->db->prepare(
                "SELECT * FROM {$this->getTableName()} WHERE id = %d",
                $id
            ),
            ARRAY_A
        );
    }

    public function findAll(array $conditions = [], int $limit = 100, int $offset = 0): array {
        $where = $this->buildWhereClause($conditions);

        return $this->db->get_results(
            $this->db->prepare(
                "SELECT * FROM {$this->getTableName()} {$where} LIMIT %d OFFSET %d",
                $limit,
                $offset
            ),
            ARRAY_A
        );
    }

    public function insert(array $data): int {
        $this->db->insert($this->getTableName(), $data);
        return $this->db->insert_id;
    }

    public function update(int $id, array $data): bool {
        return $this->db->update(
            $this->getTableName(),
            $data,
            ['id' => $id]
        ) !== false;
    }

    public function delete(int $id): bool {
        return $this->db->delete(
            $this->getTableName(),
            ['id' => $id]
        ) !== false;
    }

    protected function buildWhereClause(array $conditions): string {
        if (empty($conditions)) {
            return '';
        }

        $clauses = [];
        foreach ($conditions as $column => $value) {
            $clauses[] = $this->db->prepare("{$column} = %s", $value);
        }

        return 'WHERE ' . implode(' AND ', $clauses);
    }
}
```

**2.2 src/Repositories/TransactionRepository.php**
```php
<?php
namespace SAH\Repositories;

use SAH\Models\Transaction;

class TransactionRepository extends BaseRepository {
    protected string $table = 'hotels_transactions';

    public function findByTransactionId(string $transactionId): ?Transaction {
        $row = $this->db->get_row(
            $this->db->prepare(
                "SELECT * FROM {$this->getTableName()} WHERE transactionID = %s",
                $transactionId
            ),
            ARRAY_A
        );

        return $row ? Transaction::fromArray($row) : null;
    }

    public function findByDateRange(\DateTime $start, \DateTime $end): array {
        $results = $this->db->get_results(
            $this->db->prepare(
                "SELECT * FROM {$this->getTableName()}
                 WHERE transactionDateTime BETWEEN %s AND %s
                 ORDER BY transactionDateTime DESC",
                $start->format('Y-m-d H:i:s'),
                $end->format('Y-m-d H:i:s')
            ),
            ARRAY_A
        );

        return array_map(fn($row) => Transaction::fromArray($row), $results);
    }

    public function findPendingInvoices(): array {
        $results = $this->db->get_results(
            "SELECT * FROM {$this->getTableName()}
             WHERE invoiceUrl IS NULL OR invoiceUrl = ''
             ORDER BY transactionDateTime DESC",
            ARRAY_A
        );

        return array_map(fn($row) => Transaction::fromArray($row), $results);
    }

    public function updateInvoiceUrl(int $id, string $invoiceUrl): bool {
        return $this->update($id, ['invoiceUrl' => $invoiceUrl]);
    }

    public function existsByTransactionId(string $transactionId): bool {
        return $this->db->get_var(
            $this->db->prepare(
                "SELECT COUNT(*) FROM {$this->getTableName()} WHERE transactionID = %s",
                $transactionId
            )
        ) > 0;
    }
}
```

---

### FASE 3: SERVICIOS AFIP (4-5 dias)

#### Objetivos
- [ ] Extraer logica WSAA a clase dedicada
- [ ] Crear servicio de facturacion WSFE/WSCT
- [ ] Implementar manejo de errores robusto

#### Archivos a Crear

**3.1 src/Services/AFIP/AFIPAuthService.php**
```php
<?php
namespace SAH\Services\AFIP;

use SAH\Core\Logger;

class AFIPAuthService {
    private string $certPath;
    private string $keyPath;
    private string $passphrase;
    private string $wsaaUrl;
    private string $traPath;

    private const TOKEN_LIFETIME_HOURS = 12;

    public function __construct(array $config) {
        $this->certPath = $config['cert_path'];
        $this->keyPath = $config['key_path'];
        $this->passphrase = $config['passphrase'] ?? '';
        $this->wsaaUrl = $config['wsaa_url'];
        $this->traPath = $config['tra_path'];
    }

    public function getAuthForService(string $service): array {
        $traFile = $this->traPath . "/TRA_{$service}.xml";

        if ($this->isTokenValid($traFile)) {
            return $this->readCredentials($traFile);
        }

        return $this->requestNewToken($service);
    }

    private function isTokenValid(string $traFile): bool {
        if (!file_exists($traFile)) {
            return false;
        }

        $xml = simplexml_load_file($traFile);
        $expiration = strtotime((string) $xml->header->expirationTime);

        return time() < $expiration;
    }

    private function requestNewToken(string $service): array {
        // 1. Crear TRA
        $traXml = $this->createTRA($service);

        // 2. Firmar TRA
        $cms = $this->signTRA($traXml);

        // 3. Llamar WSAA
        $response = $this->callWSAA($cms);

        // 4. Guardar y retornar credenciales
        $this->saveCredentials($service, $response);

        return [
            'token' => (string) $response->credentials->token,
            'sign' => (string) $response->credentials->sign,
        ];
    }

    private function createTRA(string $service): string {
        $uniqueId = time();
        $generationTime = date('c', $uniqueId - 60);
        $expirationTime = date('c', $uniqueId + 60);

        $tra = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><loginTicketRequest version="1.0"/>');
        $tra->addChild('header');
        $tra->header->addChild('source', 'serialNumber=CUIT 30718446976, cn=facturaciondava2025');
        $tra->header->addChild('destination', 'cn=wsaa,o=afip,c=ar,serialNumber=CUIT 33693450239');
        $tra->header->addChild('uniqueId', $uniqueId);
        $tra->header->addChild('generationTime', $generationTime);
        $tra->header->addChild('expirationTime', $expirationTime);
        $tra->addChild('service', $service);

        return $tra->asXML();
    }

    private function signTRA(string $traXml): string {
        $tempInput = tempnam(sys_get_temp_dir(), 'tra_');
        $tempOutput = tempnam(sys_get_temp_dir(), 'cms_');

        file_put_contents($tempInput, $traXml);

        $status = openssl_pkcs7_sign(
            $tempInput,
            $tempOutput,
            "file://{$this->certPath}",
            ["file://{$this->keyPath}", $this->passphrase],
            [],
            !PKCS7_DETACHED
        );

        if (!$status) {
            throw new \RuntimeException('Error signing TRA: ' . openssl_error_string());
        }

        $content = file_get_contents($tempOutput);

        // Remove MIME headers (first 4 lines)
        $lines = explode("\n", $content);
        $cms = implode("\n", array_slice($lines, 4));

        unlink($tempInput);
        unlink($tempOutput);

        return $cms;
    }

    private function callWSAA(string $cms): \SimpleXMLElement {
        $client = new \SoapClient(
            SAH_PLUGIN_DIR . 'config/afip/wsdl/wsaa.wsdl',
            [
                'soap_version' => SOAP_1_2,
                'location' => $this->wsaaUrl,
                'trace' => true,
                'exceptions' => true,
            ]
        );

        $response = $client->loginCms(['in0' => base64_encode($cms)]);

        return simplexml_load_string($response->loginCmsReturn);
    }

    private function readCredentials(string $traFile): array {
        $xml = simplexml_load_file($traFile);

        return [
            'token' => (string) $xml->credentials->token,
            'sign' => (string) $xml->credentials->sign,
        ];
    }

    private function saveCredentials(string $service, \SimpleXMLElement $response): void {
        $traFile = $this->traPath . "/TRA_{$service}.xml";
        $response->asXML($traFile);
    }
}
```

**3.2 src/Services/AFIP/AFIPInvoiceService.php**
```php
<?php
namespace SAH\Services\AFIP;

use SAH\Models\Invoice;
use SAH\Models\Transaction;
use SAH\Core\Logger;

class AFIPInvoiceService {
    private AFIPAuthService $authService;
    private string $wsfeUrl;
    private string $wsctUrl;
    private string $cuit;
    private int $puntoVenta;

    public function __construct(
        AFIPAuthService $authService,
        array $config
    ) {
        $this->authService = $authService;
        $this->wsfeUrl = $config['wsfe_url'];
        $this->wsctUrl = $config['wsct_url'];
        $this->cuit = $config['cuit'];
        $this->puntoVenta = $config['punto_venta'];
    }

    public function emitirFacturaB(Transaction $transaction): Invoice {
        $auth = $this->authService->getAuthForService('wsfe');

        // Obtener ultimo comprobante
        $ultimoComprobante = $this->getUltimoComprobante(Invoice::TYPE_B);
        $nuevoNumero = $ultimoComprobante + 1;

        // Calcular montos
        $calculator = new \SAH\Services\Invoice\InvoiceCalculator();
        $montos = $calculator->calcularDesdeTotal($transaction->amount);

        // Construir request
        $request = $this->buildWSFERequest($transaction, $nuevoNumero, $montos);

        // Llamar AFIP
        $response = $this->callWSFE($auth, $request);

        // Procesar respuesta
        return $this->processWSFEResponse($response, $transaction, $nuevoNumero, $montos);
    }

    public function emitirFacturaT(Transaction $transaction): Invoice {
        $auth = $this->authService->getAuthForService('wsct');

        // Obtener ultimo comprobante
        $ultimoComprobante = $this->getUltimoComprobanteTipoT();
        $nuevoNumero = $ultimoComprobante + 1;

        // Calcular montos (con reintegro)
        $calculator = new \SAH\Services\Invoice\InvoiceCalculator();
        $montos = $calculator->calcularConReintegro($transaction->amount);

        // Construir request WSCT
        $request = $this->buildWSCTRequest($transaction, $nuevoNumero, $montos);

        // Llamar AFIP
        $response = $this->callWSCT($auth, $request);

        // Procesar respuesta
        return $this->processWSCTResponse($response, $transaction, $nuevoNumero, $montos);
    }

    private function getUltimoComprobante(int $tipo): int {
        // Implementar llamada FECompUltimoAutorizado
    }

    private function buildWSFERequest(Transaction $transaction, int $numero, array $montos): array {
        // Construir estructura XML para WSFE
    }

    private function callWSFE(array $auth, array $request): \SimpleXMLElement {
        // Llamada SOAP a WSFE
    }

    private function processWSFEResponse(
        \SimpleXMLElement $response,
        Transaction $transaction,
        int $numero,
        array $montos
    ): Invoice {
        // Parsear respuesta y crear Invoice
    }
}
```

---

### FASE 4: SERVICIOS CLOUDBEDS (2-3 dias)

#### Objetivos
- [ ] Extraer cliente API CloudBeds
- [ ] Implementar OAuth flow correctamente
- [ ] Crear servicio de sincronizacion

#### Archivos a Crear

**4.1 src/Services/CloudBeds/CloudBedsClient.php**
```php
<?php
namespace SAH\Services\CloudBeds;

class CloudBedsClient {
    private string $baseUrl = 'https://hotels.cloudbeds.com/api/v1.2';
    private OAuthService $oauth;

    public function __construct(OAuthService $oauth) {
        $this->oauth = $oauth;
    }

    public function getTransactions(array $params = []): array {
        return $this->request('GET', '/getTransactions', $params);
    }

    public function getGuest(string $guestId): array {
        return $this->request('GET', '/getGuest', ['guestID' => $guestId]);
    }

    public function postDocument(string $reservationId, string $filePath): array {
        return $this->uploadFile('/postReservationDocument', [
            'reservationID' => $reservationId,
            'file' => new \CURLFile($filePath),
        ]);
    }

    private function request(string $method, string $endpoint, array $params = []): array {
        $token = $this->oauth->getAccessToken();

        $ch = curl_init();

        $url = $this->baseUrl . $endpoint;
        if ($method === 'GET' && !empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$token}",
                'Content-Type: application/json',
            ],
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400) {
            throw new CloudBedsException("API Error: {$httpCode}", $httpCode);
        }

        return json_decode($response, true);
    }
}
```

**4.2 src/Services/CloudBeds/OAuthService.php**
```php
<?php
namespace SAH\Services\CloudBeds;

use SAH\Repositories\ConfigRepository;

class OAuthService {
    private ConfigRepository $configRepo;
    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;

    public function getAccessToken(): string {
        $config = $this->configRepo->getActive();

        // Check if token is still valid
        if ($this->isTokenValid($config)) {
            return $config['access_token'];
        }

        // Refresh token
        return $this->refreshToken($config['refresh_token']);
    }

    public function handleCallback(string $code): array {
        $response = $this->exchangeCodeForToken($code);

        $this->configRepo->updateTokens(
            $response['access_token'],
            $response['refresh_token']
        );

        return $response;
    }

    public function getAuthorizationUrl(): string {
        return "https://hotels.cloudbeds.com/api/v1.2/oauth?" . http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'scope' => 'read:reservation write:reservation',
        ]);
    }

    private function refreshToken(string $refreshToken): string {
        // Implementar refresh
    }

    private function exchangeCodeForToken(string $code): array {
        // Implementar exchange
    }
}
```

---

### FASE 5: GENERACION DE PDF (2-3 dias)

#### Objetivos
- [ ] Crear generador PDF modular
- [ ] Separar templates de logica
- [ ] Implementar generador QR

#### Archivos a Crear

**5.1 src/Services/PDF/PDFGenerator.php**
```php
<?php
namespace SAH\Services\PDF;

use Dompdf\Dompdf;
use Dompdf\Options;
use SAH\Models\Invoice;
use SAH\Storage\FileStorage;

class PDFGenerator {
    private Dompdf $dompdf;
    private FileStorage $storage;

    public function __construct(FileStorage $storage) {
        $this->storage = $storage;

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);

        $this->dompdf = new Dompdf($options);
    }

    /**
     * Genera el PDF de una factura y lo guarda en WordPress uploads
     *
     * @param Invoice $invoice
     * @return array ['path' => ruta absoluta, 'url' => URL publica, 'relative' => ruta relativa para BD]
     */
    public function generateInvoice(Invoice $invoice): array {
        $template = $invoice->isFacturaB()
            ? new InvoiceBTemplate($invoice)
            : new InvoiceTTemplate($invoice);

        $html = $template->render();

        $this->dompdf->loadHtml($html);
        $this->dompdf->setPaper('A4', 'portrait');
        $this->dompdf->render();

        $filename = $this->generateFilename($invoice);
        $invoiceType = $invoice->isFacturaB() ? 'factura-b' : 'factura-t';
        $pdfContent = $this->dompdf->output();

        // Guardar usando FileStorage (en wp-content/uploads/sah-invoices/)
        $result = $this->storage->savePdf($pdfContent, $filename, $invoiceType);

        return $result;
    }

    private function generateFilename(Invoice $invoice): string {
        $type = $invoice->isFacturaB() ? 'B' : 'T';
        $numero = $invoice->getNumeroFormateado();
        $date = date('Y-m-d_H-i-s');

        return "Factura_{$type}_{$numero}_{$date}.pdf";
    }
}
```

**5.2 src/Services/PDF/InvoiceBTemplate.php**
```php
<?php
namespace SAH\Services\PDF;

use SAH\Models\Invoice;
use SAH\Services\QR\QRGenerator;

class InvoiceBTemplate {
    private Invoice $invoice;
    private QRGenerator $qrGenerator;

    public function __construct(Invoice $invoice) {
        $this->invoice = $invoice;
        $this->qrGenerator = new QRGenerator();
    }

    public function render(): string {
        $qrData = $this->buildQRData();
        $qrImage = $this->qrGenerator->generate($qrData);

        ob_start();
        include SAH_PLUGIN_DIR . 'templates/invoice/factura-b.php';
        return ob_get_clean();
    }

    private function buildQRData(): string {
        // Construir datos QR segun especificacion AFIP
        $data = [
            'ver' => 1,
            'fecha' => $this->invoice->fecha->format('Y-m-d'),
            'cuit' => SAH_CUIT,
            'ptoVta' => $this->invoice->puntoVenta,
            'tipoCmp' => $this->invoice->type,
            'nroCmp' => $this->invoice->numero,
            'importe' => $this->invoice->importeTotal,
            'moneda' => 'PES',
            'ctz' => 1,
            'tipoDocRec' => $this->invoice->guest->getAFIPDocType(),
            'nroDocRec' => $this->invoice->guest->documentNumber,
            'tipoCodAut' => 'E',
            'codAut' => $this->invoice->cae,
        ];

        return base64_encode(json_encode($data));
    }
}
```

---

### FASE 6: ADMIN Y AJAX (3-4 dias)

#### Objetivos
- [ ] Crear sistema de menus limpio
- [ ] Implementar router AJAX
- [ ] Separar handlers por responsabilidad

#### Archivos a Crear

**6.1 src/Admin/AdminMenu.php**
```php
<?php
namespace SAH\Admin;

class AdminMenu {
    public function register(): void {
        add_menu_page(
            'Hotel AFIP',
            'Hotel AFIP',
            'manage_options',
            'sah-dashboard',
            [$this, 'renderDashboard'],
            'dashicons-building',
            30
        );

        add_submenu_page(
            'sah-dashboard',
            'Transacciones',
            'Transacciones',
            'manage_options',
            'sah-transactions',
            [new Pages\TransactionsPage(), 'render']
        );

        add_submenu_page(
            'sah-dashboard',
            'Configuracion',
            'Configuracion',
            'manage_options',
            'sah-config',
            [new Pages\ConfigPage(), 'render']
        );

        add_submenu_page(
            'sah-dashboard',
            'Logs',
            'Logs',
            'manage_options',
            'sah-logs',
            [new Pages\LogsPage(), 'render']
        );
    }

    public function renderDashboard(): void {
        // Dashboard principal con estadisticas
    }
}
```

**6.2 src/Admin/Ajax/AjaxHandler.php**
```php
<?php
namespace SAH\Admin\Ajax;

class AjaxHandler {
    private array $handlers = [];

    public function __construct() {
        $this->handlers = [
            'import_transactions' => new TransactionAjax(),
            'generate_invoice_b' => new InvoiceAjax(),
            'generate_invoice_t' => new InvoiceAjax(),
            'update_config' => new ConfigAjax(),
            'refresh_token' => new ConfigAjax(),
        ];
    }

    public function handle(): void {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'sah_ajax_nonce')) {
            wp_send_json_error(['message' => 'Invalid security token']);
        }

        // Check capability
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $action = sanitize_text_field($_POST['sah_action'] ?? '');

        if (!isset($this->handlers[$action])) {
            wp_send_json_error(['message' => 'Unknown action']);
        }

        try {
            $handler = $this->handlers[$action];
            $method = $this->getMethodName($action);
            $result = $handler->$method($_POST);

            wp_send_json_success($result);
        } catch (\Exception $e) {
            Logger::error($action, $e->getMessage());
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    private function getMethodName(string $action): string {
        // import_transactions -> importTransactions
        return lcfirst(str_replace('_', '', ucwords($action, '_')));
    }
}
```

**6.3 src/Admin/Ajax/InvoiceAjax.php**
```php
<?php
namespace SAH\Admin\Ajax;

use SAH\Services\AFIP\AFIPInvoiceService;
use SAH\Services\PDF\PDFGenerator;
use SAH\Storage\FileStorage;
use SAH\Repositories\TransactionRepository;

class InvoiceAjax {
    private AFIPInvoiceService $invoiceService;
    private PDFGenerator $pdfGenerator;
    private TransactionRepository $transactionRepo;
    private FileStorage $storage;

    public function __construct() {
        $this->storage = new FileStorage();
        $this->pdfGenerator = new PDFGenerator($this->storage);
        $this->transactionRepo = new TransactionRepository();
        // $this->invoiceService se inyecta via container o se instancia aqui
    }

    public function generateInvoiceB(array $data): array {
        $transactionId = sanitize_text_field($data['transaction_id']);

        $transaction = $this->transactionRepo->findByTransactionId($transactionId);

        if (!$transaction) {
            throw new \InvalidArgumentException('Transaction not found');
        }

        if ($transaction->hasInvoice()) {
            return $this->getExistingInvoice($transaction);
        }

        // Generar factura en AFIP
        $invoice = $this->invoiceService->emitirFacturaB($transaction);

        // Generar PDF (se guarda automaticamente en wp-content/uploads/sah-invoices/)
        $pdfResult = $this->pdfGenerator->generateInvoice($invoice);

        // Actualizar BD con la ruta relativa
        // Formato: sah-invoices/2024/01/pdf/factura-b/archivo.pdf
        $this->transactionRepo->updateInvoiceUrl(
            $transaction->id,
            $pdfResult['relative']
        );

        return [
            'success' => true,
            'invoice_number' => $invoice->getNumeroFormateado(),
            'cae' => $invoice->cae,
            'pdf_url' => $pdfResult['url'],
        ];
    }

    public function generateInvoiceT(array $data): array {
        $transactionId = sanitize_text_field($data['transaction_id']);

        $transaction = $this->transactionRepo->findByTransactionId($transactionId);

        if (!$transaction) {
            throw new \InvalidArgumentException('Transaction not found');
        }

        if ($transaction->hasInvoice()) {
            return $this->getExistingInvoice($transaction);
        }

        // Generar factura T en AFIP
        $invoice = $this->invoiceService->emitirFacturaT($transaction);

        // Generar PDF
        $pdfResult = $this->pdfGenerator->generateInvoice($invoice);

        // Actualizar BD
        $this->transactionRepo->updateInvoiceUrl(
            $transaction->id,
            $pdfResult['relative']
        );

        return [
            'success' => true,
            'invoice_number' => $invoice->getNumeroFormateado(),
            'cae' => $invoice->cae,
            'reintegro' => $invoice->importeReintegro,
            'pdf_url' => $pdfResult['url'],
        ];
    }

    /**
     * Obtiene la URL de una factura existente
     */
    private function getExistingInvoice($transaction): array {
        $invoiceUrl = $transaction->invoiceUrl;

        // Detectar formato de URL
        if (strpos($invoiceUrl, 'sah-invoices/') === 0) {
            // Formato nuevo: sah-invoices/2024/01/pdf/factura-b/archivo.pdf
            $url = $this->storage->getUrl($invoiceUrl);
        } elseif (strpos($invoiceUrl, 'invoicespdf') !== false || strpos($invoiceUrl, 'invoicespdft') !== false) {
            // Formato intermedio: invoicespdf/archivo.pdf o invoicespdft/archivo.pdf
            $url = plugin_dir_url(dirname(dirname(dirname(__FILE__)))) . 'php/' . $invoiceUrl;
        } else {
            // Formato legacy: solo archivo.pdf
            $url = plugin_dir_url(dirname(dirname(dirname(__FILE__)))) . 'php/invoicespdf/' . $invoiceUrl;
        }

        return [
            'success' => true,
            'already_exists' => true,
            'pdf_url' => $url,
        ];
    }
}
```

---

### FASE 7: JAVASCRIPT MODERNO (2-3 dias)

#### Objetivos
- [ ] Modularizar JavaScript
- [ ] Eliminar dependencia excesiva de jQuery
- [ ] Implementar manejo de errores UI

#### Archivos a Crear

**7.1 assets/js/admin/transactions.js**
```javascript
/**
 * Transactions Management Module
 */
const TransactionsManager = (function() {
    'use strict';

    const config = {
        ajaxUrl: window.sahConfig?.ajaxUrl || '/wp-admin/admin-ajax.php',
        nonce: window.sahConfig?.nonce || '',
    };

    const selectors = {
        importBtn: '#sah-import-transactions',
        generateBBtn: '.sah-generate-invoice-b',
        generateTBtn: '.sah-generate-invoice-t',
        transactionTable: '#sah-transactions-table',
        loadingSpinner: '.sah-loading',
        notification: '#sah-notification',
    };

    /**
     * Initialize module
     */
    function init() {
        bindEvents();
    }

    /**
     * Bind event listeners
     */
    function bindEvents() {
        document.querySelector(selectors.importBtn)
            ?.addEventListener('click', handleImport);

        document.querySelectorAll(selectors.generateBBtn)
            .forEach(btn => btn.addEventListener('click', handleGenerateInvoiceB));

        document.querySelectorAll(selectors.generateTBtn)
            .forEach(btn => btn.addEventListener('click', handleGenerateInvoiceT));
    }

    /**
     * Handle transaction import
     */
    async function handleImport(event) {
        event.preventDefault();

        const dateFrom = document.querySelector('#date-from').value;
        const dateTo = document.querySelector('#date-to').value;

        if (!dateFrom || !dateTo) {
            showNotification('Por favor seleccione un rango de fechas', 'error');
            return;
        }

        showLoading(true);

        try {
            const response = await apiRequest('import_transactions', {
                date_from: dateFrom,
                date_to: dateTo,
            });

            showNotification(`${response.imported} transacciones importadas`, 'success');
            refreshTable();
        } catch (error) {
            showNotification(error.message, 'error');
        } finally {
            showLoading(false);
        }
    }

    /**
     * Handle invoice B generation
     */
    async function handleGenerateInvoiceB(event) {
        event.preventDefault();

        const transactionId = event.target.dataset.transactionId;
        const row = event.target.closest('tr');

        if (!confirm('¿Generar Factura B para esta transaccion?')) {
            return;
        }

        setRowLoading(row, true);

        try {
            const response = await apiRequest('generate_invoice_b', {
                transaction_id: transactionId,
            });

            showNotification(`Factura ${response.invoice_number} generada`, 'success');
            updateRowWithInvoice(row, response);
        } catch (error) {
            showNotification(error.message, 'error');
        } finally {
            setRowLoading(row, false);
        }
    }

    /**
     * Make API request
     */
    async function apiRequest(action, data = {}) {
        const formData = new FormData();
        formData.append('action', 'sah_action');
        formData.append('sah_action', action);
        formData.append('nonce', config.nonce);

        Object.entries(data).forEach(([key, value]) => {
            formData.append(key, value);
        });

        const response = await fetch(config.ajaxUrl, {
            method: 'POST',
            body: formData,
        });

        const json = await response.json();

        if (!json.success) {
            throw new Error(json.data?.message || 'Error desconocido');
        }

        return json.data;
    }

    /**
     * Show notification
     */
    function showNotification(message, type = 'info') {
        const notification = document.querySelector(selectors.notification);
        notification.textContent = message;
        notification.className = `sah-notification sah-notification--${type}`;
        notification.hidden = false;

        setTimeout(() => {
            notification.hidden = true;
        }, 5000);
    }

    /**
     * Show/hide loading state
     */
    function showLoading(show) {
        document.querySelector(selectors.loadingSpinner).hidden = !show;
    }

    // Public API
    return {
        init,
    };
})();

// Initialize when DOM ready
document.addEventListener('DOMContentLoaded', TransactionsManager.init);
```

---

### FASE 8: TESTING (3-4 dias)

#### Objetivos
- [ ] Configurar PHPUnit
- [ ] Escribir tests unitarios
- [ ] Escribir tests de integracion

#### Archivos a Crear

**8.1 phpunit.xml**
```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit
    bootstrap="tests/bootstrap.php"
    colors="true"
    verbose="true"
    stopOnFailure="false"
>
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Integration">
            <directory>tests/Integration</directory>
        </testsuite>
    </testsuites>

    <coverage>
        <include>
            <directory suffix=".php">src</directory>
        </include>
    </coverage>
</phpunit>
```

**8.2 tests/Unit/Services/InvoiceCalculatorTest.php**
```php
<?php
namespace SAH\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use SAH\Services\Invoice\InvoiceCalculator;

class InvoiceCalculatorTest extends TestCase {
    private InvoiceCalculator $calculator;

    protected function setUp(): void {
        $this->calculator = new InvoiceCalculator();
    }

    public function testCalcularDesdeTotal(): void {
        $result = $this->calculator->calcularDesdeTotal(1210.00);

        $this->assertEquals(1000.00, $result['neto']);
        $this->assertEquals(210.00, $result['iva']);
        $this->assertEquals(1210.00, $result['total']);
    }

    public function testCalcularConReintegro(): void {
        $result = $this->calculator->calcularConReintegro(1210.00);

        $this->assertEquals(1000.00, $result['gravado']);
        $this->assertEquals(210.00, $result['iva']);
        $this->assertEquals(-210.00, $result['reintegro']);
        $this->assertEquals(1000.00, $result['total']);
    }

    /**
     * @dataProvider montosProvider
     */
    public function testCalculosMontosVariados(float $total, float $expectedNeto): void {
        $result = $this->calculator->calcularDesdeTotal($total);

        $this->assertEqualsWithDelta($expectedNeto, $result['neto'], 0.01);
    }

    public function montosProvider(): array {
        return [
            [121.00, 100.00],
            [242.00, 200.00],
            [12100.00, 10000.00],
            [1.21, 1.00],
        ];
    }
}
```

**8.3 tests/Unit/Helpers/CountryMapperTest.php**
```php
<?php
namespace SAH\Tests\Unit\Helpers;

use PHPUnit\Framework\TestCase;
use SAH\Helpers\CountryMapper;

class CountryMapperTest extends TestCase {
    public function testMapIsoToAfip(): void {
        $this->assertEquals(200, CountryMapper::isoToAfip('AR'));
        $this->assertEquals(212, CountryMapper::isoToAfip('US'));
        $this->assertEquals(105, CountryMapper::isoToAfip('BR'));
        $this->assertEquals(113, CountryMapper::isoToAfip('CL'));
    }

    public function testUnknownCountryReturnsDefault(): void {
        $this->assertEquals(999, CountryMapper::isoToAfip('XX'));
    }

    public function testCaseInsensitive(): void {
        $this->assertEquals(200, CountryMapper::isoToAfip('ar'));
        $this->assertEquals(200, CountryMapper::isoToAfip('Ar'));
    }
}
```

**8.4 tests/Unit/Storage/FileStorageTest.php**
```php
<?php
namespace SAH\Tests\Unit\Storage;

use PHPUnit\Framework\TestCase;
use SAH\Storage\FileStorage;

class FileStorageTest extends TestCase {
    private FileStorage $storage;
    private string $testDir;

    protected function setUp(): void {
        // Crear directorio temporal para tests
        $this->testDir = sys_get_temp_dir() . '/sah-test-' . uniqid();
        mkdir($this->testDir, 0755, true);

        // Mock de wp_upload_dir() - en tests reales usariamos WP_Mock
        $this->storage = $this->createMock(FileStorage::class);
    }

    protected function tearDown(): void {
        // Limpiar directorio temporal
        $this->deleteDirectory($this->testDir);
    }

    public function testBuildPathIncludesDateStructure(): void {
        $year = date('Y');
        $month = date('m');

        // Usar reflexion para testear metodo privado
        $reflection = new \ReflectionClass(FileStorage::class);
        $method = $reflection->getMethod('buildPath');
        $method->setAccessible(true);

        $storage = new FileStorage();
        $result = $method->invoke($storage, 'pdf', 'factura-b', 'test.pdf');

        $this->assertStringContainsString($year, $result);
        $this->assertStringContainsString($month, $result);
        $this->assertStringContainsString('pdf', $result);
        $this->assertStringContainsString('factura-b', $result);
        $this->assertStringContainsString('test.pdf', $result);
    }

    public function testSavePdfCreatesFile(): void {
        $content = '%PDF-1.4 test content';
        $filename = 'test-invoice.pdf';

        $result = $this->storage->savePdf($content, $filename, 'factura-b');

        $this->assertArrayHasKey('path', $result);
        $this->assertArrayHasKey('url', $result);
        $this->assertArrayHasKey('relative', $result);
    }

    public function testExistsReturnsTrueForExistingFile(): void {
        // Crear archivo de prueba
        $testFile = $this->testDir . '/test.pdf';
        file_put_contents($testFile, 'test');

        // Este test necesita mock apropiado de wp_upload_dir
        $this->assertTrue(file_exists($testFile));
    }

    public function testExistsReturnsFalseForNonExistingFile(): void {
        $this->assertFalse(file_exists($this->testDir . '/nonexistent.pdf'));
    }

    public function testGetUrlHandlesBothFormats(): void {
        $storage = new FileStorage();

        // Formato con prefijo
        $url1 = $storage->getUrl('sah-invoices/2024/01/pdf/factura-b/test.pdf');

        // Formato sin prefijo
        $url2 = $storage->getUrl('2024/01/pdf/factura-b/test.pdf');

        // Ambos deben generar URLs validas
        $this->assertStringContainsString('sah-invoices', $url1);
        $this->assertStringContainsString('sah-invoices', $url2);
    }

    private function deleteDirectory(string $dir): void {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = "$dir/$file";
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
```

---

### FASE 9: MIGRACION Y DEPLOY (2-3 dias)

#### Objetivos
- [ ] Crear script de migracion
- [ ] Migrar datos existentes
- [ ] Testing en produccion

#### Tareas

**9.1 Crear Migracion de Datos a WordPress Uploads**
```php
<?php
// migrations/migrate-invoice-urls.php

use SAH\Storage\FileStorage;

/**
 * Migra todos los archivos PDF y XML del plugin a WordPress uploads
 * y actualiza las URLs en la base de datos
 *
 * Estructura origen (plugin):
 *   php/invoicespdf/archivo.pdf
 *   php/invoicespdft/archivo.pdf
 *   php/invoicesxml/archivo.xml
 *   php/invoicesxmlt/archivo.xml
 *
 * Estructura destino (WordPress uploads):
 *   wp-content/uploads/sah-invoices/legacy/pdf/factura-b/archivo.pdf
 *   wp-content/uploads/sah-invoices/legacy/pdf/factura-t/archivo.pdf
 *   wp-content/uploads/sah-invoices/legacy/xml/factura-b/archivo.xml
 *   wp-content/uploads/sah-invoices/legacy/xml/factura-t/archivo.xml
 */
function sah_migrate_to_wordpress_uploads() {
    global $wpdb;
    $table = $wpdb->prefix . 'hotels_transactions';
    $storage = new FileStorage();

    // Rutas antiguas del plugin
    $plugin_path = plugin_dir_path(dirname(__FILE__));
    $old_paths = [
        'invoicespdf' => ['type' => 'pdf', 'invoice_type' => 'factura-b'],
        'invoicespdft' => ['type' => 'pdf', 'invoice_type' => 'factura-t'],
        'invoicesxml' => ['type' => 'xml', 'invoice_type' => 'factura-b'],
        'invoicesxmlt' => ['type' => 'xml', 'invoice_type' => 'factura-t'],
    ];

    $migrated = 0;
    $errors = [];

    // Obtener todos los registros con invoiceUrl
    $records = $wpdb->get_results(
        "SELECT id, invoiceUrl FROM {$table}
         WHERE invoiceUrl IS NOT NULL
         AND invoiceUrl != ''
         AND invoiceUrl NOT LIKE 'sah-invoices/%'"
    );

    foreach ($records as $record) {
        $invoiceUrl = $record->invoiceUrl;
        $filename = basename($invoiceUrl);

        // Determinar origen basado en el formato de URL
        $oldFolder = null;
        $oldFullPath = null;

        if (strpos($invoiceUrl, '/') !== false) {
            // Formato intermedio: invoicespdf/archivo.pdf o invoicespdft/archivo.pdf
            $parts = explode('/', $invoiceUrl);
            $oldFolder = $parts[0];
            $oldFullPath = $plugin_path . 'php/' . $invoiceUrl;
        } else {
            // Formato legacy: solo archivo.pdf - buscar en ambas carpetas
            foreach (['invoicespdf', 'invoicespdft'] as $folder) {
                $testPath = $plugin_path . 'php/' . $folder . '/' . $filename;
                if (file_exists($testPath)) {
                    $oldFolder = $folder;
                    $oldFullPath = $testPath;
                    break;
                }
            }
        }

        if (!$oldFolder || !$oldFullPath || !file_exists($oldFullPath)) {
            $errors[] = "Archivo no encontrado para ID {$record->id}: {$invoiceUrl}";
            continue;
        }

        // Migrar archivo
        $config = $old_paths[$oldFolder];
        $result = $storage->migrateFromLegacy($oldFullPath, $config['invoice_type'], $config['type']);

        if ($result === false) {
            $errors[] = "Error migrando archivo para ID {$record->id}: {$oldFullPath}";
            continue;
        }

        // Actualizar BD con la nueva ruta
        $wpdb->update(
            $table,
            ['invoiceUrl' => $result['relative']],
            ['id' => $record->id]
        );

        $migrated++;
    }

    return [
        'migrated' => $migrated,
        'errors' => $errors,
        'total' => count($records),
    ];
}

/**
 * Script para ejecutar desde WP-CLI o admin
 */
function sah_run_migration() {
    // Verificar permisos
    if (!current_user_can('manage_options')) {
        wp_die('No autorizado');
    }

    $result = sah_migrate_to_wordpress_uploads();

    echo "<h2>Migracion Completada</h2>";
    echo "<p>Archivos migrados: {$result['migrated']} de {$result['total']}</p>";

    if (!empty($result['errors'])) {
        echo "<h3>Errores:</h3>";
        echo "<ul>";
        foreach ($result['errors'] as $error) {
            echo "<li>{$error}</li>";
        }
        echo "</ul>";
    }

    echo "<h3>Siguientes Pasos:</h3>";
    echo "<ol>";
    echo "<li>Verificar que los archivos se copiaron correctamente a wp-content/uploads/sah-invoices/</li>";
    echo "<li>Probar descarga de algunas facturas desde el admin</li>";
    echo "<li>Si todo funciona, eliminar las carpetas antiguas del plugin:</li>";
    echo "<ul>";
    echo "<li>php/invoicespdf/</li>";
    echo "<li>php/invoicespdft/</li>";
    echo "<li>php/invoicesxml/</li>";
    echo "<li>php/invoicesxmlt/</li>";
    echo "</ul>";
    echo "</ol>";
}
```

**9.2 Checklist de Deploy**
```markdown
## Pre-Deploy
- [ ] Backup completo de base de datos
- [ ] Backup de archivos del plugin (especialmente php/afip/)
- [ ] Backup de carpetas de PDFs/XMLs (invoicespdf, invoicespdft, etc.)
- [ ] Verificar que todos los tests pasan
- [ ] Verificar certificados AFIP vigentes
- [ ] Verificar tokens CloudBeds validos

## Deploy
- [ ] Activar modo mantenimiento
- [ ] Subir archivos nuevos
- [ ] Ejecutar composer install --no-dev
- [ ] Crear directorio wp-content/uploads/sah-invoices/ con permisos 755
- [ ] Ejecutar migracion de archivos (sah_migrate_to_wordpress_uploads)
- [ ] Verificar que los archivos se copiaron correctamente
- [ ] Limpiar cache
- [ ] Desactivar modo mantenimiento

## Post-Deploy
- [ ] Verificar pagina de transacciones carga
- [ ] Probar importacion de transacciones
- [ ] Probar descarga de factura existente (migrada)
- [ ] Probar generacion de Factura B nueva
- [ ] Probar generacion de Factura T nueva
- [ ] Verificar que PDFs nuevos se guardan en wp-content/uploads/sah-invoices/
- [ ] Verificar logs no tienen errores
- [ ] Monitorear por 24 horas

## Post-Verificacion (despues de 1 semana)
- [ ] Si todo funciona, eliminar carpetas antiguas:
  - [ ] php/invoicespdf/
  - [ ] php/invoicespdft/
  - [ ] php/invoicesxml/
  - [ ] php/invoicesxmlt/
  - [ ] php/consultas/pdfs/
  - [ ] php/consultas/qrcodes/
```

---

## 5. CRONOGRAMA ESTIMADO

| Fase | Duracion | Dependencias |
|------|----------|--------------|
| Fase 0: Preparacion | 1-2 dias | Ninguna |
| Fase 1: Core y Modelos | 2-3 dias | Fase 0 |
| Fase 2: Repositorios | 2-3 dias | Fase 1 |
| Fase 3: Servicios AFIP | 4-5 dias | Fase 2 |
| Fase 4: Servicios CloudBeds | 2-3 dias | Fase 2 |
| Fase 5: Generacion PDF | 2-3 dias | Fase 3 |
| Fase 6: Admin y AJAX | 3-4 dias | Fase 3, 4, 5 |
| Fase 7: JavaScript | 2-3 dias | Fase 6 |
| Fase 8: Testing | 3-4 dias | Todas |
| Fase 9: Migracion | 2-3 dias | Fase 8 |

**Total estimado: 24-33 dias de desarrollo**

---

## 6. INSTRUCCIONES PARA CLAUDE

### Cuando empieces la refactorizacion:

1. **Lee la seccion de ADVERTENCIAS CRITICAS** al inicio del documento
2. **Lee este documento completo** antes de escribir codigo
3. **Sigue las fases en orden** - cada fase depende de la anterior
4. **Crea tests** para cada servicio nuevo
5. **No borres codigo legacy** hasta que el nuevo funcione
6. **Mantén compatibilidad** con la base de datos existente
7. **Documenta** cada clase con PHPDoc
8. **NUNCA elimines** archivos de `php/afip/` (certificados, WSDL)
9. **Usa WordPress uploads** para almacenar PDFs/XMLs (`wp-content/uploads/sah-invoices/`)
10. **Ejecuta la migracion** antes de eliminar las carpetas antiguas del plugin

### Comandos utiles:

```bash
# Verificar sintaxis PHP
php -l src/Services/AFIP/AFIPAuthService.php

# Ejecutar tests
./vendor/bin/phpunit

# Verificar estandares
./vendor/bin/phpcs --standard=WordPress src/

# Analisis estatico
./vendor/bin/phpstan analyse src/ --level=6

# Actualizar autoload
composer dump-autoload
```

### Preguntas frecuentes:

**Q: Debo mantener el archivo main_global.php?**
A: Si, temporalmente. Usa el patron Strangler Fig: crea los nuevos servicios y gradualmente mueve la logica, manteniendo el archivo viejo funcional hasta completar la migracion.

**Q: Como manejo los certificados AFIP?**
A: Los certificados actuales estan en `php/afip/cert/`. Durante la refactorizacion:
1. COPIA (no muevas) los archivos a `config/afip/cert/`
2. Verifica que funcionan con la nueva estructura
3. Solo elimina los originales cuando TODO funcione
4. NUNCA subas certificados al repositorio - usa `.gitignore`

**Q: Puedo eliminar los archivos WSDL de php/afip/?**
A: NO. Los archivos `.wsdl` (wsaa.wsdl, wsfe.wsdl, etc.) son definiciones de los servicios SOAP de AFIP y son necesarios para las llamadas. Copialos a la nueva ubicacion pero no los elimines hasta verificar que todo funciona.

**Q: Que hago con las carpetas invoicespdf, invoicesxml del plugin actual?**
A: Estas carpetas seran **ELIMINADAS** del plugin. El nuevo sistema usa WordPress Uploads:
1. Migra los archivos existentes a `wp-content/uploads/sah-invoices/legacy/`
2. Actualiza los registros en BD con las nuevas rutas
3. Elimina las carpetas del plugin
4. Los nuevos archivos se guardaran en `wp-content/uploads/sah-invoices/{year}/{month}/`

**Q: Por que usar WordPress Uploads en lugar del plugin?**
A: Mejores practicas de WordPress:
- Los archivos persisten si se actualiza/reinstala el plugin
- WordPress maneja las URLs automaticamente
- Compatible con backups estandar de WordPress
- Soporte nativo para CDN
- No se mezclan archivos generados con codigo fuente

**Q: Puedo cambiar la estructura de la base de datos?**
A: NO. Mantén compatibilidad con las tablas existentes. Si necesitas nuevos campos, usa migraciones.

**Q: Que pasa si elimino accidentalmente un certificado?**
A: Sin el certificado, el plugin NO podra autenticarse con AFIP y NO podra emitir facturas. Tendrias que solicitar un nuevo certificado a AFIP (proceso que puede tomar dias). SIEMPRE ten backup de los certificados fuera del repositorio.

---

## 7. REFERENCIAS

### Documentacion AFIP
- WSAA: https://www.afip.gob.ar/ws/WSAA/
- WSFE: https://www.afip.gob.ar/fe/documentos/manual_desarrollador_COMPG_v2_11.pdf
- WSCT: https://www.afip.gob.ar/ws/documentacion/wsct.asp

### Documentacion CloudBeds
- API: https://hotels.cloudbeds.com/api/docs

### Documentacion del Proyecto
- DOCUMENTACION_SISTEMA.md - Documentacion tecnica actual
- DOCUMENTACION_TECNICA_AFIP.md - Especificaciones AFIP detalladas

---

*Documento creado: 22 de Enero de 2026*
*Ultima actualizacion: 22 de Enero de 2026 - Agregado sistema de almacenamiento en WordPress uploads*
*Para uso en sesiones futuras de Claude Code*
*Proyecto: Plugin AFIP Hotel Penthouse 1004*
