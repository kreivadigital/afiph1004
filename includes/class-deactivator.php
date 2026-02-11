<?php
/**
 * Clase de Desactivacion del Plugin
 *
 * Esta clase define todo el codigo que se ejecuta durante la desactivacion del plugin.
 * NOTA: La desactivacion NO elimina datos, solo limpia recursos temporales.
 * Para eliminar datos completamente, usar uninstall.php
 *
 * @package ServicesAPIHotel
 * @since 1.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAH_Deactivator {

    /**
     * Metodo principal de desactivacion
     *
     * Limpia recursos temporales sin eliminar datos del usuario.
     *
     * @since 1.2.0
     */
    public static function deactivate() {
        self::clear_transients();
        self::clear_scheduled_tasks();
        self::clear_cache();
    }

    /**
     * Limpiar transients del plugin
     *
     * @since 1.2.0
     */
    private static function clear_transients() {
        $transients = array(
            'sah_afip_token_cache',
            'sah_cloudbeds_token',
            'sah_wsaa_token',
            'sah_wsfe_token',
            'sah_wsct_token',
        );

        foreach ($transients as $transient) {
            delete_transient($transient);
        }
    }

    /**
     * Limpiar tareas programadas
     *
     * @since 1.2.0
     */
    private static function clear_scheduled_tasks() {
        $hooks = array(
            'sah_refresh_token_cron',
            'sah_cleanup_invoices_cron',
            'sah_sync_transactions_cron',
        );

        foreach ($hooks as $hook) {
            wp_clear_scheduled_hook($hook);
        }
    }

    /**
     * Limpiar cache de WordPress
     *
     * @since 1.2.0
     */
    private static function clear_cache() {
        // Limpiar object cache
        wp_cache_flush();

        // Limpiar rewrite rules
        flush_rewrite_rules();
    }
}
