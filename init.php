<?php
/**
 * Plugin Name: Services API Hotel
 * Plugin URI: #
 * Description: Servicios API de Hotel - Facturacion AFIP (Tipo B y T)
 * Version: 1.2.1
 * Author: Osward Pacheco
 * Author URI: https://github.com/OswardJr
 * License: GPL
 * Text Domain: services-api-hotel
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Constantes del plugin
define('SAH_PLUGIN_VERSION', '1.2.1');
define('SAH_PLUGIN_FILE', __FILE__);
define('SAH_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SAH_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Activacion del plugin - Crear tablas y opciones
 */
function ss_options_install()
{
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();

    // Tabla de configuracion
    $table_name = $wpdb->prefix . "hotels_config";
    $sql = "CREATE TABLE $table_name (
            `id` int(10) NOT NULL AUTO_INCREMENT,
            `api_endpoint` varchar(256) CHARACTER SET utf8 NOT NULL,
            `version` varchar(256) CHARACTER SET utf8 NOT NULL,
            `client_id` varchar(256) CHARACTER SET utf8 NOT NULL,
            `client_secret` varchar(256) CHARACTER SET utf8 NOT NULL,
            `redirect_url` varchar(256) CHARACTER SET utf8 NOT NULL,
            `code_auth` varchar(256) CHARACTER SET utf8 NULL,
            `access_token` longtext CHARACTER SET utf8 NULL,
            `refresh_token` longtext CHARACTER SET utf8 NULL,
            `status` enum('0','1') DEFAULT '1' NOT NULL,
            `afip_environment` enum('prod','test') DEFAULT 'prod' NOT NULL,
            `cert_prod` varchar(256) CHARACTER SET utf8 DEFAULT 'facturacion2025.pem',
            `key_prod` varchar(256) CHARACTER SET utf8 DEFAULT 'MiClavePrivada.key',
            `cuit_prod` varchar(20) CHARACTER SET utf8 DEFAULT '30718446976',
            `cert_test` varchar(256) CHARACTER SET utf8 DEFAULT 'certificado_homo.pem',
            `key_test` varchar(256) CHARACTER SET utf8 DEFAULT 'MiClavePrivada_homo.key',
            `cuit_test` varchar(20) CHARACTER SET utf8 DEFAULT '20000000001',
            PRIMARY KEY (`id`)
          ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    // Tabla de transacciones
    $table_name_transactions = $wpdb->prefix . "hotels_transactions";
    $sql2 = "CREATE TABLE $table_name_transactions (
            `id` int(10) NOT NULL AUTO_INCREMENT,
            `propertyID` varchar(256) CHARACTER SET utf8 NOT NULL,
            `transactionID` varchar(256) CHARACTER SET utf8 NOT NULL,
            `reservationID` varchar(256) CHARACTER SET utf8 NOT NULL,
            `guestID` varchar(256) CHARACTER SET utf8 NOT NULL,
            `transactionDateTime` DATETIME NOT NULL,
            `transactionType` varchar(50) CHARACTER SET utf8 DEFAULT 'credit',
            `completeName` varchar(256) CHARACTER SET utf8 NOT NULL,
            `description` varchar(256) CHARACTER SET utf8 NOT NULL,
            `passportNumber` varchar(256) CHARACTER SET utf8 NULL,
            `amount` varchar(256) CHARACTER SET utf8 NOT NULL,
            `currency` varchar(256) CHARACTER SET utf8 NULL,
            `country` varchar(256) CHARACTER SET utf8 NOT NULL,
            `city` varchar(256) CHARACTER SET utf8 NULL,
            `address` longtext CHARACTER SET utf8 NULL,
            `invoiceUrl` varchar(512) CHARACTER SET utf8 NULL,
            PRIMARY KEY (`id`)
          ) $charset_collate;";

    dbDelta($sql2);

    // Ejecutar migraciones para tablas existentes
    sah_run_migrations();

    // Guardar version del plugin
    update_option('sah_plugin_version', SAH_PLUGIN_VERSION);
}

/**
 * Ejecutar migraciones para actualizar tablas existentes
 */
function sah_run_migrations()
{
    global $wpdb;

    $table_config = $wpdb->prefix . "hotels_config";
    $table_transactions = $wpdb->prefix . "hotels_transactions";

    // Migraciones para wp_hotels_config
    $config_columns = array(
        'afip_environment' => "ALTER TABLE {$table_config} ADD COLUMN `afip_environment` enum('prod','test') DEFAULT 'prod' NOT NULL",
        'cert_prod' => "ALTER TABLE {$table_config} ADD COLUMN `cert_prod` varchar(256) DEFAULT 'facturacion2025.pem'",
        'key_prod' => "ALTER TABLE {$table_config} ADD COLUMN `key_prod` varchar(256) DEFAULT 'MiClavePrivada.key'",
        'cuit_prod' => "ALTER TABLE {$table_config} ADD COLUMN `cuit_prod` varchar(20) DEFAULT '30718446976'",
        'cert_test' => "ALTER TABLE {$table_config} ADD COLUMN `cert_test` varchar(256) DEFAULT 'certificado_homo.pem'",
        'key_test' => "ALTER TABLE {$table_config} ADD COLUMN `key_test` varchar(256) DEFAULT 'MiClavePrivada_homo.key'",
        'cuit_test' => "ALTER TABLE {$table_config} ADD COLUMN `cuit_test` varchar(20) DEFAULT '20000000001'",
    );

    foreach ($config_columns as $column => $sql) {
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$table_config} LIKE '{$column}'");
        if (empty($column_exists)) {
            $wpdb->query($sql);
        }
    }

    // Migraciones para wp_hotels_transactions
    $transaction_columns = array(
        'transactionType' => "ALTER TABLE {$table_transactions} ADD COLUMN `transactionType` varchar(50) DEFAULT 'credit' AFTER `transactionDateTime`",
        'invoiceUrl' => "ALTER TABLE {$table_transactions} ADD COLUMN `invoiceUrl` varchar(512) NULL",
    );

    foreach ($transaction_columns as $column => $sql) {
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$table_transactions} LIKE '{$column}'");
        if (empty($column_exists)) {
            $wpdb->query($sql);
        }
    }

    // Actualizar registros sin transactionType
    $wpdb->query("UPDATE {$table_transactions} SET transactionType = 'credit' WHERE transactionType IS NULL OR transactionType = ''");
}

/**
 * Desactivacion del plugin
 * NOTA: No borramos datos aqui, solo limpiamos opciones temporales
 * Para borrar datos completamente, usar uninstall.php
 */
function pl_deactivation()
{
    // Limpiar opciones temporales (transients, cache, etc.)
    delete_transient('sah_afip_token_cache');

    // Limpiar tareas programadas si las hubiera
    wp_clear_scheduled_hook('sah_refresh_token_cron');

    // NO borramos las tablas aqui - eso va en uninstall.php
}

// Cargar clases de activacion/desactivacion
require_once(SAH_PLUGIN_DIR . 'includes/class-activator.php');
require_once(SAH_PLUGIN_DIR . 'includes/class-deactivator.php');

// Hooks de activacion y desactivacion
register_activation_hook(__FILE__, array('SAH_Activator', 'activate'));
register_deactivation_hook(__FILE__, array('SAH_Deactivator', 'deactivate'));

// Mantener compatibilidad con funcion legacy
register_activation_hook(__FILE__, 'ss_options_install');
register_deactivation_hook(__FILE__, 'pl_deactivation');

// Ejecutar migraciones automaticamente si la version cambio
add_action('admin_init', 'sah_check_migrations');
function sah_check_migrations() {
    $installed_version = get_option('sah_plugin_version', '0');
    if (version_compare($installed_version, SAH_PLUGIN_VERSION, '<')) {
        sah_run_migrations();
        update_option('sah_plugin_version', SAH_PLUGIN_VERSION);
    }
}

// Constantes legacy (para compatibilidad)
define('ROOTDIR', plugin_dir_path(__FILE__));
define('baseURLN', 'datacita');

// Cargar configuracion AFIP (debe ir primero)
require_once(SAH_PLUGIN_DIR . 'php/config/afip_config.php');

// Cargar archivos del plugin
require_once(SAH_PLUGIN_DIR . 'php/config_init/config-list.php');
require_once(SAH_PLUGIN_DIR . 'php/config_init/config-create.php');
require_once(SAH_PLUGIN_DIR . 'php/config_init/config-update.php');
require_once(SAH_PLUGIN_DIR . 'php/varios/functions.php');
require_once(SAH_PLUGIN_DIR . 'php/varios/panel_admin.php');
require_once(SAH_PLUGIN_DIR . 'php/consultas/main_global.php');
require_once(SAH_PLUGIN_DIR . 'php/config_init/transactions-list.php');
require_once(SAH_PLUGIN_DIR . 'php/config_init/transactions-list_dev.php');

//menu items

add_action('admin_menu', 'config_modifymenu');
function config_modifymenu()
{

    // global $wpdb;

    // $table_name = $wpdb->prefix . "citas";
    // $varn = '1';
    // $dataquery= $wpdb->get_results("SELECT COUNT(id) as config_id FROM $table_name WHERE status = '$varn' ");
    // foreach ( $dataquery as $row ) {
    //     $datar = $row->config_id;
    // }

    // if($datar > 0){

    // }else{

    // }

    //this is the main item for the menu
    add_menu_page(
        'Hotel API', //page title
        'Hotel API', //menu title
        'manage_options', //capabilities
        'config_list', //menu slug
        'config_list' //function
    );

    //this is a submenu
    add_submenu_page(
        'null', //parent slug
        'Añadir configuración', //page title
        'Añadir configuración', //menu title
        'manage_options', //capability
        'config_create', //menu slug
        'config_create'
    ); //function

    //this submenu is HIDDEN, however, we need to add it anyways
    add_submenu_page(
        null, //parent slug
        'Act configuración', //page title
        'Actualizar configuración', //menu title
        'manage_options', //capability
        'config_update', //menu slug
        'config_update'
    ); //function

    //this submenu is HIDDEN, however, we need to add it anyways
    add_submenu_page(
        'config_list', //parent slug
        'Transacciones', //page title
        'Listado de Transacciones', //menu title
        'manage_options', //capability
        'transactions_list', //menu slug
        'transactions_list'
    ); //function

    // --- ¡AQUÍ ES DONDE AÑADES LA PÁGINA DE DESARROLLO OCULTA! ---
    add_submenu_page(
        null, // ¡Parent slug como NULL para ocultarla del menú!
        'Transacciones DEV', // page title (para el título de la pestaña del navegador)
        'Transacciones DEV', // menu title (no se mostrará, pero es un campo requerido)
        'manage_options', // capability (asegúrate de que tu usuario tenga esta capacidad, como administrador)
        'transactions_list_dev', // ¡menu slug! Este será el identificador en la URL
        'transactions_list_dev' // ¡función de callback! El nombre de tu función en transactions_list_dev.php
    );
}
