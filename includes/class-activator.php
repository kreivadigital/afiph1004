<?php
/**
 * Clase de Activacion del Plugin
 *
 * Esta clase define todo el codigo que se ejecuta durante la activacion del plugin.
 *
 * @package ServicesAPIHotel
 * @since 1.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAH_Activator {

    /**
     * Metodo principal de activacion
     *
     * Crea las tablas necesarias y ejecuta las migraciones.
     *
     * @since 1.2.0
     */
    public static function activate() {
        self::create_tables();
        self::run_migrations();
        self::set_default_options();

        // Guardar version del plugin
        update_option('sah_plugin_version', SAH_PLUGIN_VERSION);

        // Limpiar rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Crear tablas del plugin
     *
     * @since 1.2.0
     */
    private static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // Tabla de configuracion
        $table_config = $wpdb->prefix . "hotels_config";
        $sql_config = "CREATE TABLE $table_config (
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

        dbDelta($sql_config);

        // Tabla de transacciones
        $table_transactions = $wpdb->prefix . "hotels_transactions";
        $sql_transactions = "CREATE TABLE $table_transactions (
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

        dbDelta($sql_transactions);

        // Tabla de logs
        $table_logs = $wpdb->prefix . "hotels_logs";
        $sql_logs = "CREATE TABLE $table_logs (
            `id` bigint(20) NOT NULL AUTO_INCREMENT,
            `log_date` datetime NOT NULL,
            `log_level` enum('ERROR','INFO','WARNING') NOT NULL DEFAULT 'INFO',
            `invoice_type` varchar(10) CHARACTER SET utf8 NOT NULL DEFAULT 'SYS',
            `username` varchar(60) CHARACTER SET utf8 NOT NULL,
            `action` varchar(255) CHARACTER SET utf8 NOT NULL,
            `response` text CHARACTER SET utf8 NULL,
            `transaction_id` varchar(256) CHARACTER SET utf8 NULL,
            `amount` varchar(50) CHARACTER SET utf8 NULL,
            `cae` varchar(50) CHARACTER SET utf8 NULL,
            `error_code` varchar(50) CHARACTER SET utf8 NULL,
            `ip_address` varchar(45) CHARACTER SET utf8 NULL,
            PRIMARY KEY (`id`),
            KEY `idx_log_date` (`log_date`),
            KEY `idx_log_level` (`log_level`),
            KEY `idx_invoice_type` (`invoice_type`),
            KEY `idx_transaction_id` (`transaction_id`)
        ) $charset_collate;";

        dbDelta($sql_logs);
    }

    /**
     * Ejecutar migraciones para actualizar tablas existentes
     *
     * @since 1.2.0
     */
    private static function run_migrations() {
        global $wpdb;

        $table_config = $wpdb->prefix . "hotels_config";
        $table_transactions = $wpdb->prefix . "hotels_transactions";

        // Migraciones para wp_hotels_config
        $config_migrations = array(
            'afip_environment' => "ADD COLUMN `afip_environment` enum('prod','test') DEFAULT 'prod' NOT NULL",
            'cert_prod' => "ADD COLUMN `cert_prod` varchar(256) DEFAULT 'facturacion2025.pem'",
            'key_prod' => "ADD COLUMN `key_prod` varchar(256) DEFAULT 'MiClavePrivada.key'",
            'cuit_prod' => "ADD COLUMN `cuit_prod` varchar(20) DEFAULT '30718446976'",
            'cert_test' => "ADD COLUMN `cert_test` varchar(256) DEFAULT 'certificado_homo.pem'",
            'key_test' => "ADD COLUMN `key_test` varchar(256) DEFAULT 'MiClavePrivada_homo.key'",
            'cuit_test' => "ADD COLUMN `cuit_test` varchar(20) DEFAULT '20000000001'",
        );

        self::apply_migrations($table_config, $config_migrations);

        // Migraciones para wp_hotels_transactions
        $transaction_migrations = array(
            'transactionType' => "ADD COLUMN `transactionType` varchar(50) DEFAULT 'credit' AFTER `transactionDateTime`",
            'invoiceUrl' => "ADD COLUMN `invoiceUrl` varchar(512) NULL",
        );

        self::apply_migrations($table_transactions, $transaction_migrations);

        // Actualizar registros sin transactionType
        $wpdb->query(
            "UPDATE {$table_transactions} SET transactionType = 'credit' WHERE transactionType IS NULL OR transactionType = ''"
        );
    }

    /**
     * Aplicar migraciones a una tabla
     *
     * @param string $table Nombre de la tabla
     * @param array $migrations Array de migraciones
     * @since 1.2.0
     */
    private static function apply_migrations($table, $migrations) {
        global $wpdb;

        foreach ($migrations as $column => $sql) {
            $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$table} LIKE '{$column}'");
            if (empty($column_exists)) {
                $wpdb->query("ALTER TABLE {$table} {$sql}");
            }
        }
    }

    /**
     * Establecer opciones por defecto
     *
     * @since 1.2.0
     */
    private static function set_default_options() {
        // Agregar opciones por defecto si no existen
        if (get_option('sah_plugin_version') === false) {
            add_option('sah_plugin_version', SAH_PLUGIN_VERSION);
        }
    }
}
