<?php
/**
 * Uninstall Script - Services API Hotel
 *
 * Este archivo se ejecuta cuando el usuario elimina el plugin desde WordPress.
 * Limpia todas las tablas y opciones creadas por el plugin.
 *
 * @package ServicesAPIHotel
 * @since 1.2.0
 */

// Verificar que WordPress esta ejecutando la desinstalacion
if (!defined('WP_UNINSTALL_PLUGIN')) {
    die('Acceso directo no permitido.');
}

// Verificar permisos del usuario
if (!current_user_can('activate_plugins')) {
    return;
}

// Verificar que es el plugin correcto el que se esta desinstalando
if (plugin_basename(__FILE__) !== 'afiph1004/uninstall.php') {
    return;
}

global $wpdb;

/**
 * Eliminar tablas del plugin
 */
$tables_to_drop = array(
    $wpdb->prefix . 'hotels_config',
    $wpdb->prefix . 'hotels_transactions',
);

foreach ($tables_to_drop as $table) {
    $wpdb->query("DROP TABLE IF EXISTS {$table}");
}

/**
 * Eliminar opciones del plugin
 */
$options_to_delete = array(
    'sah_plugin_version',
    'sah_afip_token_cache',
);

foreach ($options_to_delete as $option) {
    delete_option($option);
}

/**
 * Eliminar transients del plugin
 */
$transients_to_delete = array(
    'sah_afip_token_cache',
    'sah_cloudbeds_token',
);

foreach ($transients_to_delete as $transient) {
    delete_transient($transient);
}

/**
 * Eliminar archivos generados (facturas PDF/XML)
 * NOTA: Comentado por seguridad - descomentar si se desea eliminar
 */
/*
$upload_dirs = array(
    WP_PLUGIN_DIR . '/afiph1004/php/invoicespdf/',
    WP_PLUGIN_DIR . '/afiph1004/php/invoicesxml/',
    WP_PLUGIN_DIR . '/afiph1004/php/invoicespdft/',
    WP_PLUGIN_DIR . '/afiph1004/php/invoicesxmlt/',
);

foreach ($upload_dirs as $dir) {
    if (is_dir($dir)) {
        $files = glob($dir . '*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
}
*/

/**
 * Limpiar cualquier tarea programada
 */
wp_clear_scheduled_hook('sah_refresh_token_cron');
wp_clear_scheduled_hook('sah_cleanup_invoices_cron');

/**
 * Limpiar cache de WordPress
 */
wp_cache_flush();
