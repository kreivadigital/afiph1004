# Documentacion Tecnica - Plugin Services API Hotel

## Indice

1. [Introduccion](#1-introduccion)
2. [Arquitectura General](#2-arquitectura-general)
3. [Estructura de Archivos](#3-estructura-de-archivos)
4. [Base de Datos](#4-base-de-datos)
5. [AFIP - Sistema de Autenticacion (WSAA)](#5-afip---sistema-de-autenticacion-wsaa)
6. [AFIP - Facturacion Tipo B (WSFE)](#6-afip---facturacion-tipo-b-wsfe)
7. [AFIP - Facturacion Tipo T (WSCT)](#7-afip---facturacion-tipo-t-wsct)
8. [Ejemplos de Requests y Responses](#8-ejemplos-de-requests-y-responses)
9. [Codigos de Error AFIP](#9-codigos-de-error-afip)
10. [Integracion con CloudBeds](#10-integracion-con-cloudbeds)
11. [Sistema de Logging](#11-sistema-de-logging)
12. [Referencias y Recursos](#12-referencias-y-recursos)

---

## 1. Introduccion

### 1.1 Proposito del Plugin

**Services API Hotel** es un plugin de WordPress diseñado para:

- Integrar con la API de **CloudBeds** para obtener transacciones de huespedes
- Emitir **facturas electronicas** a traves de los Web Services de **AFIP**
- Soportar **Factura Tipo B** (WSFE) para clientes argentinos
- Soportar **Factura Tipo T** (WSCT) para turistas extranjeros con reintegro de IVA

### 1.2 Version Actual

- **Version**: 1.2.1
- **Autor**: Osward Pacheco
- **Servicios AFIP soportados**: WSAA, WSFE, WSCT

### 1.3 Requisitos

- WordPress 5.0+
- PHP 7.4+ con extensiones: `openssl`, `soap`, `curl`
- Certificado digital AFIP vigente
- Cuenta activa en CloudBeds

---

## 2. Arquitectura General

### 2.1 Diagrama de Flujo General

```
+----------------+     +------------------+     +------------------+
|   CloudBeds    |     |  WordPress/PHP   |     |      AFIP        |
|   (PMS Hotel)  |     |    (Plugin)      |     | (Web Services)   |
+-------+--------+     +--------+---------+     +--------+---------+
        |                       |                        |
        | 1. OAuth 2.0         |                        |
        |<--------------------->|                        |
        |                       |                        |
        | 2. GET Transacciones  |                        |
        |<----------------------|                        |
        |                       |                        |
        |                       | 3. Autenticar (WSAA)   |
        |                       |----------------------->|
        |                       |<-----------------------|
        |                       |   Token + Sign         |
        |                       |                        |
        |                       | 4. Solicitar CAE       |
        |                       |   (WSFE o WSCT)        |
        |                       |----------------------->|
        |                       |<-----------------------|
        |                       |   CAE + Nro Factura    |
        |                       |                        |
        | 5. POST PDF Factura   |                        |
        |<----------------------|                        |
        |                       |                        |
+-------v--------+     +--------v---------+     +--------v---------+
|   Reservacion  |     |    Base Datos    |     |  Comprobantes    |
|   Actualizada  |     |   (WordPress)    |     |   Autorizados    |
+----------------+     +------------------+     +------------------+
```

### 2.2 Componentes Principales

| Componente | Descripcion |
|------------|-------------|
| `init.php` | Punto de entrada del plugin, registra hooks y carga dependencias |
| `main_global.php` | Funciones principales: autenticacion AFIP, facturacion, PDF |
| `afip_config.php` | Configuracion de ambientes AFIP (prod/test) |
| `class-logger.php` | Sistema de logging (BD + archivos) |
| `transactions-list.php` | UI para listar y gestionar transacciones |

---

## 3. Estructura de Archivos

```
afiph1004/
|
+-- init.php                    # Archivo principal del plugin
+-- uninstall.php               # Script de desinstalacion
|
+-- includes/
|   +-- class-activator.php     # Activacion del plugin
|   +-- class-deactivator.php   # Desactivacion del plugin
|   +-- class-logger.php        # Sistema de logging
|
+-- php/
|   +-- config/
|   |   +-- afip_config.php     # Configuracion AFIP (URLs, certificados)
|   |
|   +-- consultas/
|   |   +-- main_global.php     # Funciones principales de facturacion
|   |   +-- barcode.php         # Generacion de codigo de barras
|   |   +-- mapeo_iso_afip_corregido.php  # Mapeo paises ISO->AFIP
|   |
|   +-- config_init/
|   |   +-- config-list.php     # Listado de configuraciones
|   |   +-- config-create.php   # Crear configuracion
|   |   +-- config-update.php   # Actualizar configuracion
|   |   +-- transactions-list.php      # Listado de transacciones
|   |   +-- transactions-list_dev.php  # Version desarrollo
|   |   +-- logs-list.php       # Visor de logs
|   |
|   +-- afip/
|   |   +-- cert/               # Certificados PRODUCCION
|   |   |   +-- facturacion2025.pem
|   |   |   +-- MiClavePrivada.key
|   |   |   +-- certificado.crt
|   |   |
|   |   +-- cert_preprod/       # Certificados HOMOLOGACION
|   |   +-- wsaa.wsdl           # WSDL autenticacion
|   |   +-- wsfe.wsdl           # WSDL facturacion tipo B
|   |   +-- TRA_WSFE.xml        # Ticket Request Auth (Tipo B)
|   |   +-- TRA_WSCT.xml        # Ticket Request Auth (Tipo T)
|   |   +-- response-loginCms.xml  # Token/Sign vigentes
|   |
|   +-- invoicesxml/            # XMLs Factura Tipo B
|   +-- invoicesxmlt/           # XMLs Factura Tipo T
|   +-- invoicespdf/            # PDFs generados
|
+-- js/
|   +-- main.js                 # JavaScript principal (AJAX handlers)
|
+-- css/
|   +-- style.css               # Estilos del plugin
|
+-- log/
|   +-- debug_YYYY-MM-DD.log    # Logs diarios
|
+-- vendor/                     # Dependencias (Dompdf, QRCode, etc.)
```

---

## 4. Base de Datos

### 4.1 Tabla: wp_hotels_config

Almacena la configuracion de conexion a CloudBeds y AFIP.

```sql
CREATE TABLE wp_hotels_config (
    id              INT(10) AUTO_INCREMENT PRIMARY KEY,
    api_endpoint    VARCHAR(256) NOT NULL,      -- URL base CloudBeds
    version         VARCHAR(256) NOT NULL,      -- Version API
    client_id       VARCHAR(256) NOT NULL,      -- OAuth Client ID
    client_secret   VARCHAR(256) NOT NULL,      -- OAuth Client Secret
    redirect_url    VARCHAR(256) NOT NULL,      -- OAuth Callback URL
    code_auth       VARCHAR(256) NULL,          -- Codigo autorizacion temporal
    access_token    LONGTEXT NULL,              -- Token de acceso CloudBeds
    refresh_token   LONGTEXT NULL,              -- Token de refresco
    status          ENUM('0','1') DEFAULT '1',  -- Estado activo/inactivo
    afip_environment ENUM('prod','test') DEFAULT 'prod',  -- Ambiente AFIP
    cert_prod       VARCHAR(256) DEFAULT 'facturacion2025.pem',
    key_prod        VARCHAR(256) DEFAULT 'MiClavePrivada.key',
    cuit_prod       VARCHAR(20) DEFAULT '30718446976',
    cert_test       VARCHAR(256) DEFAULT 'certificado_homo.pem',
    key_test        VARCHAR(256) DEFAULT 'MiClavePrivada_homo.key',
    cuit_test       VARCHAR(20) DEFAULT '20000000001'
);
```

### 4.2 Tabla: wp_hotels_transactions

Almacena las transacciones importadas de CloudBeds.

```sql
CREATE TABLE wp_hotels_transactions (
    id                  INT(10) AUTO_INCREMENT PRIMARY KEY,
    propertyID          VARCHAR(256) NOT NULL,   -- ID de la propiedad en CloudBeds
    transactionID       VARCHAR(256) NOT NULL,   -- ID transaccion CloudBeds
    reservationID       VARCHAR(256) NOT NULL,   -- ID reserva
    guestID             VARCHAR(256) NOT NULL,   -- ID huesped
    transactionDateTime DATETIME NOT NULL,       -- Fecha/hora transaccion
    transactionType     VARCHAR(50) DEFAULT 'credit',  -- Tipo: credit/debit
    completeName        VARCHAR(256) NOT NULL,   -- Nombre completo huesped
    description         VARCHAR(256) NOT NULL,   -- Descripcion (forma de pago)
    passportNumber      VARCHAR(256) NULL,       -- DNI/Pasaporte
    amount              VARCHAR(256) NOT NULL,   -- Monto total
    currency            VARCHAR(256) NULL,       -- Moneda
    country             VARCHAR(256) NOT NULL,   -- Pais del huesped (ISO)
    city                VARCHAR(256) NULL,       -- Ciudad
    address             LONGTEXT NULL,           -- Direccion
    invoiceUrl          VARCHAR(512) NULL        -- Nombre archivo PDF generado
);
```

### 4.3 Tabla: wp_hotels_logs

Almacena logs de operaciones del plugin.

```sql
CREATE TABLE wp_hotels_logs (
    id              INT(10) AUTO_INCREMENT PRIMARY KEY,
    log_date        DATETIME NOT NULL,          -- Fecha/hora del log
    log_level       VARCHAR(10) NOT NULL,       -- ERROR, INFO, WARNING
    invoice_type    VARCHAR(5) NOT NULL,        -- FB, FT, SYS
    username        VARCHAR(100) NULL,          -- Usuario WordPress
    action          VARCHAR(255) NOT NULL,      -- Accion realizada
    response        TEXT NULL,                  -- Respuesta/resultado
    transaction_id  VARCHAR(256) NULL,          -- ID transaccion relacionada
    amount          VARCHAR(50) NULL,           -- Monto
    cae             VARCHAR(20) NULL,           -- CAE obtenido
    error_code      VARCHAR(20) NULL,           -- Codigo error AFIP
    ip_address      VARCHAR(50) NULL            -- IP del usuario
);
```

---

## 5. AFIP - Sistema de Autenticacion (WSAA)

### 5.1 Descripcion General

El **WSAA (Web Service de Autenticacion y Autorizacion)** es el servicio de AFIP que emite tickets de acceso (Token + Sign) necesarios para consumir cualquier otro Web Service de negocio.

### 5.2 URLs de Servicio

| Ambiente | URL |
|----------|-----|
| **Produccion** | `https://wsaa.afip.gov.ar/ws/services/LoginCms` |
| **Homologacion** | `https://wsaahomo.afip.gov.ar/ws/services/LoginCms` |

### 5.3 Flujo de Autenticacion

```
1. Crear TRA (Ticket de Requerimiento de Acceso)
   |
   v
2. Firmar TRA con certificado digital (PKCS#7)
   |
   v
3. Encodear firma en Base64
   |
   v
4. Llamar a WSAA con loginCms(CMS_base64)
   |
   v
5. Recibir Token + Sign (validos por 12 horas)
```

### 5.4 Estructura del TRA (Ticket de Requerimiento de Acceso)

```xml
<?xml version="1.0" encoding="UTF-8"?>
<loginTicketRequest version="1.0">
    <header>
        <source>serialNumber=CUIT 30718446976, cn=facturaciondava2025</source>
        <destination>cn=wsaa,o=afip,c=ar,serialNumber=CUIT 33693450239</destination>
        <uniqueId>1737554400</uniqueId>
        <generationTime>2026-01-22T10:00:00-03:00</generationTime>
        <expirationTime>2026-01-22T10:01:00-03:00</expirationTime>
    </header>
    <service>wsfe</service>  <!-- o "wsct" para Tipo T -->
</loginTicketRequest>
```

### 5.5 Campos del TRA

| Campo | Descripcion | Formato |
|-------|-------------|---------|
| `source` | Identificacion del solicitante | `serialNumber=CUIT XXXXXXXXXXX, cn=nombre_certificado` |
| `destination` | Siempre WSAA de AFIP | `cn=wsaa,o=afip,c=ar,serialNumber=CUIT 33693450239` |
| `uniqueId` | ID unico (timestamp Unix) | Integer |
| `generationTime` | Momento de creacion | ISO 8601 (`YYYY-MM-DDTHH:MM:SS-03:00`) |
| `expirationTime` | Momento de expiracion | ISO 8601 (generationTime + 60 segundos) |
| `service` | Servicio solicitado | `wsfe` (Tipo B) o `wsct` (Tipo T) |

### 5.6 Request SOAP - loginCms

```xml
<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"
                  xmlns:wsaa="http://wsaa.view.sua.dvadac.desein.afip.gov">
    <soapenv:Header/>
    <soapenv:Body>
        <wsaa:loginCms>
            <wsaa:in0>BASE64_ENCODED_CMS_SIGNATURE</wsaa:in0>
        </wsaa:loginCms>
    </soapenv:Body>
</soapenv:Envelope>
```

### 5.7 Response SOAP - loginCmsReturn

```xml
<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">
    <soapenv:Body>
        <loginCmsResponse>
            <loginCmsReturn>
                <![CDATA[
                    <?xml version="1.0" encoding="UTF-8" standalone="yes"?>
                    <loginTicketResponse version="1.0">
                        <header>
                            <source>cn=wsaa,o=afip,c=ar,serialNumber=CUIT 33693450239</source>
                            <destination>serialNumber=CUIT 30718446976, cn=facturaciondava2025</destination>
                            <uniqueId>988744559</uniqueId>
                            <generationTime>2026-01-22T10:00:08-03:00</generationTime>
                            <expirationTime>2026-01-22T22:00:08-03:00</expirationTime>
                        </header>
                        <credentials>
                            <token>PD94bWwgdmVyc...TOKEN_MUY_LARGO...==</token>
                            <sign>rcRuqxBKtF/1oi3V...FIRMA_DIGITAL...</sign>
                        </credentials>
                    </loginTicketResponse>
                ]]>
            </loginCmsReturn>
        </loginCmsResponse>
    </soapenv:Body>
</soapenv:Envelope>
```

### 5.8 Datos de la Respuesta

| Campo | Descripcion | Duracion |
|-------|-------------|----------|
| `token` | Token de autenticacion (Base64) | 12 horas |
| `sign` | Firma del token | 12 horas |
| `generationTime` | Cuando se genero | - |
| `expirationTime` | Cuando expira | - |

### 5.9 Funciones PHP Relacionadas

```php
// Crear TRA para WSFE (Tipo B)
function CreateTRA($SERVICE)

// Crear TRA para WSCT (Tipo T)
function CreateTRATipoT($SERVICE)

// Firmar TRA con PKCS#7
function SignTRA()
function SignTRATipoT()

// Llamar a WSAA
function CallWSAA($CMS)
```

---

## 6. AFIP - Facturacion Tipo B (WSFE)

### 6.1 Descripcion General

El **WSFE (Web Service de Facturacion Electronica)** permite emitir comprobantes Tipo B para consumidores finales argentinos.

- **Codigo de Comprobante**: 6 (Factura B)
- **Punto de Venta**: 2
- **Web Service**: WSFE v1

### 6.2 URLs de Servicio

| Ambiente | URL |
|----------|-----|
| **Produccion** | `https://servicios1.afip.gov.ar/wsfev1/service.asmx` |
| **Homologacion** | `https://wswhomo.afip.gov.ar/wsfev1/service.asmx` |

### 6.3 Operaciones Principales

| Operacion | Descripcion |
|-----------|-------------|
| `FECAESolicitar` | Solicitar CAE para un comprobante |
| `FECompUltimoAutorizado` | Obtener ultimo nro de comprobante autorizado |
| `FECompConsultar` | Consultar un comprobante emitido |
| `FEParamGetTiposCbte` | Obtener tipos de comprobantes |
| `FEParamGetTiposDoc` | Obtener tipos de documentos |

### 6.4 Estructura de Datos - FECAESolicitar

#### 6.4.1 Request SOAP

```xml
<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
               xmlns:xsd="http://www.w3.org/2001/XMLSchema"
               xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
    <soap:Body>
        <FECAESolicitar xmlns="http://ar.gov.afip.dif.FEV1/">
            <Auth>
                <Token>TOKEN_WSAA</Token>
                <Sign>SIGN_WSAA</Sign>
                <Cuit>30718446976</Cuit>
            </Auth>
            <FeCAEReq>
                <FeCabReq>
                    <CantReg>1</CantReg>
                    <PtoVta>2</PtoVta>
                    <CbteTipo>6</CbteTipo>
                </FeCabReq>
                <FeDetReq>
                    <FECAEDetRequest>
                        <Concepto>1</Concepto>
                        <DocTipo>96</DocTipo>
                        <DocNro>12345678</DocNro>
                        <CbteDesde>1234</CbteDesde>
                        <CbteHasta>1234</CbteHasta>
                        <CbteFch>20260122</CbteFch>
                        <ImpTotal>12100.00</ImpTotal>
                        <ImpTotConc>0</ImpTotConc>
                        <ImpNeto>10000.00</ImpNeto>
                        <ImpOpEx>0</ImpOpEx>
                        <ImpTrib>0</ImpTrib>
                        <ImpIVA>2100.00</ImpIVA>
                        <FchServDesde>NULL</FchServDesde>
                        <FchServHasta>NULL</FchServHasta>
                        <FchVtoPago>NULL</FchVtoPago>
                        <MonId>PES</MonId>
                        <MonCotiz>1</MonCotiz>
                        <Iva>
                            <AlicIva>
                                <Id>5</Id>
                                <BaseImp>10000.00</BaseImp>
                                <Importe>2100.00</Importe>
                            </AlicIva>
                        </Iva>
                    </FECAEDetRequest>
                </FeDetReq>
            </FeCAEReq>
        </FECAESolicitar>
    </soap:Body>
</soap:Envelope>
```

#### 6.4.2 Campos del Request

**Cabecera (FeCabReq)**

| Campo | Tipo | Descripcion | Ejemplo |
|-------|------|-------------|---------|
| `CantReg` | Integer | Cantidad de comprobantes | `1` |
| `PtoVta` | Integer | Punto de venta | `2` |
| `CbteTipo` | Integer | Tipo de comprobante | `6` (Factura B) |

**Detalle (FECAEDetRequest)**

| Campo | Tipo | Descripcion | Ejemplo |
|-------|------|-------------|---------|
| `Concepto` | Integer | 1=Productos, 2=Servicios, 3=Productos y Servicios | `1` |
| `DocTipo` | Integer | Tipo documento receptor | `96` (DNI) |
| `DocNro` | Long | Numero de documento | `12345678` |
| `CbteDesde` | Long | Numero comprobante desde | `1234` |
| `CbteHasta` | Long | Numero comprobante hasta | `1234` |
| `CbteFch` | String | Fecha comprobante (YYYYMMDD) | `20260122` |
| `ImpTotal` | Decimal | Importe total | `12100.00` |
| `ImpTotConc` | Decimal | Importe no gravado | `0` |
| `ImpNeto` | Decimal | Importe neto gravado | `10000.00` |
| `ImpOpEx` | Decimal | Importe exento | `0` |
| `ImpTrib` | Decimal | Importe tributos | `0` |
| `ImpIVA` | Decimal | Importe IVA | `2100.00` |
| `MonId` | String | Codigo moneda | `PES` (Pesos) |
| `MonCotiz` | Decimal | Cotizacion moneda | `1` |

**Alicuotas IVA (AlicIva)**

| Campo | Tipo | Descripcion | Ejemplo |
|-------|------|-------------|---------|
| `Id` | Integer | Codigo alicuota IVA | `5` (21%) |
| `BaseImp` | Decimal | Base imponible | `10000.00` |
| `Importe` | Decimal | Importe IVA | `2100.00` |

#### 6.4.3 Codigos de Tipo de Documento (DocTipo)

| Codigo | Descripcion |
|--------|-------------|
| 80 | CUIT |
| 86 | CUIL |
| 87 | CDI |
| 89 | LE |
| 90 | LC |
| 91 | CI Extranjera |
| 92 | En tramite |
| 93 | Acta de nacimiento |
| 94 | Pasaporte |
| 96 | DNI |
| 99 | Consumidor Final (sin identificar) |

#### 6.4.4 Codigos de Alicuota IVA

| Codigo | Descripcion | Porcentaje |
|--------|-------------|------------|
| 3 | IVA 0% | 0% |
| 4 | IVA 10.5% | 10.5% |
| 5 | IVA 21% | 21% |
| 6 | IVA 27% | 27% |
| 8 | IVA 5% | 5% |
| 9 | IVA 2.5% | 2.5% |

#### 6.4.5 Response SOAP - Aprobado

```xml
<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
    <soap:Body>
        <FECAESolicitarResponse xmlns="http://ar.gov.afip.dif.FEV1/">
            <FECAESolicitarResult>
                <FeCabResp>
                    <Cuit>30718446976</Cuit>
                    <PtoVta>2</PtoVta>
                    <CbteTipo>6</CbteTipo>
                    <FchProceso>20260122120000</FchProceso>
                    <CantReg>1</CantReg>
                    <Resultado>A</Resultado>
                    <Reproceso>N</Reproceso>
                </FeCabResp>
                <FeDetResp>
                    <FECAEDetResponse>
                        <Concepto>1</Concepto>
                        <DocTipo>96</DocTipo>
                        <DocNro>12345678</DocNro>
                        <CbteDesde>1234</CbteDesde>
                        <CbteHasta>1234</CbteHasta>
                        <CbteFch>20260122</CbteFch>
                        <Resultado>A</Resultado>
                        <CAE>76123456789012</CAE>
                        <CAEFchVto>20260201</CAEFchVto>
                    </FECAEDetResponse>
                </FeDetResp>
            </FECAESolicitarResult>
        </FECAESolicitarResponse>
    </soap:Body>
</soap:Envelope>
```

#### 6.4.6 Campos del Response

| Campo | Descripcion | Valores |
|-------|-------------|---------|
| `Resultado` | Resultado de la operacion | `A` = Aprobado, `R` = Rechazado, `P` = Parcial |
| `CAE` | Codigo de Autorizacion Electronico | 14 digitos |
| `CAEFchVto` | Fecha vencimiento CAE | YYYYMMDD |
| `Reproceso` | Indica si es reproceso | `S` / `N` |

### 6.5 Operacion: FECompUltimoAutorizado

#### 6.5.1 Request

```xml
<FECompUltimoAutorizado xmlns="http://ar.gov.afip.dif.FEV1/">
    <Auth>
        <Token>TOKEN</Token>
        <Sign>SIGN</Sign>
        <Cuit>30718446976</Cuit>
    </Auth>
    <PtoVta>2</PtoVta>
    <CbteTipo>6</CbteTipo>
</FECompUltimoAutorizado>
```

#### 6.5.2 Response

```xml
<FECompUltimoAutorizadoResponse>
    <FECompUltimoAutorizadoResult>
        <PtoVta>2</PtoVta>
        <CbteTipo>6</CbteTipo>
        <CbteNro>1233</CbteNro>
    </FECompUltimoAutorizadoResult>
</FECompUltimoAutorizadoResponse>
```

### 6.6 Calculo de Montos

```php
// Monto total recibido de CloudBeds (incluye IVA)
$amount = 12100.00;

// Calculo del neto (base imponible)
$monto_neto = round($amount / 1.21, 2);  // 10000.00

// Calculo del IVA
$monto_iva = round($amount - $monto_neto, 2);  // 2100.00

// Verificacion
// $monto_neto + $monto_iva = $amount
```

### 6.7 Funcion PHP: FECAESolicitar

```php
function FECAESolicitar($Token, $Sign, $Cuit, $code_transaction, $timenow) {
    // 1. Obtener datos de transaccion desde BD
    // 2. Calcular neto e IVA
    // 3. Obtener ultimo comprobante autorizado
    // 4. Construir XML SOAP
    // 5. Enviar request via cURL
    // 6. Retornar response
}
```

---

## 7. AFIP - Facturacion Tipo T (WSCT)

### 7.1 Descripcion General

El **WSCT (Web Service de Comprobantes de Turismo)** permite emitir comprobantes Tipo T para turistas extranjeros, otorgando el reintegro del IVA segun la RG 3971/2016.

- **Codigo de Comprobante**: 195 (Factura T)
- **Punto de Venta**: 2
- **Beneficio**: Reintegro del 21% de IVA al turista

### 7.2 URLs de Servicio

| Ambiente | URL |
|----------|-----|
| **Produccion** | `https://serviciosjava.afip.gob.ar/wsct/CTService` |
| **Homologacion** | `https://fwshomo.afip.gov.ar/wsct/CTService` |

### 7.3 Operaciones Principales

| Operacion | SOAPAction | Descripcion |
|-----------|------------|-------------|
| `autorizarComprobante` | `autorizarComprobante` | Solicitar autorizacion |
| `consultarUltimoComprobanteAutorizado` | `consultarUltimoComprobanteAutorizado` | Ultimo nro comprobante |
| `consultarComprobante` | `consultarComprobante` | Consultar comprobante |
| `consultarPaises` | `consultarPaises` | Listar paises |
| `consultarFormasPago` | `consultarFormasPago` | Listar formas de pago |
| `consultarTiposItem` | `consultarTiposItem` | Listar tipos de item |

### 7.4 Estructura de Datos - autorizarComprobante

#### 7.4.1 Request SOAP

```xml
<?xml version="1.0" encoding="utf-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"
                  xmlns:cts="http://ar.gob.afip.wsct/CTService/">
    <soapenv:Header/>
    <soapenv:Body>
        <cts:autorizarComprobanteRequest>
            <authRequest>
                <token>TOKEN_WSAA</token>
                <sign>SIGN_WSAA</sign>
                <cuitRepresentada>30718446976</cuitRepresentada>
            </authRequest>
            <comprobanteRequest>
                <!-- Datos del comprobante -->
                <codigoTipoComprobante>195</codigoTipoComprobante>
                <numeroPuntoVenta>2</numeroPuntoVenta>
                <numeroComprobante>4</numeroComprobante>
                <fechaEmision>2026-01-22</fechaEmision>
                <codigoTipoAutorizacion>E</codigoTipoAutorizacion>

                <!-- Datos del receptor (turista) -->
                <codigoTipoDocumento>94</codigoTipoDocumento>
                <numeroDocumento>AB123456</numeroDocumento>
                <idImpositivo>9</idImpositivo>
                <codigoPais>203</codigoPais>
                <domicilioReceptor>Av. Brasil 1234, Rio de Janeiro</domicilioReceptor>
                <codigoRelacionEmisorReceptor>01</codigoRelacionEmisorReceptor>

                <!-- Importes -->
                <importeGravado>18181.82</importeGravado>
                <importeReintegro>-3818.18</importeReintegro>
                <importeTotal>18181.82</importeTotal>

                <!-- Moneda -->
                <codigoMoneda>PES</codigoMoneda>
                <cotizacionMoneda>1</cotizacionMoneda>
                <cancelaEnMismaMonedaExtranjera>N</cancelaEnMismaMonedaExtranjera>

                <!-- Items -->
                <arrayItems>
                    <item>
                        <tipo>0</tipo>
                        <codigoTurismo>1</codigoTurismo>
                        <descripcion>Servicio de hoteleria - alojamiento</descripcion>
                        <codigoAlicuotaIVA>5</codigoAlicuotaIVA>
                        <importeIVA>3818.18</importeIVA>
                        <importeItem>22000.00</importeItem>
                    </item>
                </arrayItems>

                <!-- Subtotales IVA -->
                <arraySubtotalesIVA>
                    <subtotalIVA>
                        <codigo>5</codigo>
                        <importe>3818.18</importe>
                    </subtotalIVA>
                </arraySubtotalesIVA>

                <!-- Formas de pago -->
                <arrayFormasPago>
                    <formaPago>
                        <codigo>68</codigo>
                        <tipoTarjeta>99</tipoTarjeta>
                        <numeroTarjeta>999999</numeroTarjeta>
                    </formaPago>
                </arrayFormasPago>
            </comprobanteRequest>
        </cts:autorizarComprobanteRequest>
    </soapenv:Body>
</soapenv:Envelope>
```

#### 7.4.2 Campos del Request

**Cabecera del Comprobante**

| Campo | Tipo | Descripcion | Ejemplo |
|-------|------|-------------|---------|
| `codigoTipoComprobante` | Integer | Siempre 195 para Tipo T | `195` |
| `numeroPuntoVenta` | Integer | Punto de venta | `2` |
| `numeroComprobante` | Long | Numero de comprobante | `4` |
| `fechaEmision` | Date | Fecha emision (YYYY-MM-DD) | `2026-01-22` |
| `codigoTipoAutorizacion` | String | Tipo autorizacion | `E` (Electronica) |

**Datos del Receptor (Turista)**

| Campo | Tipo | Descripcion | Ejemplo |
|-------|------|-------------|---------|
| `codigoTipoDocumento` | Integer | Tipo documento | `94` (Pasaporte) |
| `numeroDocumento` | String | Numero documento (alfanumerico) | `AB123456` |
| `idImpositivo` | Integer | Identificacion impositiva | `9` (Sin ID fiscal arg) |
| `codigoPais` | Integer | Codigo pais AFIP | `203` (Brasil) |
| `domicilioReceptor` | String | Domicilio del turista | `Av. Brasil 1234` |
| `codigoRelacionEmisorReceptor` | String | Relacion | `01` (Cliente) |

**Importes**

| Campo | Tipo | Descripcion | Ejemplo |
|-------|------|-------------|---------|
| `importeGravado` | Decimal | Monto neto (sin IVA) | `18181.82` |
| `importeReintegro` | Decimal | IVA a reintegrar (**NEGATIVO**) | `-3818.18` |
| `importeTotal` | Decimal | Total a pagar (neto) | `18181.82` |

**IMPORTANTE**: `importeReintegro` DEBE ser negativo o cero.

**Item de Turismo**

| Campo | Tipo | Descripcion | Ejemplo |
|-------|------|-------------|---------|
| `tipo` | Integer | Tipo de item | `0` |
| `codigoTurismo` | Integer | Codigo servicio turismo | `1` (Alojamiento sin desayuno) |
| `descripcion` | String | Descripcion del servicio | `Servicio de hoteleria` |
| `codigoAlicuotaIVA` | Integer | Alicuota IVA | `5` (21%) |
| `importeIVA` | Decimal | Importe IVA del item | `3818.18` |
| `importeItem` | Decimal | Importe total del item | `22000.00` |

**Codigos de Turismo**

| Codigo | Descripcion |
|--------|-------------|
| 1 | Servicio de hoteleria - alojamiento sin desayuno |
| 2 | Servicio de hoteleria - alojamiento con desayuno |
| 3 | Gastronomia |
| 4 | Transporte |
| 5 | Otros servicios turisticos |

**Formas de Pago WSCT**

| Codigo | Descripcion | Campos adicionales |
|--------|-------------|-------------------|
| 68 | Tarjeta de Credito | `tipoTarjeta`, `numeroTarjeta` |
| 69 | Tarjeta de Debito | `tipoTarjeta`, `numeroTarjeta` |
| 9 | Transferencia Bancaria | `swiftCode`, `tipoCuenta`, `numeroCuenta` |
| 99 | Otra | (ninguno) |

**Tipos de Tarjeta**

| Codigo | Descripcion |
|--------|-------------|
| 1 | American Express |
| 2 | Visa |
| 3 | Mastercard |
| 99 | Otra |

#### 7.4.3 Response SOAP - Aprobado

```xml
<?xml version="1.0" encoding="UTF-8"?>
<S:Envelope xmlns:S="http://schemas.xmlsoap.org/soap/envelope/">
    <S:Header>
        <info xmlns="https://ar.gob.afip.wsct/CTService/">
            <ambiente>Produccion - SJ5</ambiente>
            <fecha>2026-01-22 12:00:00</fecha>
            <id>1.6.4</id>
        </info>
    </S:Header>
    <S:Body>
        <ns2:autorizarComprobanteResponse xmlns:ns2="http://ar.gob.afip.wsct/CTService/">
            <autorizarComprobanteReturn>
                <cae>76123456789012</cae>
                <fechaVencimientoCae>2026-02-01</fechaVencimientoCae>
                <resultado>A</resultado>
                <codigoTipoComprobante>195</codigoTipoComprobante>
                <numeroPuntoVenta>2</numeroPuntoVenta>
                <numeroComprobante>4</numeroComprobante>
            </autorizarComprobanteReturn>
        </ns2:autorizarComprobanteResponse>
    </S:Body>
</S:Envelope>
```

#### 7.4.4 Response SOAP - Rechazado

```xml
<autorizarComprobanteReturn>
    <arrayErrores>
        <codigoDescripcion>
            <codigo>364</codigo>
            <descripcion>El campo importe reintegro debe ser menor o igual a cero...</descripcion>
        </codigoDescripcion>
    </arrayErrores>
    <resultado>R</resultado>
</autorizarComprobanteReturn>
```

### 7.5 Mapeo de Paises ISO a AFIP

El plugin incluye un mapeo de codigos de pais ISO (2 letras) a codigos AFIP.

| ISO | Pais | Codigo AFIP |
|-----|------|-------------|
| AR | Argentina | 200 |
| BR | Brasil | 203 |
| CL | Chile | 208 |
| UY | Uruguay | 225 |
| US | Estados Unidos | 212 |
| ES | Espana | 410 |
| IT | Italia | 412 |
| FR | Francia | 406 |
| DE | Alemania | 409 |
| GB | Reino Unido | 417 |

**Archivo**: `php/consultas/mapeo_iso_afip_corregido.php`

```php
function obtenerCodigoAFIP($iso_code, $default = 298) {
    $mapeo = [
        'AR' => 200, 'BR' => 203, 'CL' => 208, ...
    ];
    return $mapeo[strtoupper($iso_code)] ?? $default;
}
```

### 7.6 Calculo de Montos Tipo T

```php
// Monto total de CloudBeds (incluye IVA)
$amount = 22000.00;

// Calculo del neto (base imponible)
$montoNeto = round($amount / 1.21, 2);  // 18181.82

// Calculo del IVA
$montoIVA = round($amount - $montoNeto, 2);  // 3818.18

// Importe reintegro (NEGATIVO - es lo que se devuelve al turista)
$importeReintegro = -$montoIVA;  // -3818.18

// Importe total (lo que paga el turista)
$importeTotal = $montoNeto;  // 18181.82
```

### 7.7 Operacion: consultarUltimoComprobanteAutorizado

#### 7.7.1 Request

```xml
<cts:consultarUltimoComprobanteAutorizadoRequest>
    <authRequest>
        <token>TOKEN</token>
        <sign>SIGN</sign>
        <cuitRepresentada>30718446976</cuitRepresentada>
    </authRequest>
    <codigoTipoComprobante>195</codigoTipoComprobante>
    <numeroPuntoVenta>2</numeroPuntoVenta>
</cts:consultarUltimoComprobanteAutorizadoRequest>
```

#### 7.7.2 Response

```xml
<consultarUltimoComprobanteAutorizadoReturn>
    <numeroComprobante>3</numeroComprobante>
</consultarUltimoComprobanteAutorizadoReturn>
```

### 7.8 Diferencias entre WSFE y WSCT

| Aspecto | WSFE (Tipo B) | WSCT (Tipo T) |
|---------|---------------|---------------|
| **Codigo comprobante** | 6 | 195 |
| **Receptor** | Argentinos | Turistas extranjeros |
| **Reintegro IVA** | No | Si (valor negativo) |
| **Codigo pais** | No requerido | Obligatorio |
| **Forma de pago detallada** | No | Si |
| **Codigo turismo** | No | Si |
| **Formato fecha** | YYYYMMDD | YYYY-MM-DD |
| **Namespace XML** | `http://ar.gov.afip.dif.FEV1/` | `http://ar.gob.afip.wsct/CTService/` |

---

## 8. Ejemplos de Requests y Responses

### 8.1 Ejemplo Completo: Factura Tipo B

**Escenario**: Emitir factura por $12,100 a cliente argentino con DNI 12345678

**Request**:
```xml
<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
    <soap:Body>
        <FECAESolicitar xmlns="http://ar.gov.afip.dif.FEV1/">
            <Auth>
                <Token>PD94bWwgdmVyc2lv...TOKEN...</Token>
                <Sign>rcRuqxBKtF...SIGN...</Sign>
                <Cuit>30718446976</Cuit>
            </Auth>
            <FeCAEReq>
                <FeCabReq>
                    <CantReg>1</CantReg>
                    <PtoVta>2</PtoVta>
                    <CbteTipo>6</CbteTipo>
                </FeCabReq>
                <FeDetReq>
                    <FECAEDetRequest>
                        <Concepto>1</Concepto>
                        <DocTipo>96</DocTipo>
                        <DocNro>12345678</DocNro>
                        <CbteDesde>1001</CbteDesde>
                        <CbteHasta>1001</CbteHasta>
                        <CbteFch>20260122</CbteFch>
                        <ImpTotal>12100</ImpTotal>
                        <ImpTotConc>0</ImpTotConc>
                        <ImpNeto>10000</ImpNeto>
                        <ImpOpEx>0</ImpOpEx>
                        <ImpTrib>0</ImpTrib>
                        <ImpIVA>2100</ImpIVA>
                        <FchServDesde>NULL</FchServDesde>
                        <FchServHasta>NULL</FchServHasta>
                        <FchVtoPago>NULL</FchVtoPago>
                        <MonId>PES</MonId>
                        <MonCotiz>1</MonCotiz>
                        <Iva>
                            <AlicIva>
                                <Id>5</Id>
                                <BaseImp>10000</BaseImp>
                                <Importe>2100</Importe>
                            </AlicIva>
                        </Iva>
                    </FECAEDetRequest>
                </FeDetReq>
            </FeCAEReq>
        </FECAESolicitar>
    </soap:Body>
</soap:Envelope>
```

**Response Exitoso**:
```xml
<FECAESolicitarResult>
    <FeCabResp>
        <Cuit>30718446976</Cuit>
        <PtoVta>2</PtoVta>
        <CbteTipo>6</CbteTipo>
        <Resultado>A</Resultado>
    </FeCabResp>
    <FeDetResp>
        <FECAEDetResponse>
            <Resultado>A</Resultado>
            <CAE>76012345678901</CAE>
            <CAEFchVto>20260201</CAEFchVto>
        </FECAEDetResponse>
    </FeDetResp>
</FECAESolicitarResult>
```

### 8.2 Ejemplo Completo: Factura Tipo T

**Escenario**: Emitir factura por $22,000 a turista brasileno con pasaporte AB123456, pago con tarjeta de credito

**Request**:
```xml
<?xml version="1.0" encoding="utf-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"
                  xmlns:cts="http://ar.gob.afip.wsct/CTService/">
    <soapenv:Header/>
    <soapenv:Body>
        <cts:autorizarComprobanteRequest>
            <authRequest>
                <token>TOKEN_WSCT</token>
                <sign>SIGN_WSCT</sign>
                <cuitRepresentada>30718446976</cuitRepresentada>
            </authRequest>
            <comprobanteRequest>
                <codigoTipoComprobante>195</codigoTipoComprobante>
                <numeroPuntoVenta>2</numeroPuntoVenta>
                <numeroComprobante>5</numeroComprobante>
                <fechaEmision>2026-01-22</fechaEmision>
                <codigoTipoAutorizacion>E</codigoTipoAutorizacion>
                <codigoTipoDocumento>94</codigoTipoDocumento>
                <numeroDocumento>AB123456</numeroDocumento>
                <idImpositivo>9</idImpositivo>
                <codigoPais>203</codigoPais>
                <domicilioReceptor>Rua Copacabana 100, Rio de Janeiro</domicilioReceptor>
                <codigoRelacionEmisorReceptor>01</codigoRelacionEmisorReceptor>
                <importeGravado>18181.82</importeGravado>
                <importeReintegro>-3818.18</importeReintegro>
                <importeTotal>18181.82</importeTotal>
                <codigoMoneda>PES</codigoMoneda>
                <cotizacionMoneda>1</cotizacionMoneda>
                <cancelaEnMismaMonedaExtranjera>N</cancelaEnMismaMonedaExtranjera>
                <arrayItems>
                    <item>
                        <tipo>0</tipo>
                        <codigoTurismo>1</codigoTurismo>
                        <descripcion>Servicio de hoteleria - alojamiento</descripcion>
                        <codigoAlicuotaIVA>5</codigoAlicuotaIVA>
                        <importeIVA>3818.18</importeIVA>
                        <importeItem>22000.00</importeItem>
                    </item>
                </arrayItems>
                <arraySubtotalesIVA>
                    <subtotalIVA>
                        <codigo>5</codigo>
                        <importe>3818.18</importe>
                    </subtotalIVA>
                </arraySubtotalesIVA>
                <arrayFormasPago>
                    <formaPago>
                        <codigo>68</codigo>
                        <tipoTarjeta>99</tipoTarjeta>
                        <numeroTarjeta>999999</numeroTarjeta>
                    </formaPago>
                </arrayFormasPago>
            </comprobanteRequest>
        </cts:autorizarComprobanteRequest>
    </soapenv:Body>
</soapenv:Envelope>
```

**Response Exitoso**:
```xml
<autorizarComprobanteReturn>
    <cae>76987654321098</cae>
    <fechaVencimientoCae>2026-02-01</fechaVencimientoCae>
    <resultado>A</resultado>
    <codigoTipoComprobante>195</codigoTipoComprobante>
    <numeroPuntoVenta>2</numeroPuntoVenta>
    <numeroComprobante>5</numeroComprobante>
</autorizarComprobanteReturn>
```

---

## 9. Codigos de Error AFIP

### 9.1 Errores Comunes WSFE (Tipo B)

| Codigo | Descripcion | Solucion |
|--------|-------------|----------|
| 10016 | El numero de comprobante ya fue autorizado | Obtener nuevo numero con FECompUltimoAutorizado |
| 10048 | El campo FchVtoPago no puede ser nulo para Concepto=2 o 3 | Agregar fecha vencimiento pago |
| 10013 | El campo DocNro es obligatorio para DocTipo distinto de 99 | Agregar numero de documento |
| 10015 | El CUIT informado no se encuentra autorizado | Verificar habilitacion en AFIP |
| 10017 | El punto de venta no se encuentra habilitado | Habilitar punto de venta en AFIP |

### 9.2 Errores Comunes WSCT (Tipo T)

| Codigo | Descripcion | Solucion |
|--------|-------------|----------|
| 364 | El campo importe reintegro debe ser menor o igual a cero | Usar valor negativo para importeReintegro |
| 365 | El codigo de pais es obligatorio | Agregar codigoPais valido |
| 366 | El tipo de tarjeta debe ser numerico | Usar codigo numerico (1-99), no string |
| 367 | El numero de tarjeta debe ser numerico | Usar solo digitos (6 ultimos) |
| 1002 | No existe comprobante autorizado para el punto de venta | Es el primer comprobante, usar numeroComprobante=1 |

### 9.3 Errores de Autenticacion WSAA

| Codigo | Descripcion | Solucion |
|--------|-------------|----------|
| cuit_invalido | CUIT no valido | Verificar formato CUIT (11 digitos) |
| certificado_expirado | Certificado vencido | Renovar certificado en AFIP |
| servicio_no_autorizado | No autorizado para el servicio | Vincular servicio en AFIP |

---

## 10. Integracion con CloudBeds

### 10.1 Flujo OAuth 2.0

```
1. Usuario inicia autorizacion
   GET https://hotels.cloudbeds.com/api/v1.1/oauth?
       response_type=code&
       client_id=CLIENT_ID&
       redirect_uri=CALLBACK_URL

2. Usuario autoriza en CloudBeds

3. CloudBeds redirige con codigo
   CALLBACK_URL?code=AUTHORIZATION_CODE

4. Plugin intercambia codigo por tokens
   POST https://hotels.cloudbeds.com/api/v1.1/access_token
   Body: grant_type=authorization_code&
         code=CODE&
         redirect_uri=CALLBACK_URL

5. Recibe access_token y refresh_token
```

### 10.2 Endpoints CloudBeds Utilizados

| Endpoint | Metodo | Descripcion |
|----------|--------|-------------|
| `/api/v1.1/getTransactions` | GET | Obtener transacciones |
| `/api/v1.1/getGuest` | GET | Obtener datos de huesped |
| `/api/v1.1/postCustomFieldValue` | POST | Subir PDF factura |

### 10.3 Estructura de Transaccion CloudBeds

```json
{
    "success": true,
    "data": [
        {
            "propertyID": "123456",
            "transactionID": "189188644507808",
            "reservationID": "RES-001",
            "guestID": "78901",
            "transactionDateTime": "2026-01-22T10:30:00",
            "transactionType": "credit",
            "description": "Credit Card Payment",
            "amount": "22000.00",
            "currency": "ARS"
        }
    ]
}
```

---

## 11. Sistema de Logging

### 11.1 Niveles de Log

| Nivel | Constante | Uso |
|-------|-----------|-----|
| ERROR | `SAH_Logger::ERROR` | Errores criticos, fallos de AFIP |
| WARNING | `SAH_Logger::WARNING` | Advertencias, datos incompletos |
| INFO | `SAH_Logger::INFO` | Operaciones exitosas, informativo |

### 11.2 Tipos de Factura

| Tipo | Constante | Descripcion |
|------|-----------|-------------|
| FB | `SAH_Logger::FACTURA_B` | Factura Tipo B |
| FT | `SAH_Logger::FACTURA_T` | Factura Tipo T |
| SYS | `SAH_Logger::SYSTEM` | Sistema general |

### 11.3 Formato de Log en Archivo

```
[2026-01-22 12:00:00] [INFO] [FT] admin::FECAESolicitarTipoT=>Aprobado - CAE: 76123456789012
  INPUT: {"transaction_id": "189188644507808", "amount": "22000"}
  OUTPUT: {"resultado": "A", "cae": "76123456789012"}
  REQUEST_API:
    <?xml version="1.0"?>...
  RESPONSE_API:
    <?xml version="1.0"?>...
  ---
```

### 11.4 Uso del Logger

```php
// Log simple a BD
SAH_Logger::info(SAH_Logger::FACTURA_B, 'FECAESolicitar', 'Aprobado', [
    'transaction_id' => $transactionID,
    'cae' => $cae
]);

// Log completo (BD + archivo)
SAH_Logger::facturaT(
    SAH_Logger::INFO,
    'FECAESolicitarTipoT',
    'Aprobado - CAE: ' . $cae,
    ['transaction_id' => $transactionID, 'cae' => $cae],  // extra_data
    ['transaction_id' => $transactionID, 'amount' => $amount],  // input
    ['resultado' => 'A', 'cae' => $cae],  // output
    $response_xml,  // API response
    $request_xml    // API request
);
```

---

## 12. Referencias y Recursos

### 12.1 Documentacion Oficial AFIP

| Recurso | URL |
|---------|-----|
| Manual WSAA | https://www.afip.gob.ar/ws/WSAA/WSAA.ObtsanerCertificados.pdf |
| Manual WSFE | https://www.afip.gob.ar/ws/WSFEV1/Manual_Desarrollador_COMPG_v2.pdf |
| Manual WSCT | https://www.afip.gob.ar/fe/documentos/Manual_Desarrollador_WSCT_v01.pdf |
| Tablas de parametros | https://www.afip.gob.ar/fe/documentos/TABLA_FE.xls |

### 12.2 Herramientas Utiles

| Herramienta | Descripcion |
|-------------|-------------|
| SoapUI | Testing de Web Services SOAP |
| Postman | Testing de APIs REST |
| OpenSSL | Gestion de certificados |
| AFIP - Comprobantes en Linea | Verificacion de comprobantes emitidos |

### 12.3 Comunidad y Soporte

| Recurso | URL |
|---------|-----|
| PyAfipWs (referencia) | https://github.com/reingart/pyafipws |
| Sistemas Agiles Wiki | https://www.sistemasagiles.com.ar/trac/wiki/FacturaElectronicaComprobantesTurismo |
| Google Groups PyAfipWs | https://groups.google.com/g/pyafipws |

### 12.4 Certificados Digitales

**Pasos para obtener certificado:**

1. Generar clave privada RSA 2048:
   ```bash
   openssl genrsa -out MiClavePrivada.key 2048
   ```

2. Generar CSR (Certificate Signing Request):
   ```bash
   openssl req -new -key MiClavePrivada.key -out MiPedido.csr \
       -subj "/C=AR/O=MiEmpresa/CN=facturacion/serialNumber=CUIT XXXXXXXXXXX"
   ```

3. Subir CSR en AFIP y descargar certificado (.crt o .pem)

4. Vincular certificado con servicios (wsfe, wsct) en AFIP

---

## Historial de Cambios

| Version | Fecha | Cambios |
|---------|-------|---------|
| 1.2.1 | 2026-01-22 | Fix FECAESolicitarTipoT: cURL, codigos forma pago, importeReintegro negativo |
| 1.2.0 | 2026-01-15 | Soporte inicial Factura Tipo T (WSCT) |
| 1.1.0 | 2025-12-01 | Sistema de logging mejorado |
| 1.0.0 | 2025-01-01 | Version inicial con Factura Tipo B |

---

*Documentacion generada el 2026-01-22*
*Plugin Services API Hotel v1.2.1*
