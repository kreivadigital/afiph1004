<?php
/*
Plugin Name: Services API Hotel
Plugin URI: #
Description: Servicios API de Hotel
Version: 1.1
Author: Osward Pacheco
Author URI: https://github.com/OswardJr
License: GPL
*/
// function to create the DB / Options / Defaults					
function ss_options_install() {

    global $wpdb;

    $table_name = $wpdb->prefix . "hotels_config";
    $charset_collate = $wpdb->get_charset_collate();
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
            PRIMARY KEY (`id`)
          ) $charset_collate; ";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta($sql);

    $table_name_transactions = $wpdb->prefix . "hotels_transactions";
    $sql2 = "CREATE TABLE $table_name_transactions (
            `id` int(10) NOT NULL AUTO_INCREMENT,
            `propertyID` varchar(256) CHARACTER SET utf8 NOT NULL,
            `transactionID` varchar(256) CHARACTER SET utf8 NOT NULL,
            `reservationID` varchar(256) CHARACTER SET utf8 NOT NULL,
            `guestID` varchar(256) CHARACTER SET utf8 NOT NULL,
            `transactionDateTime` DATETIME NOT NULL,
            `completeName` varchar(256) CHARACTER SET utf8 NOT NULL,
            `description` varchar(256) CHARACTER SET utf8 NOT NULL,
            `passportNumber` varchar(256) CHARACTER SET utf8 NULL,
            `amount` varchar(256) CHARACTER SET utf8 NOT NULL,
            `currency` varchar(256) CHARACTER SET utf8 NULL,
            `country` varchar(256) CHARACTER SET utf8 NOT NULL,
            `city` varchar(256) CHARACTER SET utf8 NULL,
            `address` longtext CHARACTER SET utf8 NULL,
            PRIMARY KEY (`id`)
          ) $charset_collate; ";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta($sql2);
}

function pl_deactivation()
{
    global $wpdb;
    $sql = 'DROP TABLE '.$wpdb->prefix.'hotels_config;';
    $wpdb->get_results($sql);

	$sql2 = 'DROP TABLE '.$wpdb->prefix.'hotels_transactions;';
    $wpdb->get_results($sql2);
}

// run the install scripts upon plugin activation
register_activation_hook(__FILE__, 'ss_options_install');
register_deactivation_hook(__FILE__, 'pl_deactivation');

define('ROOTDIR', plugin_dir_path(__FILE__));
define('baseURLN', 'datacita');

//php
require_once(ROOTDIR . '/php/config_init/config-list.php');
require_once(ROOTDIR . '/php/config_init/config-create.php');
require_once(ROOTDIR . '/php/config_init/config-update.php');
require_once(ROOTDIR . '/php/varios/functions.php');
require_once(ROOTDIR . '/php/varios/panel_admin.php');
require_once(ROOTDIR . '/php/consultas/main_global.php');
require_once(ROOTDIR . '/php/config_init/transactions-list.php');
require_once(ROOTDIR . '/php/config_init/transactions-list_dev.php');

//menu items

add_action('admin_menu','config_modifymenu');
function config_modifymenu() {
	
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
	add_menu_page('Hotel API', //page title
	'Hotel API', //menu title
	'manage_options', //capabilities
	'config_list', //menu slug
	'config_list' //function
	);

	//this is a submenu
	add_submenu_page('null', //parent slug
	'Añadir configuración', //page title
	'Añadir configuración', //menu title
	'manage_options', //capability
	'config_create', //menu slug
	'config_create'); //function

	//this submenu is HIDDEN, however, we need to add it anyways
	add_submenu_page(null, //parent slug
	'Act configuración', //page title
	'Actualizar configuración', //menu title
	'manage_options', //capability
	'config_update', //menu slug
	'config_update'); //function
    
	//this submenu is HIDDEN, however, we need to add it anyways
	add_submenu_page('config_list', //parent slug
	'Transacciones', //page title
	'Listado de Transacciones', //menu title
	'manage_options', //capability
	'transactions_list', //menu slug
	'transactions_list'); //function
	
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

?>
