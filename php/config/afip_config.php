<?php
/**
 * Configuracion centralizada de AFIP
 *
 * Este archivo maneja la configuracion de los ambientes de AFIP (Produccion/Homologacion)
 * y proporciona funciones para obtener las credenciales y URLs correspondientes.
 *
 * @package ServicesAPIHotel
 * @since 1.2.0
 */

if (!defined('ABSPATH')) {
    exit; // Salir si se accede directamente
}

/**
 * URLs de los servicios web de AFIP
 */
define('AFIP_WSAA_PROD', 'https://wsaa.afip.gov.ar/ws/services/LoginCms');
define('AFIP_WSAA_TEST', 'https://wsaahomo.afip.gov.ar/ws/services/LoginCms');

define('AFIP_WSFE_PROD', 'https://servicios1.afip.gov.ar/wsfev1/service.asmx?WSDL');
define('AFIP_WSFE_TEST', 'https://wswhomo.afip.gov.ar/wsfev1/service.asmx?WSDL');

define('AFIP_WSCT_PROD', 'https://serviciosjava.afip.gob.ar/wsct/CTService?WSDL');
define('AFIP_WSCT_TEST', 'https://fwshomo.afip.gov.ar/wsct/CTService?WSDL');

/**
 * Obtiene la configuracion de AFIP desde la base de datos
 *
 * @return array Configuracion de AFIP con URLs, certificados y CUIT
 */
function get_afip_config() {
    global $wpdb;

    $table_name = $wpdb->prefix . "hotels_config";

    // Obtener configuracion activa
    $config = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE status = %s LIMIT 1",
            '1'
        )
    );

    // Si no hay configuracion, devolver valores por defecto
    if (!$config) {
        return get_afip_default_config();
    }

    // Determinar si es ambiente de test
    $is_test = isset($config->afip_environment) && $config->afip_environment === 'test';

    return array(
        'environment'   => $is_test ? 'test' : 'prod',
        'is_test'       => $is_test,

        // URLs de servicios
        'wsaa_url'      => $is_test ? AFIP_WSAA_TEST : AFIP_WSAA_PROD,
        'wsfe_url'      => $is_test ? AFIP_WSFE_TEST : AFIP_WSFE_PROD,
        'wsct_url'      => $is_test ? AFIP_WSCT_TEST : AFIP_WSCT_PROD,

        // Certificados y claves
        'cert'          => $is_test
            ? ($config->cert_test ?? 'certificado.pem')
            : ($config->cert_prod ?? 'facturacion2025.pem'),
        'key'           => $is_test
            ? ($config->key_test ?? 'MiClavePrivada.key')
            : ($config->key_prod ?? 'MiClavePrivada.key'),

        // CUIT
        'cuit'          => $is_test
            ? ($config->cuit_test ?? '20000000001')
            : ($config->cuit_prod ?? '30718446976'),

        // Paths completos (relativos al directorio del plugin)
        // Produccion usa /php/afip/cert/, Homologacion usa /php/afip/cert_preprod/
        'cert_path'     => $is_test
            ? '../afip/cert_preprod/' . ($config->cert_test ?? 'certificado.pem')
            : '../afip/cert/' . ($config->cert_prod ?? 'facturacion2025.pem'),
        'key_path'      => $is_test
            ? '../afip/cert_preprod/' . ($config->key_test ?? 'MiClavePrivada.key')
            : '../afip/cert/' . ($config->key_prod ?? 'MiClavePrivada.key'),
    );
}

/**
 * Obtiene la configuracion por defecto de AFIP
 *
 * @return array Configuracion por defecto (produccion)
 */
function get_afip_default_config() {
    return array(
        'environment'   => 'prod',
        'is_test'       => false,
        'wsaa_url'      => AFIP_WSAA_PROD,
        'wsfe_url'      => AFIP_WSFE_PROD,
        'wsct_url'      => AFIP_WSCT_PROD,
        'cert'          => 'facturacion2025.pem',
        'key'           => 'MiClavePrivada.key',
        'cuit'          => '30718446976',
        'cert_path'     => '../afip/cert/facturacion2025.pem',
        'key_path'      => '../afip/cert/MiClavePrivada.key',
    );
}

/**
 * Verifica si el ambiente actual es de testing
 *
 * @return bool True si es ambiente de testing
 */
function is_afip_test_environment() {
    $config = get_afip_config();
    return $config['is_test'];
}

/**
 * Obtiene el nombre del ambiente actual para mostrar en UI
 *
 * @return string Nombre del ambiente formateado
 */
function get_afip_environment_label() {
    $config = get_afip_config();
    return $config['is_test']
        ? 'HOMOLOGACION (Testing)'
        : 'PRODUCCION';
}

/**
 * Obtiene la clase CSS para el indicador de ambiente
 *
 * @return string Clase CSS (notice-info para test, notice-warning para prod)
 */
function get_afip_environment_notice_class() {
    return is_afip_test_environment() ? 'notice-info' : 'notice-warning';
}

/**
 * Actualiza el ambiente de AFIP en la base de datos
 *
 * @param string $environment 'prod' o 'test'
 * @return bool True si se actualizo correctamente
 */
function update_afip_environment($environment) {
    global $wpdb;

    if (!in_array($environment, array('prod', 'test'))) {
        return false;
    }

    $table_name = $wpdb->prefix . "hotels_config";

    $result = $wpdb->update(
        $table_name,
        array('afip_environment' => $environment),
        array('status' => '1'),
        array('%s'),
        array('%s')
    );

    return $result !== false;
}

/**
 * Actualiza la configuracion de credenciales AFIP
 *
 * @param array $data Datos a actualizar
 * @return bool True si se actualizo correctamente
 */
function update_afip_credentials($data) {
    global $wpdb;

    $table_name = $wpdb->prefix . "hotels_config";

    $allowed_fields = array(
        'afip_environment',
        'cert_prod', 'key_prod', 'cuit_prod',
        'cert_test', 'key_test', 'cuit_test'
    );

    $update_data = array();
    $update_format = array();

    foreach ($allowed_fields as $field) {
        if (isset($data[$field])) {
            $update_data[$field] = sanitize_text_field($data[$field]);
            $update_format[] = '%s';
        }
    }

    if (empty($update_data)) {
        return false;
    }

    $result = $wpdb->update(
        $table_name,
        $update_data,
        array('status' => '1'),
        $update_format,
        array('%s')
    );

    return $result !== false;
}
