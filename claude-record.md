# Claude Record - Plugin Services API Hotel (AFIP)

> Este documento sirve como contexto para futuras sesiones de Claude. Contiene toda la informacion necesaria para entender el plugin sin necesidad de analizar el codigo desde cero.

---

## 1. Descripcion General

**Nombre**: Services API Hotel
**Version**: 1.2.1
**Ubicacion**: `wp-content/plugins/afiph1004/`
**Proposito**: Plugin de WordPress para facturacion electronica AFIP integrado con CloudBeds (PMS hotelero)

### Tipos de Factura Soportados

| Tipo | Codigo AFIP | Servicio Web | Destinatario |
|------|-------------|--------------|--------------|
| **Factura B** | 6 | WSFE | Consumidores argentinos |
| **Factura T** | 195 | WSCT | Turistas extranjeros (reintegro IVA) |

### Punto de Venta
- **Produccion**: PtoVta = 2
- **CUIT**: 30718446976

---

## 2. Arquitectura de Archivos

```
afiph1004/
├── init.php                      # Punto de entrada del plugin
├── uninstall.php                 # Script de desinstalacion
├── claude-record.md              # Este archivo (contexto para Claude)
├── DOCUMENTACION_TECNICA_AFIP.md # Documentacion tecnica completa
│
├── php/
│   ├── consultas/
│   │   ├── main_global.php       # ARCHIVO PRINCIPAL - toda la logica de facturacion
│   │   ├── barcode.php           # Generacion codigo de barras
│   │   └── mapeo_iso_afip_corregido.php  # Mapeo paises ISO -> AFIP
│   │
│   ├── config/
│   │   └── afip_config.php       # URLs y configuracion AFIP (prod/test)
│   │
│   ├── afip/
│   │   ├── cert/                 # Certificados PRODUCCION
│   │   ├── cert_preprod/         # Certificados HOMOLOGACION
│   │   ├── wsaa.wsdl             # WSDL autenticacion
│   │   ├── wsfe.wsdl             # WSDL facturacion tipo B
│   │   ├── TRA_WSFE.xml          # Ticket Request Auth (Tipo B)
│   │   ├── TRA_WSCT.xml          # Ticket Request Auth (Tipo T)
│   │   └── response-loginCms.xml # Token/Sign vigentes (cache)
│   │
│   └── config_init/
│       ├── transactions-list.php # UI listado transacciones
│       └── logs-list.php         # Visor de logs
│
├── js/
│   └── main.js                   # JavaScript principal (AJAX handlers)
│
├── includes/
│   └── class-logger.php          # Sistema de logging
│
└── vendor/                       # Dependencias (Dompdf, QRCode, etc.)
```

---

## 3. Sistema de Almacenamiento

### Ubicacion de Archivos

**Sistema NUEVO (actual)** - Todo se guarda en WordPress uploads:
```
wp-content/uploads/hotels/
├── invoicespdf/      # PDFs Factura B
├── invoicespdft/     # PDFs Factura T
├── invoicesxml/      # XMLs Factura B
└── invoicesxmlt/     # XMLs Factura T
```

**Sistema LEGACY (historico)** - Archivos antiguos en carpeta del plugin:
```
wp-content/plugins/afiph1004/php/
├── invoicespdf/      # PDFs Factura B (legacy)
├── invoicespdft/     # PDFs Factura T (legacy)
├── invoicesxml/      # XMLs Factura B (legacy)
└── invoicesxmlt/     # XMLs Factura T (legacy)
```

### Funciones de Almacenamiento (main_global.php)

| Funcion | Proposito | Ubicacion |
|---------|-----------|-----------|
| `get_hotels_upload_dir()` | Path fisico base | `uploads/hotels/` |
| `get_hotels_upload_url()` | URL base | `uploads/hotels/` |
| `get_pdf_save_path($file, $type)` | Path para guardar PDF nuevo | `uploads/hotels/invoicespdf[t]/` |
| `get_xml_save_path($file, $type)` | Path para guardar XML nuevo | `uploads/hotels/invoicesxml[t]/` |
| `get_pdf_url($file, $type)` | URL para descargar PDF | `uploads/hotels/invoicespdf[t]/` |
| `find_pdf_file($file, $type)` | Buscar PDF con fallback legacy->uploads | Retorna path + url + location |
| `find_xml_file($file, $type)` | Buscar XML con fallback legacy->uploads | Retorna path + url + location |
| `ensure_hotels_upload_dirs()` | Crear directorios si no existen | Crea estructura completa |

### Logica de Fallback

Las funciones `find_pdf_file()` y `find_xml_file()`:
1. **Primero** buscan en carpeta LEGACY (plugin)
2. **Si no existe**, buscan en UPLOADS (nuevo)
3. Retornan `location: 'legacy'`, `'uploads'`, o `'not_found'`

---

## 4. Flujo de Facturacion

### 4.1 Factura B (Argentinos) - WSFE

**Funcion principal**: `genFacturar()` en js/main.js
**Handler PHP**: Buscar `isset($_POST['gen_facturar'])` en main_global.php

**Flujo**:
```
1. Usuario clickea "Generar Factura B"
2. AJAX envia: action=foo, gen_facturar=nonce, code_transaction=ID, type_gen='NULL'
3. PHP:
   a. Verifica si ya existe factura (invoiceUrl en BD)
   b. Si existe -> retorna URL para descargar
   c. Si no existe:
      - Autentica con WSAA (obtiene Token/Sign)
      - Obtiene ultimo comprobante autorizado (FECompUltimoAutorizado)
      - Solicita CAE (FECAESolicitar)
      - Si aprobado: genera PDF, guarda XML, actualiza BD
      - Retorna: {data: 'Generado con exito.', name_file: 'archivo.pdf', rell: 'URL'}
4. JavaScript:
   - Descarga PDF automaticamente
   - Recarga pagina despues de 1.5s
```

### 4.2 Factura T (Turistas) - WSCT

**Funcion principal**: `genFacturarT()` en js/main.js
**Handler PHP**: Buscar `isset($_POST['gen_facturar_tipo_t'])` en main_global.php

**Flujo**: Similar a Factura B pero usa:
- Servicio WSCT en lugar de WSFE
- Funcion `FECAESolicitarTipoT()`
- Incluye datos adicionales: codigoPais, importeReintegro (negativo), formas de pago

### 4.3 Mecanismo de Retry

**Problema**: Error 302 de AFIP - "La numeracion y fecha de comprobante no corresponde"
**Causa**: La fecha de la transaccion es muy antigua (mas de unos dias)
**Solucion**: Reintentar con fecha actual

**Implementado en ambas facturas (B y T)**:

```javascript
// js/main.js - Cuando hay error, muestra modal con opciones:
Swal.fire({
    title: 'Error en la conexion con AFIP',
    showDenyButton: true,
    confirmButtonText: 'Volver a intentar',      // type_gen: 'NULL' (misma fecha)
    denyButtonText: 'Probar con fecha de hoy',   // type_gen: 'now' (fecha actual)
})
```

**PHP - Logica de fechas**:
```php
// Factura B (FECAESolicitar):
$CbteFch = ($timenow === 'NULL')
    ? date('Ymd', strtotime($transactionDateTime))  // Fecha transaccion
    : date('Ymd');                                   // Fecha actual

// Factura T (FECAESolicitarTipoT):
$fechaEmision = ($timenow === 'NULL')
    ? date('Y-m-d', strtotime($transactionDateTime))  // Fecha transaccion
    : date('Y-m-d');                                   // Fecha actual
```

---

## 5. Formatos de Fecha AFIP

**IMPORTANTE**: Los formatos son DIFERENTES entre servicios:

| Servicio | Campo | Formato PHP | Ejemplo |
|----------|-------|-------------|---------|
| WSFE (Factura B) | CbteFch | `date('Ymd')` | `20260123` |
| WSCT (Factura T) | fechaEmision | `date('Y-m-d')` | `2026-01-23` |

---

## 6. Flujo de Descarga PDF

### JavaScript (js/main.js)
```javascript
if (typeof data.data.rell !== 'undefined') {
    var link = document.createElement('a');
    link.href = data.data.rell;        // URL del PDF
    link.download = data.data.name_file; // Nombre archivo
    link.click();
    link.remove();
}
// Recargar pagina despues de la descarga
setTimeout(function() {
    location.reload();
}, 1500);
```

### Respuesta PHP exitosa
```php
wp_send_json_success([
    'data' => 'Generado con exito.',
    'name_file' => $generate_pdf,      // ej: 'RES123_23_01_2026_10_30_00.pdf'
    'rell' => $url_file_web,           // ej: 'https://sitio.com/wp-content/uploads/hotels/invoicespdf/RES123_23_01_2026_10_30_00.pdf'
    'file_id' => $data_send_pdf,
    'cae' => $cae,                     // Solo Factura T
    'fecha_vto' => $fechaVencimientoCae // Solo Factura T
]);
```

---

## 7. Base de Datos

### Tabla: wp_hotels_transactions

| Campo | Tipo | Descripcion |
|-------|------|-------------|
| id | INT | ID interno |
| transactionID | VARCHAR | ID transaccion CloudBeds |
| reservationID | VARCHAR | ID reserva |
| transactionDateTime | DATETIME | Fecha/hora transaccion |
| completeName | VARCHAR | Nombre huesped |
| passportNumber | VARCHAR | DNI/Pasaporte |
| amount | VARCHAR | Monto total |
| country | VARCHAR | Codigo pais ISO (ej: 'BR', 'US') |
| invoiceUrl | VARCHAR | Nombre archivo PDF generado |

### Tabla: wp_hotels_logs

Almacena logs de operaciones (errores, exitos, requests/responses AFIP)

---

## 8. Errores Comunes AFIP

### Error 302 (WSFE/WSCT)
- **Mensaje**: "La numeracion y fecha de comprobante no corresponde con el proximo a autorizar"
- **Causa**: Fecha muy antigua
- **Solucion**: Usar mecanismo de retry con `type_gen: 'now'`

### Error 364 (WSCT)
- **Mensaje**: "El campo importe reintegro debe ser menor o igual a cero"
- **Causa**: importeReintegro debe ser negativo
- **Solucion**: Usar `-$montoIVA` en lugar de `$montoIVA`

### Error 10016 (WSFE)
- **Mensaje**: "El numero de comprobante ya fue autorizado"
- **Causa**: Numero duplicado
- **Solucion**: Obtener nuevo numero con FECompUltimoAutorizado

---

## 9. Funciones Principales (main_global.php)

### Autenticacion WSAA
- `CreateTRA($SERVICE)` - Crea TRA para WSFE
- `CreateTRATipoT($SERVICE)` - Crea TRA para WSCT
- `SignTRA()` / `SignTRATipoT()` - Firma con certificado
- `CallWSAA($CMS)` - Llama a WSAA, obtiene Token/Sign

### Facturacion
- `FECAESolicitar()` - Solicita CAE Factura B
- `FECAESolicitarTipoT()` - Solicita CAE Factura T
- `FECompUltimoAutorizado()` - Ultimo comprobante B
- `FECompUltimoAutorizadoTipoT()` - Ultimo comprobante T

### Generacion PDF
- `generatePDF($code, $transactionID)` - Genera PDF Factura B
- `generatePDFTipoT($code, $transactionID)` - Genera PDF Factura T

### Utilidades
- `saveUrlFile($code, $name_file)` - Guarda nombre PDF en BD
- `sendPDF()` - Envia PDF a CloudBeds
- `generate_qr($name_file)` - Genera codigo QR
- `obtenerCodigoAFIP($iso_code)` - Convierte ISO a codigo AFIP

---

## 10. Cambios Recientes (Enero 2026)

### 10.1 Sistema de Almacenamiento Refactorizado
- Movido de carpeta plugin a `wp-content/uploads/hotels/`
- Implementado fallback para archivos legacy
- Funciones: `get_pdf_save_path()`, `find_pdf_file()`, etc.

### 10.2 Retry para Factura T
- Agregado mecanismo de retry igual a Factura B
- Modal con opciones: "Volver a intentar" / "Probar con fecha de hoy"
- PHP maneja `type_gen: 'now'` para usar fecha actual

### 10.3 Descarga + Reload
- Modificado js/main.js para descargar PDF automaticamente
- Agregado `location.reload()` con delay de 1.5s despues de descarga
- Aplicado a los 6 flujos de exito (3 Factura B + 3 Factura T)

---

## 11. URLs de Servicios AFIP

### Produccion
| Servicio | URL |
|----------|-----|
| WSAA | `https://wsaa.afip.gov.ar/ws/services/LoginCms` |
| WSFE | `https://servicios1.afip.gov.ar/wsfev1/service.asmx` |
| WSCT | `https://serviciosjava.afip.gob.ar/wsct/CTService` |

### Homologacion
| Servicio | URL |
|----------|-----|
| WSAA | `https://wsaahomo.afip.gov.ar/ws/services/LoginCms` |
| WSFE | `https://wswhomo.afip.gov.ar/wsfev1/service.asmx` |
| WSCT | `https://fwshomo.afip.gov.ar/wsct/CTService` |

---

## 12. Tips para Debugging

### Ver logs
- Archivo: `log/debug_YYYY-MM-DD.log`
- BD: tabla `wp_hotels_logs`
- Visor: Menu WordPress -> Logs

### Ver requests/responses AFIP
- XMLs guardados en `uploads/hotels/invoicesxml[t]/`
- Archivos `*_REQUEST.xml` y `*.xml` (response)

### Verificar PDF generado
```bash
ls -la wp-content/uploads/hotels/invoicespdf/
ls -la wp-content/uploads/hotels/invoicespdft/
```

### Probar URL de descarga
La URL deberia ser accesible directamente en el navegador:
`https://tu-sitio.com/wp-content/uploads/hotels/invoicespdf/archivo.pdf`

---

*Documento generado: 2026-01-23*
*Ultima actualizacion: Implementacion retry Factura T + descarga automatica*
