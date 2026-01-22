<?php

/**
 * Main Global - Funciones principales de facturacion AFIP
 *
 * @package ServicesAPIHotel
 * @since 1.0.0
 */

// error_reporting(true);
// error_reporting(E_ALL);
// ini_set('display_errors', '1');

include plugin_dir_path(__DIR__) . '../vendor/autoload.php';

//include librery qrcode
include plugin_dir_path(__DIR__) . "../vendor/phpqrcode/qrlib.php";

// Mapeo de paises ISO -> AFIP para Factura Tipo T
require_once __DIR__ . '/mapeo_iso_afip_corregido.php';

// reference the Dompdf namespace
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Configuracion AFIP dinamica
 * Obtiene las credenciales segun el ambiente seleccionado (prod/test)
 */
$afip_config = function_exists('get_afip_config') ? get_afip_config() : null;

// Variables AFIP - Usar configuracion dinamica si esta disponible
if ($afip_config) {
    define("WSDL", "../afip/wsaa.wsdl");
    define("CERT", $afip_config['cert_path']);
    define("PRIVATEKEY", $afip_config['key_path']);
    define("URL", $afip_config['wsaa_url']);
    define("CUIT", $afip_config['cuit']);

    // URLs de servicios AFIP
    if (!defined('WSFE_URL')) {
        define("WSFE_URL", $afip_config['wsfe_url']);
    }
    if (!defined('WSCT_URL')) {
        define("WSCT_URL", $afip_config['wsct_url']);
    }
} else {
    // Fallback a valores por defecto (produccion)
    define("WSDL", "../afip/wsaa.wsdl");
    define("CERT", "../afip/cert/facturacion2025.pem");
    define("PRIVATEKEY", "../afip/cert/MiClavePrivada.key");
    define("URL", "https://wsaa.afip.gov.ar/ws/services/LoginCms");
    define("CUIT", "30718446976");
}

// Configuracion de proxy (si es necesario)
define("PASSPHRASE", "");
define("PROXY_HOST", "10.20.152.112");
define("PROXY_PORT", "80");

// URLs dinamicas basadas en la ubicacion del plugin
$plugin_base_url = plugin_dir_url(dirname(dirname(__FILE__)));
define("URL_WEB_FILES", $plugin_base_url . 'php/invoicesxml/');
define("URL_WEB_FILES_PDF", $plugin_base_url . 'php/invoicespdf/');
define("URL_WEB_FILES_QR", $plugin_base_url);

function ajax_foo_handler()
{

    global $wpdb, $table_name, $table_name_transactions, $access_token_global;

    if (isset($_POST['init'])) { //generar despacho

        $sql = "SELECT api_endpoint,version,client_id,client_secret,redirect_url FROM $table_name ";
        $result = $wpdb->get_results($sql) or die(mysql_error());

        foreach ($result  as $key => $row) {
            // each column in your row will be accessible like this
            $api_endpoint =  $row->api_endpoint;
            $version =  $row->version;
            $client_id =  $row->client_id;
            $client_secret =  $row->client_secret;
            $redirect_url =  $row->redirect_url;
        }

        $url_access = AUTH_URL . "?" . "&response_type=code" . "&client_id=" . $client_id . "&redirect_uri=" . $redirect_url;

        wp_send_json_success($url_access);
    }

    if (isset($_POST['generate_token'])) { //generar despacho

        $code_auth = $_POST['code_auth'];

        wp_send_json_success($code_auth);
    }

    if (isset($_POST['imp_transac'])) { //generar despacho

        $data_fetch_import = fetch_import($_POST['resultsFrom'], $_POST['resultsTo'], $_POST['reservationID'], $_POST['met_pago']);
        wp_send_json_success($data_fetch_import);
    }

    if (isset($_POST['imp_transac_date_today'])) { //generar despacho

        $datenow = date('Y-m-d');

        $data_fetch_import = getTransactionsByDate($access_token_global, $datenow);
        $data_fetch_guest = getDataByGuest();

        wp_send_json_success(['data' => $data_fetch_import, 'url_search' => CALLBACK_URL]);
    }

    if (isset($_POST['act_values_transactions'])) { //generar despacho

        $datenow = date('Y-m-d');

        $data_fetch_import = getTransactionsByDate($access_token_global, $datenow);
        $data_fetch_guest = getDataByGuest();

        wp_send_json_success(['data' => GUEST_URL, 'url_search' => CALLBACK_URL]);
    }

    if (isset($_POST['init_load_records'])) { //generar despacho

        $data_fetch_import = getTransactions($access_token_global);
        // $data_fetch_guest = getDataByGuest();
        wp_send_json_success(['data' => GUEST_URL, 'url_search' => CALLBACK_URL]);
    }

    if (isset($_POST['update_amount'])) {

        $transaction_id = $_POST['transaction_id'];
        $new_amount = $_POST['new_amount'];

        // Obtener monto anterior para el log
        $old_amount = $wpdb->get_var($wpdb->prepare(
            "SELECT amount FROM {$table_name_transactions} WHERE id = %d",
            $transaction_id
        ));

        $update_monto_result = actualizar_monto_por_id($transaction_id, $new_amount);

        if ($update_monto_result === false) {
            // Log error
            SAH_Logger::system(
                SAH_Logger::ERROR,
                'amount_update',
                'Error al actualizar monto: ' . $wpdb->last_error,
                array('transaction_id' => $transaction_id, 'amount' => $new_amount),
                array('transaction_id' => $transaction_id, 'old_amount' => $old_amount, 'new_amount' => $new_amount),
                array('error' => $wpdb->last_error)
            );

            wp_send_json_error([
                'message' => 'Error al actualizar en la base de datos.',
                'error_details' => $wpdb->last_error // Solo para depuración, no para usuario final
            ]);
        } elseif ($update_monto_result === 0) {
            wp_send_json_success([
                'message' => 'No se realizaron cambios.',
                'rows_affected' => $update_monto_result
            ]);
        } else {
            // Log exito
            SAH_Logger::system(
                SAH_Logger::INFO,
                'amount_update',
                "Monto actualizado de {$old_amount} a {$new_amount}",
                array('transaction_id' => $transaction_id, 'amount' => $new_amount),
                array('transaction_id' => $transaction_id, 'old_amount' => $old_amount),
                array('new_amount' => $new_amount, 'rows_affected' => $update_monto_result)
            );

            wp_send_json_success([
                'message' => 'Monto actualizado.',
                'rows_affected' => $update_monto_result
            ]);
        }

        wp_die();
    }

    if (isset($_POST['sync_transaction'])) {

        $transaction_id = intval($_POST['transaction_id']);

        // Obtener datos de la transaccion
        $transaction = $wpdb->get_row($wpdb->prepare(
            "SELECT id, propertyID, guestID, passportNumber FROM {$table_name_transactions} WHERE id = %d",
            $transaction_id
        ));

        if (!$transaction) {
            wp_send_json_error(['message' => 'Transaccion no encontrada.']);
            wp_die();
        }

        // Obtener datos del guest desde CloudBeds
        $data_guest = getGuest($transaction->propertyID, $transaction->guestID);

        if (!$data_guest || !isset($data_guest->data)) {
            wp_send_json_error(['message' => 'Error al obtener datos de CloudBeds.']);
            wp_die();
        }

        // Extraer passportNumber de customFields
        $pass_dni = 'NULO';
        if (isset($data_guest->data->customFields) && is_array($data_guest->data->customFields) && count($data_guest->data->customFields) > 0) {
            $pass_dni = $data_guest->data->customFields[0]->customFieldValue ?? 'NULO';
        }

        // Actualizar la transaccion en la DB
        $updated = $wpdb->update(
            $table_name_transactions,
            array(
                'passportNumber' => $pass_dni,
                'country' => $data_guest->data->country ?? '',
                'city' => $data_guest->data->city ?? '',
                'address' => $data_guest->data->address ?? '',
                'completeName' => trim(($data_guest->data->firstName ?? '') . ' ' . ($data_guest->data->lastName ?? ''))
            ),
            array('id' => $transaction_id),
            array('%s', '%s', '%s', '%s', '%s'),
            array('%d')
        );

        if ($updated === false) {
            wp_send_json_error(['message' => 'Error al actualizar la base de datos.']);
        } else {
            wp_send_json_success(['message' => 'Transaccion sincronizada correctamente.']);
        }

        wp_die();
    }

    if (isset($_POST['gen_facturar'])) { //generar despacho

        $code_transaction = $_POST['code_transaction'];
        $type_gen = $_POST['type_gen'];

        ini_set("soap.wsdl_cache_enabled", "0");
        // if (!file_exists(CERT)) {exit("Failed to open ".CERT."\n");}
        // if (!file_exists(PRIVATEKEY)) {exit("Failed to open ".PRIVATEKEY."\n");}
        // if (!file_exists(WSDL)) {exit("Failed to open ".WSDL."\n");}
        // //if ( $argc < 2 ) {ShowUsage($argv[0]); exit();}
        date_default_timezone_set('America/Argentina/Buenos_Aires');
        $hoy = date('Y-m-d H:i:s');
        $SERVICE = "wsfe";

        $xml_token = '';
        $xml_sign = '';

        if (file_exists(plugin_dir_path(__FILE__) . "../afip/TRA_WSFE.xml")) {
            $TA = simplexml_load_file(plugin_dir_path(__FILE__) . "../afip/TRA_WSFE.xml");
            $inicio = substr($TA->header->generationTime, 0, -10);
            $ini = str_replace('T', ' ', $inicio);
            $expira = substr($TA->header->expirationTime, 0, -10);
            $ec = str_replace('T', ' ', $expira);

            if ($hoy >= $ec) { //solicitamos nuevo token y sign

                CreateTRA($SERVICE);
                $CMS = SignTRA();
                $TARES = CallWSAA(base64_decode($CMS));
                if (!file_put_contents(plugin_dir_path(__FILE__) . "../afip/TRA_WSFE.xml", $TARES)) {
                    exit();
                }
                $xml = simplexml_load_string($TARES) or die("Error: Cannot create object");
                $xml_token = $xml->credentials->token;
                $xml_sign = $xml->credentials->sign;
            } else { //usamos sign y token generados anteriormente

                $xml_token = $TA->credentials->token;
                $xml_sign = $TA->credentials->sign;
            }
        } else { //solicitud primera vez

            CreateTRA($SERVICE);
            $CMS = SignTRA();
            $TARES = CallWSAA(base64_decode($CMS));
            if (!file_put_contents(plugin_dir_path(__FILE__) . "../afip/TRA_WSFE.xml", $TARES)) {
                exit();
            }
            $xml = simplexml_load_string($TARES) or die("Error: Cannot create object");
            $xml_token = $xml->credentials->token;
            $xml_sign = $xml->credentials->sign;
        }

        $sql_res = "SELECT id,propertyID,reservationID,transactionID,guestID,amount,invoiceUrl FROM $table_name_transactions WHERE id=" . $code_transaction;
        $res_t = $wpdb->get_results($sql_res) or die(mysql_error());

        foreach ($res_t  as $key => $row) {

            // each column in your row will be accessible like this
            $code = $row->id;
            $propertyID =  $row->propertyID;
            $reservationID =  $row->reservationID;
            $transactionID =  $row->transactionID;
            $amount =  $row->amount;
            $guestID =  $row->guestID;
            $invoiceUrl =  $row->invoiceUrl;
        }

        if ($invoiceUrl != null) {

            //generate pdf
            $generate_pdf = generatePDF($code, $transactionID);
            $url_file_web = URL_WEB_FILES_PDF . basename($invoiceUrl);
            $data_send_pdf = sendPDF($code, $propertyID, $reservationID, $generate_pdf);

            //LOG ID PDF
            file_put_contents(plugin_dir_path(__FILE__) . "../invoicespdf/log_pdf.json", "transactionID:" . $transactionID . "___ Nombre del PDF: " . $generate_pdf . "_____ ID DEL PDF: " . $data_send_pdf . PHP_EOL, FILE_APPEND | LOCK_EX);

            wp_send_json_success(['data' => 'Generado con éxito.', 'name_file' => $invoiceUrl, 'rell' => $url_file_web, 'file_id' => $data_send_pdf]);
        } else {

            if ($type_gen == 'NULL') {

                // Log inicio Factura B
                SAH_Logger::file(
                    SAH_Logger::INFO,
                    SAH_Logger::FACTURA_B,
                    'FECAESolicitar_init',
                    array('transaction_id' => $transactionID, 'amount' => $amount, 'cuit' => CUIT),
                    null,
                    'Iniciando solicitud Factura B'
                );

                //solicitar fecae afip
                $data_fecae = FECAESolicitar($xml_token, $xml_sign, CUIT, $code_transaction, $type_gen);

                //obtener el ultimo comprobante autorizado
                $plainXML = mungXML(trim($data_fecae));
                $xml_now = SimpleXML_Load_String($plainXML, 'SimpleXMLElement', LIBXML_NOCDATA);

                $last_data_recae_res = $xml_now->soap_Body->FECAESolicitarResponse[0]->FECAESolicitarResult->FeCabResp->Resultado;
                $last_data_recae_res_error = $xml_now->soap_Body->FECAESolicitarResponse[0]->FECAESolicitarResult->Errors->Err->Code;

                $json2_result = str_replace('0', '"UK"', $last_data_recae_res);

                // Inicializar mensaje de error
                $error_code = '';
                $error_msg = '';
                $observaciones = '';

                if ($json2_result == 'R') {

                    if (isset($xml_now->soap_Body->FECAESolicitarResponse[0]->FECAESolicitarResult->FeDetResp->FECAEDetResponse->Observaciones)) {
                        $obs = $xml_now->soap_Body->FECAESolicitarResponse[0]->FECAESolicitarResult->FeDetResp->FECAEDetResponse->Observaciones->Obs;
                        $observaciones = (string) $obs->Msg;
                    }

                    // Intentar obtener el error específico
                    $errors = $xml_now->soap_Body->FECAESolicitarResponse[0]->FECAESolicitarResult->Errors;

                    if ($errors && isset($errors->Err)) {
                        $err = $errors->Err;
                        $error_code = (string) $err->Code;
                        $error_msg = (string) $err->Msg;
                    }

                    //LOG ERROR XML
                    file_put_contents(plugin_dir_path(__FILE__) . "../invoicesxml/" . $transactionID . "_ERROR.xml", $data_fecae);

                    // Leer request XML para logging
                    $request_xml_b = @file_get_contents(plugin_dir_path(__FILE__) . "../invoicesxml/" . $transactionID . "_REQUEST.xml");

                    // Log Factura B rechazada
                    SAH_Logger::facturaB(
                        SAH_Logger::ERROR,
                        'FECAESolicitar',
                        "Rechazado - Error: {$error_code} - {$error_msg}",
                        array('transaction_id' => $transactionID, 'error_code' => $error_code, 'amount' => $amount),
                        array('transaction_id' => $transactionID, 'amount' => $amount),
                        array('resultado' => 'R', 'error_code' => $error_code, 'error_msg' => $error_msg, 'observaciones' => $observaciones),
                        $data_fecae,
                        $request_xml_b
                    );

                    wp_send_json_success([
                        'data' => 'error',
                        'json_res' => $json2_result,
                        'error_code' => $error_code,
                        'error_msg' => $error_msg,
                        'observaciones' => $observaciones,
                    ]);
                } elseif ($json2_result == 'A') {

                    if (file_put_contents(plugin_dir_path(__FILE__) . "../invoicesxml/" . $transactionID . ".xml", $data_fecae)) {

                        //generate pdf
                        $generate_pdf = generatePDF($code, $transactionID);
                        $url_file_web = URL_WEB_FILES_PDF . basename($generate_pdf);
                        $data_send_pdf = sendPDF($code, $propertyID, $reservationID, $generate_pdf);

                        //LOG ID PDF
                        file_put_contents(plugin_dir_path(__FILE__) . "../invoicespdf/log_pdf.json", "transactionID:" . $transactionID . "___ Nombre del PDF: " . $generate_pdf . "_____ ID DEL PDF: " . $data_send_pdf . PHP_EOL, FILE_APPEND | LOCK_EX);

                        $save_name_file = saveUrlFile($code, $generate_pdf);

                        if ($save_name_file) {
                            // Leer request XML para logging
                            $request_xml_b = @file_get_contents(plugin_dir_path(__FILE__) . "../invoicesxml/" . $transactionID . "_REQUEST.xml");

                            // Log Factura B aprobada
                            SAH_Logger::facturaB(
                                SAH_Logger::INFO,
                                'FECAESolicitar',
                                'Aprobado - PDF generado: ' . $generate_pdf,
                                array('transaction_id' => $transactionID, 'amount' => $amount),
                                array('transaction_id' => $transactionID, 'amount' => $amount),
                                array('resultado' => 'A', 'pdf' => $generate_pdf, 'pdf_id' => $data_send_pdf),
                                $data_fecae,
                                $request_xml_b
                            );

                            wp_send_json_success(['data' => 'Generado con éxito.', 'name_file' => $generate_pdf, 'rell' => $url_file_web, 'file_id' => $data_send_pdf]);
                        } else {
                            wp_send_json_error(['data' => 'error.', 'name_file' => $generate_pdf, 'rell' => $url_file_web]);
                        }
                    } else {
                        wp_send_json_success(['data' => 'Generado con éxito.', 'name_file' => 'error', 'rell' => 'https://google.com']);
                    }
                } else {
                    //LOG ID PDF ERROR
                    file_put_contents(plugin_dir_path(__FILE__) . "../invoicespdf/log_pdf_error.json", "transactionID:" . $transactionID . "___ Data AFIP: " . $data_fecae . PHP_EOL, FILE_APPEND | LOCK_EX);

                    wp_send_json_error(['data' => 'error.', 'data_afip' => $data_fecae]);
                }
            } else {

                //solicitar fecae afip con fecha actual
                $data_fecae_now = FECAESolicitar($xml_token, $xml_sign, CUIT, $code_transaction, $type_gen);

                //obtener el ultimo comprobante autorizado
                $plainXML_rep = mungXML(trim($data_fecae_now));
                $xml_now_rep = SimpleXML_Load_String($plainXML_rep, 'SimpleXMLElement', LIBXML_NOCDATA);

                $last_data_recae_res_rep = $xml_now_rep->soap_Body->FECAESolicitarResponse[0]->FECAESolicitarResult->FeCabResp->Resultado;
                $last_data_recae_res_error_rep = $xml_now_rep->soap_Body->FECAESolicitarResponse[0]->FECAESolicitarResult->Errors->Err->Code;

                $json2_result_rep = str_replace('0', '"UK"', $last_data_recae_res_rep);

                if ($json2_result_rep == 'A') {

                    if (file_put_contents(plugin_dir_path(__FILE__) . "../invoicesxml/" . $transactionID . ".xml", $data_fecae_now)) {

                        //generate pdf
                        $generate_pdf_now = generatePDF($code, $transactionID);
                        $url_file_web_now = URL_WEB_FILES_PDF . basename($generate_pdf_now);
                        $data_send_pdf_now = sendPDF($code, $propertyID, $reservationID, $generate_pdf_now);

                        //LOG ID PDF
                        file_put_contents(plugin_dir_path(__FILE__) . "../invoicespdf/log_pdf.json", "transactionID:" . $transactionID . "___ Nombre del PDF: " . $generate_pdf_now . "_____ ID DEL PDF: " . $data_send_pdf_now . PHP_EOL, FILE_APPEND | LOCK_EX);

                        $save_name_file_now = saveUrlFile($code, $generate_pdf_now);

                        if ($save_name_file_now) {
                            wp_send_json_success(['data' => 'Generado con éxito.', 'name_file' => $generate_pdf_now, 'rell' => $url_file_web_now, 'file_id' => $data_send_pdf_now]);
                        } else {
                            wp_send_json_error(['data' => 'error.', 'name_file' => $generate_pdf_now, 'rell' => $url_file_web_now]);
                        }
                    } else {
                        wp_send_json_success(['data' => 'Generado con éxito.', 'name_file' => 'error', 'rell' => 'https://google.com']);
                    }
                } else {

                    //LOG ID PDF ERROR
                    file_put_contents(plugin_dir_path(__FILE__) . "../invoicespdf/log_pdf_error.json", "transactionID:" . $transactionID . "___ Data AFIP: " . $data_fecae_now . PHP_EOL, FILE_APPEND | LOCK_EX);

                    wp_send_json_success(['data' => 'error', 'json_res' => $json2_result_rep]);
                }
            }
        }
    }

    if (isset($_POST['gen_facturar_tipo_t'])) { //generar despacho

        $code_transaction = $_POST['code_transaction'];
        $type_gen = $_POST['type_gen'];

        ini_set("soap.wsdl_cache_enabled", "0");
        // if (!file_exists(CERT)) {exit("Failed to open ".CERT."\n");}
        // if (!file_exists(PRIVATEKEY)) {exit("Failed to open ".PRIVATEKEY."\n");}
        // if (!file_exists(WSDL)) {exit("Failed to open ".WSDL."\n");}
        // //if ( $argc < 2 ) {ShowUsage($argv[0]); exit();}
        date_default_timezone_set('America/Argentina/Buenos_Aires');
        $hoy = date('Y-m-d H:i:s');

        $xml_token = '';
        $xml_sign = '';

        $SERVICE = "wsct";


        if (file_exists(plugin_dir_path(__FILE__) . "../afip/TRA_WSCT.xml")) {
            $TA = simplexml_load_file(plugin_dir_path(__FILE__) . "../afip/TRA_WSCT.xml");
            $inicio = substr($TA->header->generationTime, 0, -10);
            $ini = str_replace('T', ' ', $inicio);
            $expira = substr($TA->header->expirationTime, 0, -10);
            $ec = str_replace('T', ' ', $expira);

            if ($hoy >= $ec) { //solicitamos nuevo token y sign

                CreateTRATipoT($SERVICE);
                $CMS = SignTRATipoT();
                $TARES = CallWSAA(base64_decode($CMS));
                if (!file_put_contents(plugin_dir_path(__FILE__) . "../afip/TRA_WSCT.xml", $TARES)) {
                    exit();
                }
                $xml = simplexml_load_string($TARES) or die("Error: Cannot create object");
                $xml_token = $xml->credentials->token;
                $xml_sign = $xml->credentials->sign;
            } else { //usamos sign y token generados anteriormente

                $xml_token = $TA->credentials->token;
                $xml_sign = $TA->credentials->sign;
            }
        } else { //solicitud primera vez

            CreateTRATipoT($SERVICE);
            $CMS = SignTRATipoT();
            $TARES = CallWSAA(base64_decode($CMS));
            if (!file_put_contents(plugin_dir_path(__FILE__) . "../afip/TRA_WSCT.xml", $TARES)) {
                exit();
            }
            $xml = simplexml_load_string($TARES) or die("Error: Cannot create object");
            $xml_token = $xml->credentials->token;
            $xml_sign = $xml->credentials->sign;
        }

        $sql_res = "SELECT id,propertyID,reservationID,transactionID,guestID,amount,invoiceUrl FROM $table_name_transactions WHERE id=" . $code_transaction;
        $res_t = $wpdb->get_results($sql_res) or die(mysql_error());

        foreach ($res_t  as $key => $row) {

            // each column in your row will be accessible like this
            $code = $row->id;
            $propertyID =  $row->propertyID;
            $reservationID =  $row->reservationID;
            $transactionID =  $row->transactionID;
            $amount =  $row->amount;
            $guestID =  $row->guestID;
            $invoiceUrl =  $row->invoiceUrl;
        }

        if ($invoiceUrl != null) {

            //generate pdf
            $generate_pdf = generatePDF($code, $transactionID);
            $url_file_web = URL_WEB_FILES_PDF . basename($invoiceUrl);
            $data_send_pdf = sendPDF($code, $propertyID, $reservationID, $generate_pdf);

            //LOG ID PDF
            file_put_contents(plugin_dir_path(__FILE__) . "../invoicespdf/log_pdf.json", "transactionID:" . $transactionID . "___ Nombre del PDF: " . $generate_pdf . "_____ ID DEL PDF: " . $data_send_pdf . PHP_EOL, FILE_APPEND | LOCK_EX);

            wp_send_json_success(['data' => 'Generado con éxito.', 'name_file' => $invoiceUrl, 'rell' => $url_file_web, 'file_id' => $data_send_pdf]);
        } else {

            // Log inicio Factura T
            SAH_Logger::file(
                SAH_Logger::INFO,
                SAH_Logger::FACTURA_T,
                'FECAESolicitarTipoT_init',
                array('transaction_id' => $transactionID, 'amount' => $amount, 'cuit' => CUIT),
                null,
                'Iniciando solicitud Factura T'
            );

            // Solicitar autorización a WSCT (AFIP)
            $data_wsct = FECAESolicitarTipoT($xml_token, $xml_sign, CUIT, $code_transaction, $type_gen);

            // Log respuesta cruda para diagnóstico
            SAH_Logger::file(
                SAH_Logger::INFO,
                SAH_Logger::FACTURA_T,
                'FECAESolicitarTipoT_raw_response',
                array(
                    'transaction_id' => $transactionID,
                    'response_length' => strlen($data_wsct),
                    'response_preview' => substr($data_wsct, 0, 300)
                ),
                null,
                'Respuesta cruda de FECAESolicitarTipoT'
            );

            // Parsear respuesta WSCT (diferente estructura que WSFE)
            $resultado = '';
            $cae = '';
            $fechaVencimientoCae = '';
            $error_code = '';
            $error_msg = '';

            // Extraer resultado de la respuesta WSCT usando regex
            if (preg_match('/<resultado>([AR])<\/resultado>/i', $data_wsct, $matchRes)) {
                $resultado = $matchRes[1];
            }

            if (preg_match('/<cae>(\d+)<\/cae>/i', $data_wsct, $matchCae)) {
                $cae = $matchCae[1];
            }

            if (preg_match('/<fechaVencimientoCae>([^<]+)<\/fechaVencimientoCae>/i', $data_wsct, $matchFecha)) {
                $fechaVencimientoCae = $matchFecha[1];
            }

            // Extraer errores si existen
            if (preg_match('/<codigo>(\d+)<\/codigo>.*?<descripcion>([^<]+)<\/descripcion>/s', $data_wsct, $matchErr)) {
                $error_code = $matchErr[1];
                $error_msg = $matchErr[2];
            }

            if ($resultado == 'A') {
                // Aprobado - guardar XML y generar PDF
                if (file_put_contents(plugin_dir_path(__FILE__) . "../invoicesxmlt/" . $transactionID . ".xml", $data_wsct)) {

                    // Generar PDF para Tipo T
                    $generate_pdf = generatePDFTipoT($code, $transactionID);
                    $url_file_web = URL_WEB_FILES_PDF . basename($generate_pdf);
                    $data_send_pdf = sendPDF($code, $propertyID, $reservationID, $generate_pdf);

                    // LOG ID PDF
                    file_put_contents(plugin_dir_path(__FILE__) . "../invoicespdft/log_pdf.json", "transactionID:" . $transactionID . "___ Nombre del PDF: " . $generate_pdf . "_____ ID DEL PDF: " . $data_send_pdf . PHP_EOL, FILE_APPEND | LOCK_EX);

                    $save_name_file = saveUrlFile($code, $generate_pdf);

                    if ($save_name_file) {
                        // Leer request XML para logging
                        $request_xml_t = @file_get_contents(plugin_dir_path(__FILE__) . "../invoicesxmlt/" . $transactionID . "_REQUEST.xml");

                        // Log Factura T aprobada
                        SAH_Logger::facturaT(
                            SAH_Logger::INFO,
                            'FECAESolicitarTipoT',
                            "Aprobado - CAE: {$cae}",
                            array('transaction_id' => $transactionID, 'amount' => $amount, 'cae' => $cae),
                            array('transaction_id' => $transactionID, 'amount' => $amount),
                            array('resultado' => 'A', 'cae' => $cae, 'fecha_vto' => $fechaVencimientoCae, 'pdf' => $generate_pdf),
                            $data_wsct,
                            $request_xml_t
                        );

                        wp_send_json_success([
                            'data' => 'Generado con éxito.',
                            'name_file' => $generate_pdf,
                            'rell' => $url_file_web,
                            'file_id' => $data_send_pdf,
                            'cae' => $cae,
                            'fecha_vto' => $fechaVencimientoCae
                        ]);
                    } else {
                        wp_send_json_error(['data' => 'error.', 'name_file' => $generate_pdf, 'rell' => $url_file_web]);
                    }
                } else {
                    wp_send_json_error(['data' => 'Error al guardar XML.']);
                }
            } elseif ($resultado == 'R') {
                // Rechazado - guardar log de error
                file_put_contents(plugin_dir_path(__FILE__) . "../invoicesxmlt/" . $transactionID . "_ERROR.xml", $data_wsct);

                // Leer request XML para logging
                $request_xml_t = @file_get_contents(plugin_dir_path(__FILE__) . "../invoicesxmlt/" . $transactionID . "_REQUEST.xml");

                // Log Factura T rechazada
                SAH_Logger::facturaT(
                    SAH_Logger::ERROR,
                    'FECAESolicitarTipoT',
                    "Rechazado - Error: {$error_code} - {$error_msg}",
                    array('transaction_id' => $transactionID, 'error_code' => $error_code, 'amount' => $amount),
                    array('transaction_id' => $transactionID, 'amount' => $amount),
                    array('resultado' => 'R', 'error_code' => $error_code, 'error_msg' => $error_msg),
                    $data_wsct,
                    $request_xml_t
                );

                wp_send_json_success([
                    'data' => 'error',
                    'json_res' => 'R',
                    'error_code' => $error_code,
                    'error_msg' => $error_msg,
                ]);
            } else {
                // Error en la respuesta o respuesta vacía/malformada
                file_put_contents(plugin_dir_path(__FILE__) . "../invoicespdft/log_pdf_error.json", "transactionID:" . $transactionID . "___ Data AFIP: " . $data_wsct . PHP_EOL, FILE_APPEND | LOCK_EX);

                // Leer request XML para logging
                $request_xml_t = @file_get_contents(plugin_dir_path(__FILE__) . "../invoicesxmlt/" . $transactionID . "_REQUEST.xml");

                // Log error de respuesta
                SAH_Logger::facturaT(
                    SAH_Logger::ERROR,
                    'FECAESolicitarTipoT',
                    "Respuesta sin resultado válido (ni A ni R)",
                    array('transaction_id' => $transactionID, 'amount' => $amount, 'resultado' => $resultado),
                    array('transaction_id' => $transactionID, 'amount' => $amount),
                    array('resultado' => $resultado, 'error_code' => $error_code, 'error_msg' => $error_msg, 'response_preview' => substr($data_wsct, 0, 500)),
                    $data_wsct,
                    $request_xml_t
                );

                wp_send_json_error(['data' => 'error', 'data_afip' => $data_wsct, 'error_code' => $error_code, 'error_msg' => $error_msg]);
            }
        }
    }
}
add_action('wp_ajax_foo', 'ajax_foo_handler');        // for authenticated users


function getToken($code)
{

    global $client_id_global, $client_secret_global;

    $content = "grant_type=authorization_code&code=" . $code . "&redirect_uri=" . CALLBACK_URL;
    $authorization = base64_encode("$client_id_global:$client_secret_global");

    $header = array("Authorization: Basic {$authorization}", "Content-Type: application/x-www-form-urlencoded");

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => ACCESS_TOKEN_URL,
        CURLOPT_HTTPHEADER => $header,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $content
    ));

    $response = curl_exec($curl);
    curl_close($curl);

    return json_decode($response);
}

function refreshToken($refresh_token)
{
    global $client_id_global, $client_secret_global;

    $content = "grant_type=refresh_token&refresh_token=" . $refresh_token . "&redirect_uri=" . CALLBACK_URL;
    $authorization = base64_encode("$client_id_global:$client_secret_global");

    $header = array("Authorization: Basic {$authorization}", "Content-Type: application/x-www-form-urlencoded");

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => ACCESS_TOKEN_URL,
        CURLOPT_HTTPHEADER => $header,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $content
    ));

    $response = curl_exec($curl);
    curl_close($curl);

    return json_decode($response);
}

function getTransactions($access_token)
{

    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => TRANSACTIONS_URL,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => array(
            'Authorization: Bearer ' . $access_token
        ),
    ));

    $response = curl_exec($curl);

    curl_close($curl);

    $data_res = json_decode($response);
    $verifyData = saveTransactions($data_res);

    return $verifyData;
}

function getTransactionsByDate($access_token, $datefrom)
{

    $content = "resultsFrom=" . $datefrom . "&resultsTo=" . $datefrom;

    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => TRANSACTIONS_URL . '?' . $content,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_POSTFIELDS => 'status=confirmed',
        CURLOPT_HTTPHEADER => array(
            'Authorization: Bearer ' . $access_token
        ),
    ));

    $response = curl_exec($curl);

    curl_close($curl);

    $data_res = json_decode($response);
    $verifyData = saveTransactions($data_res);

    return $verifyData;
}

function saveTransactions($resource)
{

    global $wpdb, $table_name_transactions, $access_token_global;

    if (is_array($resource->data) && sizeof($resource->data) > 0) {
        foreach ($resource->data as $res) {

            $data_count = checkDatabase($res->transactionID);

            if ($data_count > 0) {
            } else {
                $data_insert = $wpdb->insert(
                    $table_name_transactions, //table
                    array('propertyID' => $res->propertyID, 'transactionID' => $res->transactionID, 'reservationID' => $res->reservationID, 'guestID' => $res->guestID, 'transactionDateTime' => $res->transactionDateTime, 'completeName' => $res->guestName, 'description' => $res->description, 'amount' => $res->amount, 'currency' => $res->currency, 'transactionType' => $res->transactionType), //data
                    array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s') //data format           
                );
            }
        }
    } else {
        return false;
    }

    return true;
}

function checkDatabase($transactionID)
{

    global $wpdb;

    $table_name_transactions = $wpdb->prefix . "hotels_transactions";

    $total = $wpdb->get_var("SELECT COUNT(transactionID) FROM $table_name_transactions WHERE transactionID = '$transactionID'");
    return $total;
}

function getDataByGuest()
{

    global $wpdb, $table_name_transactions, $access_token_global;

    try {

        $data_reserv = $wpdb->get_results($wpdb->prepare("SELECT * from $table_name_transactions"));
        foreach ($data_reserv as $dat_all) {

            if ($dat_all->passportNumber != null) {
            } else {

                $data_guest = getGuest($dat_all->propertyID, $dat_all->guestID);

                if (count($data_guest->data->customFields) > 0) { //SE EVALUA SI HAY NUMERO DE DOCUMENTO O NO

                    $i = 0;
                    foreach ($data_guest->data->customFields as $dat_arr) { //for para extraer el nro de dni o passport en el primer elemento
                        if ($i == 0) {
                            $pass_dni = $dat_arr->customFieldValue;
                        }
                        $i++;
                    }
                } else {
                    $pass_dni = 'NULO';
                }

                $data_update_reserv = $wpdb->update(
                    $table_name_transactions, //table
                    array('passportNumber' => $pass_dni, 'country' => $data_guest->data->country, 'city' => $data_guest->data->city, 'address' => $data_guest->data->address), //data
                    array('id' => $dat_all->id), //where
                    array('%s'), //data format
                    array('%s') //where format
                );
            }
        }

        return true;
    } catch (Throwable $e) {

        return false;
    }
}

function getDataByGuestOLD()
{

    global $wpdb, $table_name_transactions, $access_token_global;

    try {

        $pass_dni = '';
        $data_reserv = $wpdb->get_results($wpdb->prepare("SELECT * from $table_name_transactions"));
        foreach ($data_reserv as $dat_all) {

            if ($dat_all->passportNumber != null) {
            } else {
                $data_guest = getGuest($dat_all->propertyID, $dat_all->guestID);

                $i = 0;
                foreach ($data_guest->data->customFields as $dat_arr) { //for para extraer el nro de dni o passport en el primer elemento
                    if ($i == 0) {
                        $pass_dni = $dat_arr->customFieldValue;
                    }
                    $i++;
                }

                $data_update_reserv = $wpdb->update(
                    $table_name_transactions, //table
                    array('passportNumber' => $pass_dni, 'country' => $data_guest->data->country, 'city' => $data_guest->data->city, 'address' => $data_guest->data->address), //data
                    array('id' => $dat_all->id), //where
                    array('%s'), //data format
                    array('%s') //where format
                );
            }
        }

        return true;
    } catch (Throwable $e) {

        return false;
    }
}

function getGuest($property_id, $guest_id)
{

    global $access_token_global;

    $content = "propertyID=" . $property_id . "&guestID=" . $guest_id;

    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => GUEST_URL . '?' . $content,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_POSTFIELDS => 'status=confirmed',
        CURLOPT_HTTPHEADER => array(
            'Authorization: Bearer ' . $access_token_global
        ),
    ));

    $response = curl_exec($curl);

    curl_close($curl);

    return json_decode($response);
}

function sendPDF($code, $propertyID, $reservationID, $name_file)
{

    global $access_token_global;

    $route_file = plugin_dir_path(__FILE__) . "../invoicespdf/" . $name_file;

    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => SENDPDF_URL,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => array('reservationID' => $reservationID, 'file' => new CURLFILE($route_file), 'propertyID' => $propertyID),
        CURLOPT_HTTPHEADER => array(
            'Authorization: Bearer ' . $access_token_global
        ),
    ));

    $response = curl_exec($curl);

    curl_close($curl);

    $decode_data = json_decode($response);

    $sf = preg_replace('/[^0-9]/', '', $decode_data->data->fileID);

    if (strlen($sf) > 0) {
        return $decode_data->data->fileID;
    } else {
        return $response;
    }
}

function fetch_import($resultsFrom, $resultsTo, $reservationID, $met_pago)
{

    global $wpdb, $table_name_transactions, $access_token_global;

    $content = "resultsFrom=" . $resultsFrom . "&resultsTo=" . $resultsTo . "&reservationID=" . $reservationID;

    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => TRANSACTIONS_URL . '?' . $content,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_POSTFIELDS => 'status=confirmed',
        CURLOPT_HTTPHEADER => array(
            'Authorization: Bearer ' . $access_token_global
        ),
    ));

    $response = curl_exec($curl);

    curl_close($curl);

    $data_res = json_decode($response);
    $verifyData = saveTransactions($data_res);
    $update_data_rest = getDataByGuestImport($resultsFrom, $resultsTo, $reservationID);

    wp_send_json_success(['data' => $verifyData, 'url_search' => CALLBACK_URL . '&resultsFrom=' . $resultsFrom . '&resultsTo=' . $resultsTo . '&reservationID=' . $reservationID . '&metpago=' . $met_pago]);
}

function getDataByGuestImport($resultsFrom, $resultsTo, $reservationID)
{

    global $wpdb, $table_name_transactions, $access_token_global;

    try {

        if ($reservationID != '') {
            $data_reserv = $wpdb->get_results("SELECT id, propertyID, guestID, DATE_FORMAT(transactionDateTime, '%Y-%m-%d') from $table_name_transactions WHERE DATE_FORMAT(transactionDateTime, '%Y-%m-%d') between '$resultsFrom' and '$resultsTo' and reservationID = '$reservationID'");
        } else {
            $data_reserv = $wpdb->get_results("SELECT id, propertyID, guestID, DATE_FORMAT(transactionDateTime, '%Y-%m-%d') from $table_name_transactions WHERE DATE_FORMAT(transactionDateTime, '%Y-%m-%d') between '$resultsFrom' and '$resultsTo'");
        }

        foreach ($data_reserv as $dat_all) {

            $data_guest = getGuest($dat_all->propertyID, $dat_all->guestID);

            if (count($data_guest->data->customFields) > 0) { //SE EVALUA SI HAY NUMERO DE DOCUMENTO O NO

                $i = 0;
                foreach ($data_guest->data->customFields as $dat_arr) { //for para extraer el nro de dni o passport en el primer elemento
                    if ($i == 0) {
                        $pass_dni = $dat_arr->customFieldValue;
                    }
                    $i++;
                }
            } else {
                $pass_dni = 'NULO';
            }

            $data_update_reserv = $wpdb->update(
                $table_name_transactions, //table
                array('passportNumber' => $pass_dni, 'country' => $data_guest->data->country, 'city' => $data_guest->data->city, 'address' => $data_guest->data->address), //data
                array('id' => $dat_all->id), //where
                array('%s'), //data format
                array('%s') //where format
            );
        }

        return true;
    } catch (Throwable $e) {

        return false;
    }
}

function actualizar_monto_por_id($transaction_id, $new_amount)
{
    global $wpdb;

    // Asegúrate de que esta variable contenga el nombre correcto de tu tabla
    $table_name_transactions = $wpdb->prefix . 'hotels_transactions'; // O el nombre que uses, ej: 'mis_transacciones'

    $rows_affected = $wpdb->update(
        $table_name_transactions,
        array('amount' => $new_amount),       // Datos a actualizar
        array('id' => $transaction_id), // Condición WHERE (usando 'transactionID')
        array('%s'),                          // Formato para 'amount' (flotante)
        array('%s')                           // Formato para 'transactionID' (string, o '%d' si es número)
    );

    if ($rows_affected === false) {
        error_log('Error al actualizar: ' . $wpdb->last_error);
    }

    return $rows_affected;
}

#AQUI LAS DE FACTURACION TIPO B

#------------------------------------------------------------------------------
# You shouldn't have to change anything below this line!!!
#==============================================================================
function CreateTRA($SERVICE)
{
    $TRA = new SimpleXMLElement(
        '<?xml version="1.0" encoding="UTF-8"?>' .
            '<loginTicketRequest version="1.0">' .
            '</loginTicketRequest>'
    );
    $TRA->addChild('header');
    $TRA->header->addChild('source', 'serialNumber=CUIT 30718446976, cn=facturaciondava2025');
    $TRA->header->addChild('destination', 'cn=wsaa,o=afip,c=ar,serialNumber=CUIT 33693450239');
    $TRA->header->addChild('uniqueId', date('U'));
    $TRA->header->addChild('generationTime', date('c', date('U') - 60));
    $TRA->header->addChild('expirationTime', date('c', date('U') + 60));
    $TRA->addChild('service', $SERVICE);
    $TRA->asXML(plugin_dir_path(__FILE__) . '../afip/TRA_WSFE.xml');
}
#==============================================================================
# This functions makes the PKCS#7 signature using TRA as input file, CERT and
# PRIVATEKEY to sign. Generates an intermediate file and finally trims the 
# MIME heading leaving the final CMS required by WSAA.C:\\laragon\www\hotel\wp-content\plugins\hotels\php\afip\TRA_WSFE.xml
function SignTRA()
{
    $certificado = "file://" . plugin_dir_path(__FILE__) . "../afip/cert/certificado.crt";
    $privatekey = "file://" . plugin_dir_path(__FILE__) . "../afip/cert/MiClavePrivada.key";
    $args = array(
        'extracerts' => $certificado,
        'friendly_name' => 'My signed cert by CA certificate'
    );
    // $STA=openssl_pkcs12_export($pedido,$certificado,$privatekey, "caruso12021991", $args);  
    // $tra="../afip/cert/TRA_WSFE.xml";
    $STATUS = openssl_pkcs7_sign(
        plugin_dir_path(__FILE__) . "../afip/TRA_WSFE.xml",
        plugin_dir_path(__FILE__) . "../afip/TRA_WSFE.tmp",
        "file://" . plugin_dir_path(__FILE__) . CERT,
        array("file://" . plugin_dir_path(__FILE__) . PRIVATEKEY, PASSPHRASE),
        array(),
        !PKCS7_DETACHED
    );
    if (!$STATUS) {
        exit("ERROR generating PKCS#7 signature\n");
    }
    $inf = fopen(plugin_dir_path(__FILE__) . "../afip/TRA_WSFE.tmp", "r");
    $i = 0;
    $CMS = "";
    while (!feof($inf)) {
        $buffer = fgets($inf);
        if ($i++ >= 4) {
            $CMS .= $buffer;
        }
    }
    fclose($inf);
    #  unlink("TRA_WSFE.xml");
    unlink(plugin_dir_path(__FILE__) . "../afip/TRA_WSFE.tmp");
    return $CMS;
}
#==============================================================================
function CallWSAA($CMS)
{
    $client = new SoapClient(plugin_dir_path(__FILE__) . WSDL, array(

        'soap_version'   => SOAP_1_2,
        'location'       => URL,
        'trace'          => 1,
        'exceptions'     => 0
    ));
    $results = $client->loginCms(array('in0' => base64_encode($CMS)));
    file_put_contents(plugin_dir_path(__FILE__) . "../afip/request-loginCms.xml", $client->__getLastRequest());
    file_put_contents(plugin_dir_path(__FILE__) . "../afip/response-loginCms.xml", $client->__getLastResponse());
    if (is_soap_fault($results)) {
        exit("SOAP Fault: " . $results->faultcode . "\n" . $results->faultstring . "\n");
    }
    return $results->loginCmsReturn;
}
#==============================================================================
#AQUI LAS DE FACTURACION TIPO T

// Asegúrate de que las constantes CERT, PRIVATEKEY, PASSPHRASE, WSDL, URL estén definidas.
// Si estás en WordPress, la función plugin_dir_path() debe estar disponible.

function CreateTRATipoT($SERVICE)
{
    $TRA = new SimpleXMLElement(
        '<?xml version="1.0" encoding="UTF-8"?>' .
            '<loginTicketRequest version="1.0">' .
            '</loginTicketRequest>'
    );
    $TRA->addChild('header');
    $TRA->header->addChild('source', 'serialNumber=CUIT 30718446976, cn=facturaciondava2025');
    $TRA->header->addChild('destination', 'cn=wsaa,o=afip,c=ar,serialNumber=CUIT 33693450239');
    $TRA->header->addChild('uniqueId', date('U'));
    $TRA->header->addChild('generationTime', date('c', date('U') - 60));
    $TRA->header->addChild('expirationTime', date('c', date('U') + 60));
    $TRA->addChild('service', $SERVICE);
    $TRA->asXML(plugin_dir_path(__FILE__) . '../afip/TRA_WSCT.xml');
}

#==============================================================================
function SignTRATipoT()
{
    $certificado = "file://" . plugin_dir_path(__FILE__) . "../afip/cert/certificado.crt";
    $privatekey = "file://" . plugin_dir_path(__FILE__) . "../afip/cert/MiClavePrivada.key";
    $args = array(
        'extracerts' => $certificado,
        'friendly_name' => 'My signed cert by CA certificate'
    );
    // $STA=openssl_pkcs12_export($pedido,$certificado,$privatekey, "caruso12021991", $args);  
    // $tra="../afip/cert/TRA_WSCT.xml";
    $STATUS = openssl_pkcs7_sign(
        plugin_dir_path(__FILE__) . "../afip/TRA_WSCT.xml",
        plugin_dir_path(__FILE__) . "../afip/TRA_WSCT.tmp",
        "file://" . plugin_dir_path(__FILE__) . CERT,
        array("file://" . plugin_dir_path(__FILE__) . PRIVATEKEY, PASSPHRASE),
        array(),
        !PKCS7_DETACHED
    );
    if (!$STATUS) {
        exit("ERROR generating PKCS#7 signature\n");
    }
    $inf = fopen(plugin_dir_path(__FILE__) . "../afip/TRA_WSCT.tmp", "r");
    $i = 0;
    $CMS = "";
    while (!feof($inf)) {
        $buffer = fgets($inf);
        if ($i++ >= 4) {
            $CMS .= $buffer;
        }
    }
    fclose($inf);
    #  unlink("TRA_WSCT.xml");
    unlink(plugin_dir_path(__FILE__) . "../afip/TRA_WSCT.tmp");
    return $CMS;
}

#==============================================================================

#FIN FACTURACION TIPO T

function ShowUsage($MyPath)
{
    printf("Uso  : %s Arg#1 Arg#2\n", $MyPath);
    printf("donde: Arg#1 debe ser el service name del WS de negocio.\n");
    printf("  Ej.: %s wsfe\n", $MyPath);
}

#END CONECTORES

function FECAESolicitar($Token, $Sign, $Cuit, $code_transaction, $timenow)
{

    global $wpdb, $table_name_transactions;

    //Iniciamos la consulta a database para traer datos importantes de la factura

    $sql_res = "SELECT id,transactionID,transactionDateTime,passportNumber,amount,country FROM $table_name_transactions WHERE id=" . $code_transaction;
    $res_t = $wpdb->get_results($sql_res) or die(mysql_error());

    foreach ($res_t  as $key => $row) {
        // each column in your row will be accessible like this
        $transactionID =  $row->transactionID;
        $passportNumber =  $row->passportNumber;
        $amount = $row->amount;
        $transactionDateTime = $row->transactionDateTime;
        $country = $row->country;
    }

    $sf = preg_replace('/[^0-9]/', '', $passportNumber);

    //calculo de subtotal number_format($dat1,2,',','.')
    $monto_n = calc_neto($amount);
    $monto_neto = round($monto_n, 2);

    //calculo de iva
    $monto_i = calc_iva($monto_n);
    $monto_iva = round($monto_i, 2);

    //Date transaction format
    $newDate = intval(date('Ymd', strtotime($transactionDateTime)));

    //obtener el ultimo comprobante autorizado
    $get_last_comp_autorizado = FECompUltimoAutorizado($Token, $Sign, $Cuit);
    $plainXML = mungXML(trim($get_last_comp_autorizado));
    $xml_now = SimpleXML_Load_String($plainXML, 'SimpleXMLElement', LIBXML_NOCDATA);

    $last_comp_autorizado = intval(json_decode($xml_now->soap_Body->FECompUltimoAutorizadoResponse[0]->FECompUltimoAutorizadoResult->CbteNro, true)) + 1;

    //Iniciamos la consulta a la api FECAESolicitar

    $soapUrl = "https://servicios1.afip.gov.ar/wsfev1/service.asmx?op=FECAESolicitar";

    date_default_timezone_set('America/Argentina/Buenos_Aires');
    $date_now = intval(date('Ymd'));

    $time_nowit = '';
    if ($timenow == 'NULL') {
        $time_nowit = $newDate;
    } else {
        $time_nowit = $date_now;
    }

    $xml_post_string =
        '<?xml version="1.0" encoding="utf-8"?>
                <soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
                  <soap:Body>
                    <FECAESolicitar xmlns="http://ar.gov.afip.dif.FEV1/">
                      <Auth>
                        <Token>' . $Token . '</Token>
                        <Sign>' . $Sign . '</Sign>
                        <Cuit>' . $Cuit . '</Cuit>
                        </Auth>
                        <FeCAEReq>
                          <FeCabReq>
                            <CantReg>1</CantReg>
                            <PtoVta>2</PtoVta>
                            <CbteTipo>6</CbteTipo>
                          </FeCabReq>
                          <FeDetReq>
                            <FECAEDetRequest>
                              <Concepto>1</Concepto>';

    if ($country == 'AR') {
        if (strlen($sf) > 0) {
            $xml_post_string .=
                '<DocTipo>96</DocTipo>
                                  <DocNro>' . $sf . '</DocNro>';
        } else {
            $xml_post_string .=
                '<DocTipo>99</DocTipo>';
        }
    } else {
        if (strlen($sf) > 0) {
            $xml_post_string .=
                '<DocTipo>94</DocTipo>
                                  <DocNro>' . $sf . '</DocNro>';
        } else {
            $xml_post_string .=
                '<DocTipo>99</DocTipo>';
        }
    }

    $xml_post_string .=
        '<CbteDesde>' . $last_comp_autorizado . '</CbteDesde>
                              <CbteHasta>' . $last_comp_autorizado . '</CbteHasta>
                              <CbteFch>' . $time_nowit . '</CbteFch>
                              <ImpTotal>' . $amount . '</ImpTotal>
                              <ImpTotConc>0</ImpTotConc>
                              <ImpNeto>' . $monto_neto . '</ImpNeto>
                              <ImpOpEx>0</ImpOpEx>
                              <ImpTrib>0</ImpTrib>
                              <ImpIVA>' . $monto_iva . '</ImpIVA>
                              <FchServDesde>NULL</FchServDesde>
                              <FchServHasta>NULL</FchServHasta>
                              <FchVtoPago>NULL</FchVtoPago>
                              <MonId>PES</MonId>
                              <MonCotiz>1</MonCotiz>
                              <Iva>
                                <AlicIva>
                                  <Id>5</Id>
                                  <BaseImp>' . $monto_neto . '</BaseImp>
                                  <Importe>' . $monto_iva . '</Importe>
                                </AlicIva>
                              </Iva>
                            </FECAEDetRequest>
                          </FeDetReq>
                        </FeCAEReq>
                      </FECAESolicitar>
                    </soap:Body>
                  </soap:Envelope>';

    $headers = ["POST /wsfev1/service.asmx HTTP/1.1", "Host: servicios1.afip.gov.ar", "Content-Type: text/xml; charset=utf-8", "Content-Length: " . strlen($xml_post_string)];

    // Guardar XML de request para debug
    $request_file = plugin_dir_path(__FILE__) . '../invoicesxml/' . $transactionID . '_REQUEST.xml';
    @file_put_contents($request_file, $xml_post_string);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $soapUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $xml_post_string);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

    $respons = curl_exec($ch);
    curl_close($ch);

    return $respons;
}

/* function FECAESolicitarTipoT($Token, $Sign, $Cuit, $code_transaction, $timenow)
{
    global $wpdb, $table_name_transactions;

    // Obtener datos de la transacción desde la base de datos
    $sql_res = "SELECT id,transactionID,transactionDateTime,passportNumber,amount,country,address,description,transactionType FROM $table_name_transactions WHERE id=" . $code_transaction;
    $res_t = $wpdb->get_results($sql_res) or die(mysql_error());

    foreach ($res_t as $key => $row) {
        $transactionID = $row->transactionID;
        $passportNumber = $row->passportNumber;
        $amount = floatval($row->amount);
        $transactionDateTime = $row->transactionDateTime;
        $country = $row->country;
        $address = $row->address ?? '';
        $description = $row->description ?? '';
        $transactionType = $row->transactionType ?? '';
    }

    // Limpiar número de documento (solo números y letras)
    $nroDoc = preg_replace('/[^A-Za-z0-9]/', '', $passportNumber);

    // Detectar tipo de documento automáticamente según formato
    if (preg_match('/^\d{11}$/', $nroDoc)) {
        // Es un CUIT (11 dígitos numéricos)
        $tipoDoc = 80;
    } elseif (preg_match('/^\d{7,8}$/', $nroDoc)) {
        // Es un DNI argentino (7-8 dígitos)
        $tipoDoc = 96;
    } else {
        // Es pasaporte extranjero
        $tipoDoc = 94;
    }

    // Obtener código de país AFIP usando el mapeo ISO
    $codigoPais = obtenerCodigoAFIP($country, 298); // 298 = INDETERMINADO AMERICA como fallback

    // Domicilio del receptor (usar dirección de la reserva o texto genérico)
    $domicilio = !empty($address) ? $address : 'Sin domicilio informado';

    // ID Impositivo: 9 = No categorizado (turista extranjero sin identificación fiscal argentina)
    $idImpositivo = '9';

    // Detectar forma de pago según transactionType o description
    // Códigos WSCT: 01=Tarjeta Crédito, 02=Tarjeta Débito, 03=Cheque, 04=Transferencia, 05=Otra
    $codigoFormaPago = '05'; // Por defecto: Otra
    $descLower = strtolower($description . ' ' . $transactionType);

    if (strpos($descLower, 'crédit') !== false || strpos($descLower, 'credit') !== false) {
        $codigoFormaPago = '01'; // Tarjeta de Crédito
    } elseif (strpos($descLower, 'débit') !== false || strpos($descLower, 'debit') !== false) {
        $codigoFormaPago = '02'; // Tarjeta de Débito
    } elseif (strpos($descLower, 'cheque') !== false) {
        $codigoFormaPago = '03'; // Cheque
    } elseif (strpos($descLower, 'transfer') !== false) {
        $codigoFormaPago = '04'; // Transferencia Bancaria
    } elseif (strpos($descLower, 'efectivo') !== false || strpos($descLower, 'cash') !== false) {
        $codigoFormaPago = '05'; // Otra (efectivo se mapea a Otra en WSCT)
    }

    // --- CÁLCULOS PARA FACTURA TIPO T ---
    // En Tipo T, el IVA se "reintegra" (se devuelve al turista)
    // El monto de CloudBeds ya incluye IVA, debemos descomponerlo

    // Calcular gravado e IVA (21%)
    $monto_neto = round($amount / 1.21, 2);  // Monto sin IVA
    $monto_iva = round($amount - $monto_neto, 2);  // IVA 21%

    // importeReintegro = IVA negativo (se reintegra al turista)
    $importeReintegro = -$monto_iva;

    // importeTotal = gravado (el IVA se cancela con el reintegro)
    $importeTotal = $monto_neto;

    // importeItem = monto total del ítem incluyendo IVA
    $importeItem = $amount;

    // Formatear como strings con 2 decimales
    $gravado_str = sprintf('%.2f', $monto_neto);
    $iva_str = sprintf('%.2f', $monto_iva);
    $importeReintegro_str = sprintf('%.2f', $importeReintegro);
    $importeTotal_str = sprintf('%.2f', $importeTotal);
    $importeItem_str = sprintf('%.2f', $importeItem);

    // Próximo número de comprobante
    $numeroComprobante = FECompUltimoAutorizadoTipoT($Token, $Sign, $Cuit);

    $soapUrl = "https://serviciosjava.afip.gob.ar/wsct/CTService";
    date_default_timezone_set('America/Argentina/Buenos_Aires');

    // Usar fecha de transacción o fecha actual según parámetro
    if ($timenow == 'NULL') {
        $fechaEmision = date('Y-m-d', strtotime($transactionDateTime));
    } else {
        $fechaEmision = date('Y-m-d');
    }

    // Construcción del XML SOAP para WSCT
    $xml_post_string =
        '<?xml version="1.0" encoding="utf-8"?>' .
        '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" ' .
        'xmlns:cts="http://ar.gob.afip.wsct/CTService/">' .
        '<soapenv:Header/>' .
        '<soapenv:Body>' .
        '<cts:autorizarComprobanteRequest>' .
        '<authRequest>' .
        '<token>' . htmlspecialchars($Token, ENT_XML1, 'UTF-8') . '</token>' .
        '<sign>' . htmlspecialchars($Sign, ENT_XML1, 'UTF-8') . '</sign>' .
        '<cuitRepresentada>' . $Cuit . '</cuitRepresentada>' .
        '</authRequest>' .
        '<comprobanteRequest>' .
        '<codigoTipoComprobante>195</codigoTipoComprobante>' .
        '<numeroPuntoVenta>2</numeroPuntoVenta>' .
        '<numeroComprobante>' . $numeroComprobante . '</numeroComprobante>' .
        '<fechaEmision>' . $fechaEmision . '</fechaEmision>' .
        '<codigoTipoAutorizacion>E</codigoTipoAutorizacion>' .
        '<codigoTipoDocumento>' . $tipoDoc . '</codigoTipoDocumento>' .
        '<numeroDocumento>' . htmlspecialchars($nroDoc, ENT_XML1, 'UTF-8') . '</numeroDocumento>' .
        '<idImpositivo>' . $idImpositivo . '</idImpositivo>' .
        '<codigoPais>' . $codigoPais . '</codigoPais>' .
        '<domicilioReceptor>' . htmlspecialchars($domicilio, ENT_XML1, 'UTF-8') . '</domicilioReceptor>' .
        '<codigoRelacionEmisorReceptor>01</codigoRelacionEmisorReceptor>' .  // 01 = Turista adquiere directamente al prestador
        '<importeGravado>' . $gravado_str . '</importeGravado>' .
        '<importeReintegro>' . $importeReintegro_str . '</importeReintegro>' .
        '<importeTotal>' . $importeTotal_str . '</importeTotal>' .
        '<codigoMoneda>PES</codigoMoneda>' .
        '<cotizacionMoneda>1</cotizacionMoneda>' .
        '<cancelaEnMismaMonedaExtranjera>N</cancelaEnMismaMonedaExtranjera>' .
        '<arrayItems>' .
        '<item>' .
        '<tipo>0</tipo>' .
        '<codigoTurismo>1</codigoTurismo>' .
        '<descripcion>Servicio de hoteleria - alojamiento</descripcion>' .
        '<codigoAlicuotaIVA>5</codigoAlicuotaIVA>' .
        '<importeIVA>' . $iva_str . '</importeIVA>' .
        '<importeItem>' . $importeItem_str . '</importeItem>' .
        '</item>' .
        '</arrayItems>' .
        '<arraySubtotalesIVA>' .
        '<subtotalIVA>' .
        '<codigo>5</codigo>' .
        '<importe>' . $iva_str . '</importe>' .
        '</subtotalIVA>' .
        '</arraySubtotalesIVA>' .
        '<arrayFormasPago>' .
        '<formaPago>' .
        '<codigo>' . $codigoFormaPago . '</codigo>' .
        '</formaPago>' .
        '</arrayFormasPago>' .
        '</comprobanteRequest>' .
        '</cts:autorizarComprobanteRequest>' .
        '</soapenv:Body>' .
        '</soapenv:Envelope>';

    $headers = [
        "Content-Type: text/xml; charset=utf-8",
        'SOAPAction: "http://ar.gob.afip.wsct/CTService/autorizarComprobante"',
        "Content-Length: " . strlen($xml_post_string)
    ];

    // Guardar XML de request para debug
    $request_file = plugin_dir_path(__FILE__) . '../invoicesxmlt/' . $transactionID . '_REQUEST.xml';
    file_put_contents($request_file, $xml_post_string);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $soapUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $xml_post_string);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

    $response = curl_exec($ch);

    if ($response === false) {
        $err = curl_error($ch);
        curl_close($ch);
        return '<error>CURL_ERROR: ' . $err . '</error>';
    }

    curl_close($ch);
    return $response;
}
*/
function FECAESolicitarTipoT($Token, $Sign, $Cuit, $code_transaction, $timenow)
{
    global $wpdb, $table_name_transactions;

    // =============================
    // 1. OBTENER DATOS TRANSACCIÓN
    // =============================
    $sql = $wpdb->prepare(
        "SELECT transactionID, transactionDateTime, passportNumber, amount, country, address, description, transactionType
         FROM $table_name_transactions WHERE id = %d",
        $code_transaction
    );
    $row = $wpdb->get_row($sql);

    if (!$row) {
        return '<error>Transacción no encontrada</error>';
    }

    $transactionDateTime = $row->transactionDateTime;
    $passportNumber      = $row->passportNumber;
    $amount              = (float)$row->amount;
    $country             = $row->country;
    $address             = $row->address ?: 'Sin domicilio informado';
    $description         = strtolower($row->description . ' ' . $row->transactionType);

    // =============================
    // 2. DOCUMENTO RECEPTOR
    // =============================
    $nroDoc = preg_replace('/[^A-Za-z0-9]/', '', $passportNumber);

    if (preg_match('/^\d{11}$/', $nroDoc)) {
        $tipoDoc = 80;
    } elseif (preg_match('/^\d{7,8}$/', $nroDoc)) {
        $tipoDoc = 96;
    } else {
        $tipoDoc = 94;
    }

    // =============================
    // 3. PAÍS / IMPOSITIVO
    // =============================
    $codigoPais   = obtenerCodigoAFIP($country, 298);
    $idImpositivo = '9';

    // =============================
    // 4. FORMA DE PAGO WSCT
    // =============================
    // Códigos AFIP WSCT según documentación:
    // 68 = Tarjeta de Crédito
    // 69 = Tarjeta de Débito
    // 9  = Transferencia Bancaria
    // 99 = Otra (OTA/intermediarios)
    $mapFormasPagoWSCT = [
        'credit' => '68',
        'debit'  => '69',
        'ota'    => '99',
    ];

    $codigoFormaPago = null;

    if (strpos($description, 'credit') !== false || strpos($description, 'crédito') !== false) {
        $codigoFormaPago = $mapFormasPagoWSCT['credit'];
    } elseif (strpos($description, 'debit') !== false || strpos($description, 'débito') !== false) {
        $codigoFormaPago = $mapFormasPagoWSCT['debit'];
    } elseif (
        strpos($description, 'booking') !== false ||
        strpos($description, 'expedia') !== false ||
        strpos($description, 'ota') !== false
    ) {
        $codigoFormaPago = $mapFormasPagoWSCT['ota'];
    }

    if (!$codigoFormaPago) {
        // Log error de forma de pago
        SAH_Logger::file(
            SAH_Logger::ERROR,
            SAH_Logger::FACTURA_T,
            'FECAESolicitarTipoT_forma_pago_error',
            array(
                'transaction_id' => $row->transactionID,
                'description' => $description,
                'amount' => $amount
            ),
            null,
            'Forma de pago no detectada para WSCT. Description: ' . $description
        );
        return '<error>Forma de pago inválida para WSCT. No se detectó credit/debit/booking/expedia/ota en la descripción.</error>';
    }

    // =============================
    // 4.1 DATOS MEDIO DE PAGO
    // =============================
    // tipoTarjeta AFIP: 1=AmEx, 2=Visa, 3=Mastercard, 99=Otra
    // numeroTarjeta: últimos 6 dígitos (numérico)
    // tipoCuenta/numeroCuenta: valores numéricos para OTA
    $formaPagoExtraXML = '';

    switch ($codigoFormaPago) {
        case '68': // Tarjeta de Crédito
        case '69': // Tarjeta de Débito
            $formaPagoExtraXML = '
                <tipoTarjeta>99</tipoTarjeta>
                <numeroTarjeta>999999</numeroTarjeta>';
            break;

        case '99': // OTA/Otra
            // Para código 99 (Otra) no se requieren campos adicionales
            $formaPagoExtraXML = '';
            break;
    }

    // =============================
    // 5. CÁLCULOS FACTURA T
    // =============================
    // El reintegro es el IVA que se devuelve al turista extranjero
    // AFIP requiere que importeReintegro sea NEGATIVO (menor o igual a cero)
    $montoNeto        = round($amount / 1.21, 2);
    $montoIVA         = round($amount - $montoNeto, 2);
    $importeReintegro = -$montoIVA;  // NEGATIVO - es el reintegro al turista
    $importeTotal     = $montoNeto;

    // =============================
    // 6. COMPROBANTE
    // =============================
    $numeroComprobante = FECompUltimoAutorizadoTipoT($Token, $Sign, $Cuit);

    date_default_timezone_set('America/Argentina/Buenos_Aires');
    $fechaEmision = ($timenow === 'NULL')
        ? date('Y-m-d', strtotime($transactionDateTime))
        : date('Y-m-d');

    // =============================
    // 7. XML WSCT
    // =============================
    $xml = '<?xml version="1.0" encoding="utf-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"
                  xmlns:cts="http://ar.gob.afip.wsct/CTService/">
  <soapenv:Header/>
  <soapenv:Body>
    <cts:autorizarComprobanteRequest>
      <authRequest>
        <token>' . htmlspecialchars($Token, ENT_XML1) . '</token>
        <sign>' . htmlspecialchars($Sign, ENT_XML1) . '</sign>
        <cuitRepresentada>' . $Cuit . '</cuitRepresentada>
      </authRequest>
      <comprobanteRequest>

        <codigoTipoComprobante>195</codigoTipoComprobante>
        <numeroPuntoVenta>2</numeroPuntoVenta>
        <numeroComprobante>' . $numeroComprobante . '</numeroComprobante>
        <fechaEmision>' . $fechaEmision . '</fechaEmision>
        <codigoTipoAutorizacion>E</codigoTipoAutorizacion>

        <codigoTipoDocumento>' . $tipoDoc . '</codigoTipoDocumento>
        <numeroDocumento>' . $nroDoc . '</numeroDocumento>
        <idImpositivo>' . $idImpositivo . '</idImpositivo>
        <codigoPais>' . $codigoPais . '</codigoPais>
        <domicilioReceptor>' . htmlspecialchars($address, ENT_XML1) . '</domicilioReceptor>
        <codigoRelacionEmisorReceptor>01</codigoRelacionEmisorReceptor>

        <importeGravado>' . number_format($montoNeto, 2, '.', '') . '</importeGravado>
        <importeReintegro>' . number_format($importeReintegro, 2, '.', '') . '</importeReintegro>
        <importeTotal>' . number_format($importeTotal, 2, '.', '') . '</importeTotal>

        <codigoMoneda>PES</codigoMoneda>
        <cotizacionMoneda>1</cotizacionMoneda>
        <cancelaEnMismaMonedaExtranjera>N</cancelaEnMismaMonedaExtranjera>

        <arrayItems>
          <item>
            <tipo>0</tipo>
            <codigoTurismo>1</codigoTurismo>
            <descripcion>Servicio de hoteleria - alojamiento</descripcion>
            <codigoAlicuotaIVA>5</codigoAlicuotaIVA>
            <importeIVA>' . number_format($montoIVA, 2, '.', '') . '</importeIVA>
            <importeItem>' . number_format($amount, 2, '.', '') . '</importeItem>
          </item>
        </arrayItems>

        <arraySubtotalesIVA>
          <subtotalIVA>
            <codigo>5</codigo>
            <importe>' . number_format($montoIVA, 2, '.', '') . '</importe>
          </subtotalIVA>
        </arraySubtotalesIVA>

        <arrayFormasPago>
          <formaPago>
            <codigo>' . $codigoFormaPago . '</codigo>'
            . $formaPagoExtraXML . '
          </formaPago>
        </arrayFormasPago>

      </comprobanteRequest>
    </cts:autorizarComprobanteRequest>
  </soapenv:Body>
</soapenv:Envelope>';

    // =============================
    // 8. GUARDAR XML REQUEST (DEBUG)
    // =============================
    $request_file = plugin_dir_path(__FILE__) . '../invoicesxmlt/' . $row->transactionID . '_REQUEST.xml';
    @file_put_contents($request_file, $xml);

    // =============================
    // 9. ENVIAR A WSCT (cURL)
    // =============================
    $soapUrl = "https://serviciosjava.afip.gob.ar/wsct/CTService";

    $headers = [
        "POST /wsct/CTService HTTP/1.1",
        "Host: serviciosjava.afip.gob.ar",
        "Content-Type: text/xml; charset=utf-8",
        'SOAPAction: "http://ar.gob.afip.wsct/CTService/autorizarComprobante"',
        "Content-Length: " . strlen($xml)
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $soapUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    $response = curl_exec($ch);

    if ($response === false) {
        $curl_error = curl_error($ch);
        curl_close($ch);

        // Log error cURL
        SAH_Logger::file(
            SAH_Logger::ERROR,
            SAH_Logger::FACTURA_T,
            'FECAESolicitarTipoT_curl_error',
            array('transaction_id' => $row->transactionID, 'error' => $curl_error),
            null,
            'Error cURL al enviar a WSCT'
        );

        return '<error>CURL_ERROR: ' . htmlspecialchars($curl_error) . '</error>';
    }

    curl_close($ch);

    // Log respuesta recibida
    SAH_Logger::file(
        SAH_Logger::INFO,
        SAH_Logger::FACTURA_T,
        'FECAESolicitarTipoT_response',
        array('transaction_id' => $row->transactionID, 'response_length' => strlen($response)),
        null,
        'Respuesta recibida de WSCT'
    );

    return $response;
}



function FECompUltimoAutorizado($Token, $Sign, $Cuit)
{

    global $wpdb, $table_name_transactions;

    //Iniciamos la consulta a la api FECompUltimoAutorizado

    $soapUrl = "https://servicios1.afip.gov.ar/wsfev1/service.asmx?op=FECompUltimoAutorizado";

    $xml_post_string =
        '<?xml version="1.0" encoding="utf-8"?>
                <soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
                  <soap:Body>
                    <FECompUltimoAutorizado  xmlns="http://ar.gov.afip.dif.FEV1/">
                      <Auth>
                        <Token>' . $Token . '</Token>
                        <Sign>' . $Sign . '</Sign>
                        <Cuit>' . $Cuit . '</Cuit>
                      </Auth>
                      <PtoVta>2</PtoVta>
                      <CbteTipo>6</CbteTipo>
                    </FECompUltimoAutorizado >
                  </soap:Body>
                </soap:Envelope>';


    $headers = ["POST /wsfev1/service.asmx HTTP/1.1", "Host: servicios1.afip.gov.ar", "Content-Type: text/xml; charset=utf-8", "Content-Length: " . strlen($xml_post_string)];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $soapUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $xml_post_string);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

    $respons = curl_exec($ch);
    curl_close($ch);

    return $respons;
}

function FECompUltimoAutorizadoTipoT($Token, $Sign, $Cuit)
{
    // Endpoint PRODUCCIÓN WSCT
    $soapUrl = "https://serviciosjava.afip.gob.ar/wsct/CTService";

    // Envelope correcto (document/literal) según WSDL WSCT
    $xml_post_string = '<?xml version="1.0" encoding="utf-8"?>'
        . '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" '
        . 'xmlns:cts="http://ar.gob.afip.wsct/CTService/">'
        . '  <soapenv:Header/>'
        . '  <soapenv:Body>'
        . '    <cts:consultarUltimoComprobanteAutorizadoRequest>'
        . '      <authRequest>'
        . '        <token>' . htmlspecialchars($Token, ENT_XML1) . '</token>'
        . '        <sign>' . htmlspecialchars($Sign, ENT_XML1) . '</sign>'
        . '        <cuitRepresentada>' . $Cuit . '</cuitRepresentada>'
        . '      </authRequest>'
        . '      <codigoTipoComprobante>195</codigoTipoComprobante>'
        . '      <numeroPuntoVenta>2</numeroPuntoVenta>'
        . '    </cts:consultarUltimoComprobanteAutorizadoRequest>'
        . '  </soapenv:Body>'
        . '</soapenv:Envelope>';

    // Headers correctos
    $headers = [
        "POST /wsct/CTService HTTP/1.1",
        "Host: serviciosjava.afip.gob.ar",
        "Content-Type: text/xml; charset=utf-8",
        'SOAPAction: "http://ar.gob.afip.wsct/CTService/consultarUltimoComprobanteAutorizado"',
        "Content-Length: " . strlen($xml_post_string)
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $soapUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $xml_post_string);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    // Opcional: ver errores TLS/Proxy si aplica
    // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $response = curl_exec($ch);

    if ($response === false) {
        $err = curl_error($ch);
        curl_close($ch);
        // Devuelve el error para debug/log
        return "CURL_ERROR: " . $err;
    }

    // Si viene 1002 => no hay anteriores => retorna 1
    if (preg_match('#<numeroComprobante>(\d+)</numeroComprobante>#', $response, $m)) {
        return (int)$m[1] + 1;
    }
    return 1;
}

function generatePDF($code, $transactionID)
{

    global $wpdb, $table_name_transactions;

    //Iniciamos la consulta a database para traer datos importantes de la factura

    $sql_res = "SELECT id,transactionID,transactionDateTime,reservationID,description,passportNumber,completeName,amount,city,address FROM $table_name_transactions WHERE id=" . $code;
    $res_t = $wpdb->get_results($sql_res) or die(mysql_error());

    foreach ($res_t  as $key => $row) {
        // each column in your row will be accessible like this
        $passportNumber =  $row->passportNumber;
        $completeName =  $row->completeName;
        $amount =  round($row->amount, 2);
        $description =  $row->description;
        $reservationID =  $row->reservationID;
        $city =  $row->city;
        $address =  $row->address;
        $transactionDateTime = $row->transactionDateTime;
    }

    //calculo de subtotal number_format($dat1,2,',','.')
    $monto_n = calc_neto($amount);
    $monto_neto = round($monto_n, 2);

    //calculo de iva
    $monto_i = calc_iva($monto_n);
    $monto_iva = round($monto_i, 2);

    $importe_total = number_format($amount, 2, ',', '.');
    $monto_neto = number_format($monto_neto, 2, ',', '.');
    $monto_iva = number_format($monto_iva, 2, ',', '.');

    $xml_file = file_get_contents(plugin_dir_path(__FILE__) . "../invoicesxml/" . $transactionID . ".xml");

    $doc = new DOMDocument();
    $doc->loadXML($xml_file);

    $pto_venta = $doc->getElementsByTagName('PtoVta')->item(0)->nodeValue;

    $num_factura = $doc->getElementsByTagName('CbteDesde')->item(0)->nodeValue;

    $orgDate_process = $doc->getElementsByTagName('FchProceso')->item(0)->nodeValue;
    $newDate_process = date("d/m/Y", strtotime($orgDate_process));

    $orgDate = $doc->getElementsByTagName('CbteFch')->item(0)->nodeValue;
    $newDate = date("d/m/Y", strtotime($orgDate));

    $nro_cae = $doc->getElementsByTagName('CAE')->item(0)->nodeValue;
    $date_cae = $doc->getElementsByTagName('CAEFchVto')->item(0)->nodeValue;
    $fecha_cae = date("d/m/Y", strtotime($date_cae));

    $date_process = $doc->getElementsByTagName('FchProceso')->item(0)->nodeValue;
    $fecha_process = date("d_m_Y_h_i_s", strtotime($date_process));
    $name_file = $reservationID . '_' . $fecha_process;

    $codbarra = generate_qr($name_file);
    $cod_barra = URL_WEB_FILES_QR . 'img/codesqr/' . $codbarra;
    //generate pdf

    $html = '
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <style>
        table {
          border:1px solid white;
        }
        th, td {
          border:1px solid black;
        }
        .whitet {
          border:1px solid white;
          font-size:14px
        }
        footer {
            position: fixed;
            bottom: -0px;
            left: 0px;
            right: 0px;
            height: 50px;

            /** Extra personal styles **/
            background-color: #03a9f4;
            color: white;
            text-align: center;
            line-height: 35px;
        }
    </style>
    <body>

  <!-- Wrap the content of your PDF inside a main tag -->
  <main>
  <table border=1 width="680px">
    <tbody>
        <tr height="100px">
            <td class="" style="font-size:20px;text-align:center" colspan="3"><b>ORIGINAL</b></td>
        </tr>

        <tr height="140px">
            <td width="290px" style="font-size:20px;text-align:center"><b>PENTHOUSE 1004</b></td>
            <td width="100px" style="text-align:center"><p style="font-size:50px;margin-top:-5px;margin-bottom:-20px"><b>B</b></p><p font-size:25px;><b>COD. 006</b></p></td>
            <td width="290px" style="font-size:20px;padding-left:20px"><b>FACTURA</b></td>
        </tr>

        <tr height="340px">
            <td class="" colspan="3">
                <table class="whitet">
                    <tr>

                        <td width="261">
                            <ul style="list-style-type: none;font-size:14px">
                                <li><b>Razón Social:</b> DEVA S.A.S</li>
                                <li><b>Domicilio Comercial:</b> San Martín 127 - piso 10 - Dpto. 1004 -Bariloche - Río Negro (CP 8400)</li>
                                <li><b>Condición frente al IVA:</b> IVA Responsable Inscripto</li>
                            </ul>
                        </td>

                        <td width="261">
                            <ul style="list-style-type: none;font-size:14px">
                                <li><b>Punto de Venta:</b> 0000' . $pto_venta . ' Comp. Nro: ' . add_zeros($num_factura) . '</li>
                                <li><b>Fecha de Emisión:</b> ' . $newDate . '</li>
                                <li>
                                    <b>CUIT:</b> 30-71844697-6<br>
                                    <b>Ingresos Brutos:</b> 47187980<br>
                                    <b>Fecha de Inicio de Actividades:</b> 01/04/2025
                                </li>
                            </ul>
                        </td>

                    </tr>
                </table>
            </td>
        </tr>

        <tr height="340px">
            <td class="" colspan="3">
                <table>
                    <tr>

                        <td class="whitet" width="172">
                            <p style="font-size:14px">Período Facturado Desde: ' . $newDate . '</p>
                        </td>

                        <td class="whitet" width="172">
                            <p style="font-size:14px;text-align:center">Hasta: ' . $newDate . '</p>
                        </td>

                        <td class="whitet" width="172">
                            <p style="font-size:14px">Fecha de Vto. para el pago: ' . $fecha_cae . '</p>
                        </td>


                    </tr>
                </table>
            </td>
        </tr>
        <tr height="340px">
            <td class="" colspan="3">
                <table class="">
                    <tr>

                        <td width="261">
                            <ul style="list-style-type: none;font-size:14px">';

    if ($passportNumber != null or $passportNumber != '') {
        $html .= '<li><b>Doc.:</b> ' . $passportNumber . '</li>';
    }

    $html .= '<li><b>Condición frente al IVA:</b> Consumidor Final</li>
                                <li><b>Condición de venta:</b> Contado</li>
                            </ul>
                        </td>
                        <td width="261">
                            <ul style="list-style-type: none;font-size:14px">
                                <li><b>Apellido y Nombre / Razón Social:</b> ' . $completeName . '</li>';

    if ($city != null or $city != '') {
        $html .= '<li><b>Domicilio:</b> ' . $city . ' ' . $address . '</li>
                        </ul>
                            </td>';
    }

    $html .= '</tr>
                </table>
            </td>
        </tr>';

    $html .= '<tr height="250px">
            <td class="whitet" colspan="3">
                <table width="100%" height="40%" class="whitet">
                    <thead>
                    <tr style="background-color:#D8E2DC">
                        <th>Código</th>
                        <th>Producto / Servicio</th>
                        <th>Cantidad</th>
                        <th>U. Medida</th>
                        <th>Precio Unit</th>
                        <th>% Bonif</th>
                        <th>Imp. Bonif</th>
                        <th>Subtotal</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr>
                        <td class="whitet"></td>
                        <td class="whitet">BOOKING ID: ' . $reservationID . '</td>
                        <td class="whitet">1,00</td>
                        <td class="whitet">unidades</td>
                        <td class="whitet">' . $monto_neto . '</td>
                        <td class="whitet">0,00</td>
                        <td class="whitet">0,00</td>
                        <td class="whitet">' . $monto_neto . '</td>
                    </tr>
                    </tbody>
                </table>
            </td>
        </tr>

        <tr height="300px"></tr>

    </tbody>
  </table>

  <div style="position:fixed;bottom: 240px;left: 0px;right: 0px;height: 0px;">

    <table border=1 width="100%" class="whitet">
        <tbody>
            <tr>
                <td align="right" class="gray">
                    <ul style="list-style-type: none;">
                        <li><b>Subtotal:</b> $ ' . $monto_neto . ' </li>
                        <li><b>Importe Otros Tributos:</b> $ ' . $monto_iva . ' </li>
                        <li><b>Importe Total:</b> $ ' . $importe_total . ' </li>
                    </ul>
                </td>
            </tr>
            
            <tr>
                <td align="left" class="gray">
                    <ul style="list-style-type: none;">
                        <li>Régimen de Transparencia Fiscal al Consumidor (Ley 27.743)</li>
                        <li><b>IVA contenido :</b> $ ' . $monto_iva . '</li>
                    </ul>
                </td>
                
            </tr>
        </tbody>
    </table>
    
    <table width="100%" style="border:1px solid white">
        <tbody>
            <tr>
                <td style="border:1px solid white" align="right">
                    <ul style="list-style-type: none;font-size:16px;float:left">
                        <li><img src="' . $cod_barra . '" style="width:80px;float:left"></li>
                        <li><p style="font-size:10px;margin-top:85px">Comprobante Autorizado por ARCA</p></li>
                    </ul>
                    <ul style="list-style-type: none;font-size:16px;float:right">
                        <li><b>CAE N°:</b> ' . $nro_cae . '</li>
                        <li><b>Fecha de Vto. de CAE:</b> ' . $fecha_cae . '</li>
                    </ul>
                </td>
            </tr>
        </tbody>
    </table>

  </div>
  </main>

    </body>
    ';

    //Aquí se crea el objeto a utilizar
    $options = new Options();

    //Y debes activar esta opción "TRUE"
    $options->set('isRemoteEnabled', TRUE);

    // instantiate and use the dompdf class
    $dompdf = new DOMPDF($options);

    // Cargamos el contenido HTML.
    $dompdf->load_html($html);

    // (Optional) Setup the paper size and orientation
    $dompdf->setPaper('A4', 'portrait');

    // Render the HTML as PDF
    $dompdf->render();

    // Output the generated PDF to variable and return it to save it into the file
    $output = $dompdf->output();

    $filen = $name_file . '.pdf';

    file_put_contents(plugin_dir_path(__FILE__) . "../invoicespdf/" . $name_file . ".pdf", $output);

    return $filen;
}

function generatePDFTipoT($code, $transactionID)
{
    global $wpdb, $table_name_transactions;

    // Obtener datos de la transacción
    $sql_res = "SELECT id,transactionID,transactionDateTime,reservationID,description,passportNumber,completeName,amount,city,address,country FROM $table_name_transactions WHERE id=" . $code;
    $res_t = $wpdb->get_results($sql_res) or die(mysql_error());

    foreach ($res_t as $key => $row) {
        $passportNumber = $row->passportNumber;
        $completeName = $row->completeName;
        $amount = round($row->amount, 2);
        $description = $row->description;
        $reservationID = $row->reservationID;
        $city = $row->city;
        $address = $row->address;
        $country = $row->country;
        $transactionDateTime = $row->transactionDateTime;
    }

    // Cálculos para Tipo T (IVA reintegrado)
    $monto_neto = round($amount / 1.21, 2);
    $monto_iva = round($amount - $monto_neto, 2);
    $importe_reintegro = $monto_iva; // IVA que se reintegra

    // El total a pagar es el neto (IVA se reintegra)
    $importe_total = number_format($monto_neto, 2, ',', '.');
    $monto_neto_fmt = number_format($monto_neto, 2, ',', '.');
    $monto_iva_fmt = number_format($monto_iva, 2, ',', '.');
    $importe_reintegro_fmt = number_format($importe_reintegro, 2, ',', '.');

    // Leer XML de respuesta WSCT (desde carpeta invoicesxmlt)
    $xml_file = file_get_contents(plugin_dir_path(__FILE__) . "../invoicesxmlt/" . $transactionID . ".xml");

    // Extraer datos del XML WSCT
    $pto_venta = '2';
    $num_factura = '';
    $nro_cae = '';
    $fecha_cae = '';
    $fecha_emision = '';

    if (preg_match('/<numeroComprobante>(\d+)<\/numeroComprobante>/i', $xml_file, $m)) {
        $num_factura = $m[1];
    }
    if (preg_match('/<cae>(\d+)<\/cae>/i', $xml_file, $m)) {
        $nro_cae = $m[1];
    }
    if (preg_match('/<fechaVencimientoCae>([^<]+)<\/fechaVencimientoCae>/i', $xml_file, $m)) {
        $fecha_cae = date("d/m/Y", strtotime($m[1]));
    }
    if (preg_match('/<fechaEmision>([^<]+)<\/fechaEmision>/i', $xml_file, $m)) {
        $fecha_emision = date("d/m/Y", strtotime($m[1]));
    }

    $name_file = $reservationID . '_' . date("d_m_Y_h_i_s");

    $codbarra = generate_qr($name_file);
    $cod_barra = URL_WEB_FILES_QR . 'img/codesqr/' . $codbarra;

    // Nombre del país para mostrar
    $pais_nombre = $country;

    $html = '
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <style>
        table {
          border:1px solid white;
        }
        th, td {
          border:1px solid black;
        }
        .whitet {
          border:1px solid white;
          font-size:14px
        }
        footer {
            position: fixed;
            bottom: -0px;
            left: 0px;
            right: 0px;
            height: 50px;
            background-color: #03a9f4;
            color: white;
            text-align: center;
            line-height: 35px;
        }
    </style>
    <body>
    <main>
    <table border=1 width="680px">
        <tbody>
            <tr height="100px">
                <td class="" style="font-size:20px;text-align:center" colspan="3"><b>ORIGINAL</b></td>
            </tr>

            <tr height="140px">
                <td width="290px" style="font-size:20px;text-align:center"><b>PENTHOUSE 1004</b></td>
                <td width="100px" style="text-align:center"><p style="font-size:50px;margin-top:-5px;margin-bottom:-20px"><b>T</b></p><p font-size:25px;><b>COD. 195</b></p></td>
                <td width="290px" style="font-size:20px;padding-left:20px"><b>FACTURA DE EXPORTACION DE SERVICIOS</b></td>
            </tr>

            <tr height="340px">
                <td class="" colspan="3">
                    <table class="whitet">
                        <tr>
                            <td width="261">
                                <ul style="list-style-type: none;font-size:14px">
                                    <li><b>Razon Social:</b> DEVA S.A.S</li>
                                    <li><b>Domicilio Comercial:</b> San Martin 127 - piso 10 - Dpto. 1004 - Bariloche - Rio Negro (CP 8400)</li>
                                    <li><b>Condicion frente al IVA:</b> IVA Responsable Inscripto</li>
                                </ul>
                            </td>
                            <td width="261">
                                <ul style="list-style-type: none;font-size:14px">
                                    <li><b>Punto de Venta:</b> 0000' . $pto_venta . ' Comp. Nro: ' . add_zeros($num_factura) . '</li>
                                    <li><b>Fecha de Emision:</b> ' . $fecha_emision . '</li>
                                    <li>
                                        <b>CUIT:</b> 30-71844697-6<br>
                                        <b>Ingresos Brutos:</b> 47187980<br>
                                        <b>Fecha de Inicio de Actividades:</b> 01/04/2025
                                    </li>
                                </ul>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>

            <tr height="340px">
                <td class="" colspan="3">
                    <table>
                        <tr>
                            <td class="whitet" width="172">
                                <p style="font-size:14px">Periodo Facturado Desde: ' . $fecha_emision . '</p>
                            </td>
                            <td class="whitet" width="172">
                                <p style="font-size:14px;text-align:center">Hasta: ' . $fecha_emision . '</p>
                            </td>
                            <td class="whitet" width="172">
                                <p style="font-size:14px">Fecha de Vto. para el pago: ' . $fecha_cae . '</p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>

            <tr height="340px">
                <td class="" colspan="3">
                    <table class="">
                        <tr>
                            <td width="261">
                                <ul style="list-style-type: none;font-size:14px">';

    if ($passportNumber != null && $passportNumber != '') {
        $html .= '<li><b>Pasaporte:</b> ' . $passportNumber . '</li>';
    }

    $html .= '<li><b>Pais:</b> ' . $pais_nombre . '</li>
                                    <li><b>Condicion:</b> Turista Extranjero</li>
                                </ul>
                            </td>
                            <td width="261">
                                <ul style="list-style-type: none;font-size:14px">
                                    <li><b>Apellido y Nombre:</b> ' . $completeName . '</li>';

    if ($city != null && $city != '') {
        $html .= '<li><b>Domicilio:</b> ' . $city . ' ' . $address . '</li>';
    }

    $html .= '</ul>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>';

    $html .= '<tr height="250px">
            <td class="whitet" colspan="3">
                <table width="100%" height="40%" class="whitet">
                    <thead>
                    <tr style="background-color:#D8E2DC">
                        <th>Codigo</th>
                        <th>Producto / Servicio</th>
                        <th>Cantidad</th>
                        <th>U. Medida</th>
                        <th>Precio Unit</th>
                        <th>IVA</th>
                        <th>Subtotal</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr>
                        <td class="whitet">1</td>
                        <td class="whitet">Servicio de hoteleria - alojamiento (BOOKING: ' . $reservationID . ')</td>
                        <td class="whitet">1,00</td>
                        <td class="whitet">unidades</td>
                        <td class="whitet">' . $monto_neto_fmt . '</td>
                        <td class="whitet">' . $monto_iva_fmt . '</td>
                        <td class="whitet">' . number_format($amount, 2, ',', '.') . '</td>
                    </tr>
                    </tbody>
                </table>
            </td>
        </tr>

        <tr height="300px"></tr>

        </tbody>
    </table>

    <div style="position:fixed;bottom: 240px;left: 0px;right: 0px;height: 0px;">

        <table border=1 width="100%" class="whitet">
            <tbody>
                <tr>
                    <td align="right" class="gray">
                        <ul style="list-style-type: none;">
                            <li><b>Importe Gravado:</b> $ ' . $monto_neto_fmt . '</li>
                            <li><b>IVA 21%:</b> $ ' . $monto_iva_fmt . '</li>
                            <li><b>Reintegro IVA:</b> -$ ' . $importe_reintegro_fmt . '</li>
                            <li style="font-size:16px"><b>Importe Total:</b> $ ' . $importe_total . '</li>
                        </ul>
                    </td>
                </tr>

                <tr>
                    <td align="left" class="gray">
                        <ul style="list-style-type: none;">
                            <li><b>Regimen de Reintegro del IVA a Turistas del Exterior (Ley 25.997)</b></li>
                            <li>El IVA contenido en esta factura sera reintegrado al turista.</li>
                        </ul>
                    </td>
                </tr>
            </tbody>
        </table>

        <table width="100%" style="border:1px solid white">
            <tbody>
                <tr>
                    <td style="border:1px solid white" align="right">
                        <ul style="list-style-type: none;font-size:16px;float:left">
                            <li><img src="' . $cod_barra . '" style="width:80px;float:left"></li>
                            <li><p style="font-size:10px;margin-top:85px">Comprobante Autorizado por ARCA</p></li>
                        </ul>
                        <ul style="list-style-type: none;font-size:16px;float:right">
                            <li><b>CAE N°:</b> ' . $nro_cae . '</li>
                            <li><b>Fecha de Vto. de CAE:</b> ' . $fecha_cae . '</li>
                        </ul>
                    </td>
                </tr>
            </tbody>
        </table>

    </div>
    </main>
    </body>
    ';

    // Crear PDF con DomPDF
    $options = new Options();
    $options->set('isRemoteEnabled', TRUE);

    $dompdf = new DOMPDF($options);
    $dompdf->load_html($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    $output = $dompdf->output();
    $filen = $name_file . '.pdf';

    // Guardar en carpeta invoicespdft (para Tipo T)
    file_put_contents(plugin_dir_path(__FILE__) . "../invoicespdft/" . $name_file . ".pdf", $output);

    return $filen;
}

function saveUrlFile($code, $name_file)
{

    global $wpdb, $table_name_transactions;

    $data_update_name_file = $wpdb->update(
        $table_name_transactions, //table
        array('invoiceUrl' => $name_file), //data
        array('id' => $code), //where
        array('%s'), //data format
        array('%s') //where format
    );

    return $data_update_name_file;
}

function calc_neto($val)
{

    $value = $val * 100 / 121;
    return $value;
}

function calc_iva($val)
{

    $value = $val * 0.21;
    return $value;
}

// function calc_iva($val){

//   $value = intval($val) * 0.21;
//   return $value;

// }

// function calc_trib($val){

//   $value = intval($val) * 0.052;
//   return $value;

// }

function CheckNumber($x)
{
    if ($x > 0) {
        $message = "Positive";
    }
    if ($x == 0) {
        $message = "Zero";
    }
    if ($x < 0) {
        $message = "Negative";
    }
    return $message;
}

// FUNCTION TO MUNG THE XML SO WE DO NOT HAVE TO DEAL WITH NAMESPACE
function mungXML($xml)
{
    $obj = SimpleXML_Load_String($xml);
    if ($obj === FALSE) return $xml;

    // GET NAMESPACES, IF ANY
    $nss = $obj->getNamespaces(TRUE);
    if (empty($nss)) return $xml;

    // CHANGE ns: INTO ns_
    $nsm = array_keys($nss);
    foreach ($nsm as $key) {
        // A REGULAR EXPRESSION TO MUNG THE XML
        $rgx
            = '#'               // REGEX DELIMITER
            . '('               // GROUP PATTERN 1
            . '\<'              // LOCATE A LEFT WICKET
            . '/?'              // MAYBE FOLLOWED BY A SLASH
            . preg_quote($key)  // THE NAMESPACE
            . ')'               // END GROUP PATTERN
            . '('               // GROUP PATTERN 2
            . ':{1}'            // A COLON (EXACTLY ONE)
            . ')'               // END GROUP PATTERN
            . '#'               // REGEX DELIMITER
        ;
        // INSERT THE UNDERSCORE INTO THE TAG NAME
        $rep
            = '$1'          // BACKREFERENCE TO GROUP 1
            . '_'           // LITERAL UNDERSCORE IN PLACE OF GROUP 2
        ;
        // PERFORM THE REPLACEMENT
        $xml =  preg_replace($rgx, $rep, $xml);
    }

    return $xml;
} // End :: mungXML()

function generate_qr($name_file)
{

    if (!file_exists(plugin_dir_path(__FILE__) . '../../img/codesqr')) {
        mkdir(plugin_dir_path(__FILE__) . '../../img/codesqr', 0777, true);
    }

    $PNG_TEMP_DIR_SUP = plugin_dir_path(__FILE__) . '../../img/codesqr/';

    $matrixPointSize = 10;
    $errorCorrectionLevel = 'L';

    //format date and hour
    $horasup = date('H:i', strtotime($datet));
    $fechasup = date('d/m/Y', strtotime($datet));

    $filnam = $name_file . '.png';
    $filenam = $PNG_TEMP_DIR_SUP . $name_file . '.png';
    $route_file = URL_WEB_FILES_PDF . $name_file . '.pdf';

    QRcode::png($route_file, $filenam, $errorCorrectionLevel, $matrixPointSize, 1);

    return $filnam;
}

function add_zeros($val)
{

    $numexpend = '';

    $code = strlen($val);
    switch ($code) {
        case 1:
            $nume = '0000000' . $val;
            break;
        case 2:
            $nume = '000000' . $val;
            break;
        case 3:
            $nume = '00000' . $val;
            break;
        case 4:
            $nume = '0000' . $val;
            break;
        case 5:
            $nume = '000' . $val;
            break;
        case 6:
            $nume = '00' . $val;
            break;
        case 7:
            $nume = '0' . $val;
            break;
        default:
            $nume = $val;
    }
    return $nume;
}

// =========================
// WSCT – helper base
// =========================
function WSCT_call($soapAction, $innerXml)
{
    $url = "https://serviciosjava.afip.gob.ar/wsct/CTService";
    $xml = '<?xml version="1.0" encoding="UTF-8"?>'
        . '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" '
        . '                  xmlns:cts="http://ar.gob.afip.wsct/CTService/">'
        . '  <soapenv:Header/>'
        . '  <soapenv:Body>'
        .        $innerXml
        . '  </soapenv:Body>'
        . '</soapenv:Envelope>';

    $headers = [
        "POST /wsct/CTService HTTP/1.1",
        "Host: serviciosjava.afip.gob.ar",
        "Content-Type: text/xml; charset=utf-8",
        'SOAPAction: "http://ar.gob.afip.wsct/CTService/' . $soapAction . '"',
        "Content-Length: " . strlen($xml)
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $resp = curl_exec($ch);
    curl_close($ch);

    return $resp;
}

// =========================
// consultarPaises
// =========================
function WSCT_ConsultarPaises($Token, $Sign, $Cuit)
{
    $body = '
      <cts:consultarPaisesRequest>
        <authRequest>
          <token>' . $Token . '</token>
          <sign>' . $Sign . '</sign>
          <cuitRepresentada>' . $Cuit . '</cuitRepresentada>
        </authRequest>
      </cts:consultarPaisesRequest>';

    return WSCT_call('consultarPaises', $body);
}

// Devuelve el código numérico de país (por ej., 225 para URUGUAY) o null
function WSCT_ObtenerCodigoPais($Token, $Sign, $Cuit, $paisBuscado = 'URUGUAY')
{
    $xml = WSCT_ConsultarPaises($Token, $Sign, $Cuit);

    // "des-namespace" simple para buscar fácil
    $clean = preg_replace('/(xmlns[^=]*="[^"]*")/', '', $xml);
    $clean = str_replace(['ns2:', 'ns3:', 'soapenv:', 'S:', 'cts:'], '', $clean);

    $sx = @simplexml_load_string($clean);
    if (!$sx) return null;

    // Ruta: Envelope/Body/consultarPaisesResponse/consultarPaisesReturn/arrayPaises/codigoDescripcionString
    $items = $sx->Body->consultarPaisesResponse->consultarPaisesReturn->arrayPaises->codigoDescripcionString ?? [];
    foreach ($items as $cd) {
        $codigo = (string)$cd->codigo;        // ej.: 225
        $descr  = strtoupper(trim((string)$cd->descripcion)); // ej.: URUGUAY
        if ($descr === strtoupper($paisBuscado)) {
            return (int)$codigo;
        }
    }
    return null;
}

// =========================
// consultarCUITsPaises
// =========================
function WSCT_ConsultarCUITsPaises($Token, $Sign, $Cuit)
{
    $body = '
      <cts:consultarCUITsPaisesRequest>
        <authRequest>
          <token>' . $Token . '</token>
          <sign>' . $Sign . '</sign>
          <cuitRepresentada>' . $Cuit . '</cuitRepresentada>
        </authRequest>
      </cts:consultarCUITsPaisesRequest>';

    return WSCT_call('consultarCUITsPaises', $body);
}

/**
 * Busca el "CUIT País" adecuado para un país y tipo de sujeto.
 * $tipoSujeto esperado (tal como lo devuelve AFIP en la descripcion):
 *   - "Persona humana"
 *   - "Persona jurídica"
 *   - "Otro tipo de entidad"
 *
 * Devuelve string con el CUIT País (p.ej. '50000000059') o null si no lo halla.
 */
function WSCT_ResolverIdImpositivoPais($Token, $Sign, $Cuit, $pais = 'URUGUAY', $tipoSujeto = 'Persona humana')
{
    $xml = WSCT_ConsultarCUITsPaises($Token, $Sign, $Cuit);

    $clean = preg_replace('/(xmlns[^=]*="[^"]*")/', '', $xml);
    $clean = str_replace(['ns2:', 'ns3:', 'soapenv:', 'S:', 'cts:'], '', $clean);

    $sx = @simplexml_load_string($clean);
    if (!$sx) return null;

    // Ruta: Envelope/Body/consultarCUITsPaisesResponse/consultarCUITsPaisesReturn/arrayCuitPaises/codigoDescripcionString
    $items = $sx->Body->consultarCUITsPaisesResponse->consultarCUITsPaisesReturn->arrayCuitPaises->codigoDescripcionString ?? [];
    $paisUp = strtoupper($pais);
    $tipoUp = strtoupper($tipoSujeto);

    foreach ($items as $cd) {
        $codigo = trim((string)$cd->codigo);               // ej.: 50000000059
        $descr  = strtoupper(trim((string)$cd->descripcion)); // ej.: URUGUAY Persona humana
        if (strpos($descr, $paisUp) !== false && strpos($descr, $tipoUp) !== false) {
            return $codigo;
        }
    }
    return null;
}

function WSCT_ConsultarCondicionesIVA($Token, $Sign, $Cuit)
{
    $soapUrl = "https://serviciosjava.afip.gob.ar/wsct/CTService";

    $xml = '<?xml version="1.0" encoding="utf-8"?>'
        . '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" '
        . '                  xmlns:cts="http://ar.gob.afip.wsct/CTService/">'
        . '  <soapenv:Header/>'
        . '  <soapenv:Body>'
        . '    <cts:consultarCondicionesIVARequest>'
        . '      <authRequest>'
        . '        <token>' . htmlspecialchars($Token, ENT_NOQUOTES, "UTF-8") . '</token>'
        . '        <sign>' . htmlspecialchars($Sign, ENT_NOQUOTES, "UTF-8") . '</sign>'
        . '        <cuitRepresentada>' . $Cuit . '</cuitRepresentada>'
        . '      </authRequest>'
        . '    </cts:consultarCondicionesIVARequest>'
        . '  </soapenv:Body>'
        . '</soapenv:Envelope>';

    $headers = [
        'POST /wsct/CTService HTTP/1.1',
        'Host: serviciosjava.afip.gob.ar',
        'Content-Type: text/xml; charset=utf-8',
        'SOAPAction: "http://ar.gob.afip.wsct/CTService/consultarCondicionesIVA"',
        'Content-Length: ' . strlen($xml),
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $soapUrl,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $xml,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => true,
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);

    if (!$resp) return ['ok' => false, 'condiciones' => [], 'raw' => null];

    // Extraer arrayCondicionesIVA / codigoDescripcionString (codigo, descripcion)
    $cond = [];
    if (preg_match_all(
        '~<codigoDescripcionString>\s*<codigo>(.*?)</codigo>\s*<descripcion>(.*?)</descripcion>\s*</codigoDescripcionString>~si',
        $resp,
        $m,
        PREG_SET_ORDER
    )) {
        foreach ($m as $row) {
            $codigo = trim(html_entity_decode($row[1], ENT_QUOTES | ENT_XML1, 'UTF-8'));
            $desc   = trim(html_entity_decode($row[2], ENT_QUOTES | ENT_XML1, 'UTF-8'));
            $cond[$codigo] = $desc;
        }
    }

    return ['ok' => true, 'condiciones' => $cond, 'raw' => $resp];
}

function WSCT_ConsultarTiposItem($Token, $Sign, $Cuit)
{
    $soapUrl = "https://serviciosjava.afip.gob.ar/wsct/CTService";

    $xml = '<?xml version="1.0" encoding="utf-8"?>'
        . '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" '
        . '                  xmlns:cts="http://ar.gob.afip.wsct/CTService/">'
        . '  <soapenv:Header/>'
        . '  <soapenv:Body>'
        . '    <cts:consultarTiposItemRequest>'
        . '      <authRequest>'
        . '        <token>' . htmlspecialchars($Token, ENT_NOQUOTES, "UTF-8") . '</token>'
        . '        <sign>' . htmlspecialchars($Sign, ENT_NOQUOTES, "UTF-8") . '</sign>'
        . '        <cuitRepresentada>' . $Cuit . '</cuitRepresentada>'
        . '      </authRequest>'
        . '    </cts:consultarTiposItemRequest>'
        . '  </soapenv:Body>'
        . '</soapenv:Envelope>';

    $headers = [
        'POST /wsct/CTService HTTP/1.1',
        'Host: serviciosjava.afip.gob.ar',
        'Content-Type: text/xml; charset=utf-8',
        'SOAPAction: "http://ar.gob.afip.wsct/CTService/consultarTiposItem"',
        'Content-Length: ' . strlen($xml),
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $soapUrl,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $xml,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => true,
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);

    if (!$resp) {
        return ['ok' => false, 'tipos' => [], 'raw' => null];
    }

    // Ejemplo de estructura esperada:
    // <arrayTiposItem>
    //   <codigoDescripcionString>
    //     <codigo>1</codigo>
    //     <descripcion>Alojamiento</descripcion>
    //   </codigoDescripcionString>
    //   ...
    // </arrayTiposItem>

    $tipos = [];
    if (preg_match_all(
        '~<codigoDescripcionString>\s*<codigo>(.*?)</codigo>\s*<descripcion>(.*?)</descripcion>\s*</codigoDescripcionString>~si',
        $resp,
        $m,
        PREG_SET_ORDER
    )) {
        foreach ($m as $row) {
            $codigo = trim(html_entity_decode($row[1], ENT_QUOTES | ENT_XML1, 'UTF-8'));
            $desc   = trim(html_entity_decode($row[2], ENT_QUOTES | ENT_XML1, 'UTF-8'));
            $tipos[$codigo] = $desc;
        }
    }

    return ['ok' => true, 'tipos' => $tipos, 'raw' => $resp];
}

function WSCT_ConsultarCodigosItemTurismo($Token, $Sign, $Cuit)
{
    $soapUrl = "https://serviciosjava.afip.gob.ar/wsct/CTService";

    $xml = '<?xml version="1.0" encoding="utf-8"?>'
        . '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" '
        . '                  xmlns:cts="http://ar.gob.afip.wsct/CTService/">'
        . '  <soapenv:Header/>'
        . '  <soapenv:Body>'
        . '    <cts:consultarCodigosItemTurismoRequest>'
        . '      <authRequest>'
        . '        <token>' . htmlspecialchars($Token, ENT_NOQUOTES, "UTF-8") . '</token>'
        . '        <sign>' . htmlspecialchars($Sign, ENT_NOQUOTES, "UTF-8") . '</sign>'
        . '        <cuitRepresentada>' . $Cuit . '</cuitRepresentada>'
        . '      </authRequest>'
        . '    </cts:consultarCodigosItemTurismoRequest>'
        . '  </soapenv:Body>'
        . '</soapenv:Envelope>';

    $headers = [
        'POST /wsct/CTService HTTP/1.1',
        'Host: serviciosjava.afip.gob.ar',
        'Content-Type: text/xml; charset=utf-8',
        'SOAPAction: "http://ar.gob.afip.wsct/CTService/consultarCodigosItemTurismo"',
        'Content-Length: ' . strlen($xml),
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $soapUrl,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $xml,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => true,
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);

    if (!$resp) {
        return ['ok' => false, 'codigos' => [], 'raw' => null];
    }

    // Esperamos algo tipo:
    // <arrayCodigosItem>
    //   <codigoDescripcion>
    //     <codigo>10</codigo>
    //     <descripcion>Alojamiento</descripcion>
    //   </codigoDescripcion>
    //   ...
    // </arrayCodigosItem>

    $codigos = [];
    if (preg_match_all(
        '~<codigoDescripcion>\s*<codigo>(.*?)</codigo>\s*<descripcion>(.*?)</descripcion>\s*</codigoDescripcion>~si',
        $resp,
        $m,
        PREG_SET_ORDER
    )) {
        foreach ($m as $row) {
            $codigo = trim(html_entity_decode($row[1], ENT_QUOTES | ENT_XML1, 'UTF-8'));
            $desc   = trim(html_entity_decode($row[2], ENT_QUOTES | ENT_XML1, 'UTF-8'));
            $codigos[$codigo] = $desc;
        }
    }

    return ['ok' => true, 'codigos' => $codigos, 'raw' => $resp];
}

/**
 * Consulta las formas de pago disponibles en WSCT
 *
 * @param string $Token Token de autenticación AFIP
 * @param string $Sign Firma de autenticación AFIP
 * @param string $Cuit CUIT del contribuyente
 * @return array ['ok' => bool, 'formas' => array, 'raw' => string]
 */
function WSCT_ConsultarFormasPago($Token, $Sign, $Cuit)
{
    $soapUrl = "https://serviciosjava.afip.gob.ar/wsct/CTService";

    $xml = '<?xml version="1.0" encoding="utf-8"?>
    <soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"
                      xmlns:cts="http://ar.gob.afip.wsct/CTService/">
        <soapenv:Header/>
        <soapenv:Body>
            <cts:consultarFormasPagoRequest>
                <authRequest>
                    <token>' . htmlspecialchars($Token, ENT_XML1, 'UTF-8') . '</token>
                    <sign>' . htmlspecialchars($Sign, ENT_XML1, 'UTF-8') . '</sign>
                    <cuitRepresentada>' . $Cuit . '</cuitRepresentada>
                </authRequest>
            </cts:consultarFormasPagoRequest>
        </soapenv:Body>
    </soapenv:Envelope>';

    $headers = [
        "Content-Type: text/xml; charset=utf-8",
        'SOAPAction: "http://ar.gob.afip.wsct/CTService/consultarFormasPago"',
        "Content-Length: " . strlen($xml)
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $soapUrl,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $xml,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => true,
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);

    if (!$resp) {
        return ['ok' => false, 'formas' => [], 'raw' => null];
    }

    $formas = [];
    if (preg_match_all(
        '~<codigoDescripcion>\s*<codigo>(.*?)</codigo>\s*<descripcion>(.*?)</descripcion>\s*</codigoDescripcion>~si',
        $resp,
        $m,
        PREG_SET_ORDER
    )) {
        foreach ($m as $row) {
            $codigo = trim(html_entity_decode($row[1], ENT_QUOTES | ENT_XML1, 'UTF-8'));
            $desc   = trim(html_entity_decode($row[2], ENT_QUOTES | ENT_XML1, 'UTF-8'));
            $formas[$codigo] = $desc;
        }
    }

    return ['ok' => true, 'formas' => $formas, 'raw' => $resp];
}

/**
 * Consulta las relaciones emisor-receptor disponibles en WSCT
 *
 * @param string $Token Token de autenticación AFIP
 * @param string $Sign Firma de autenticación AFIP
 * @param string $Cuit CUIT del contribuyente
 * @return array ['ok' => bool, 'relaciones' => array, 'raw' => string]
 */
function WSCT_ConsultarRelacionEmisorReceptor($Token, $Sign, $Cuit)
{
    $soapUrl = "https://serviciosjava.afip.gob.ar/wsct/CTService";

    $xml = '<?xml version="1.0" encoding="utf-8"?>
    <soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"
                      xmlns:cts="http://ar.gob.afip.wsct/CTService/">
        <soapenv:Header/>
        <soapenv:Body>
            <cts:consultarRelacionEmisorReceptorRequest>
                <authRequest>
                    <token>' . htmlspecialchars($Token, ENT_XML1, 'UTF-8') . '</token>
                    <sign>' . htmlspecialchars($Sign, ENT_XML1, 'UTF-8') . '</sign>
                    <cuitRepresentada>' . $Cuit . '</cuitRepresentada>
                </authRequest>
            </cts:consultarRelacionEmisorReceptorRequest>
        </soapenv:Body>
    </soapenv:Envelope>';

    $headers = [
        "Content-Type: text/xml; charset=utf-8",
        'SOAPAction: "http://ar.gob.afip.wsct/CTService/consultarRelacionEmisorReceptor"',
        "Content-Length: " . strlen($xml)
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $soapUrl,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $xml,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => true,
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);

    if (!$resp) {
        return ['ok' => false, 'relaciones' => [], 'raw' => null];
    }

    $relaciones = [];
    if (preg_match_all(
        '~<codigoDescripcion>\s*<codigo>(.*?)</codigo>\s*<descripcion>(.*?)</descripcion>\s*</codigoDescripcion>~si',
        $resp,
        $m,
        PREG_SET_ORDER
    )) {
        foreach ($m as $row) {
            $codigo = trim(html_entity_decode($row[1], ENT_QUOTES | ENT_XML1, 'UTF-8'));
            $desc   = trim(html_entity_decode($row[2], ENT_QUOTES | ENT_XML1, 'UTF-8'));
            $relaciones[$codigo] = $desc;
        }
    }

    return ['ok' => true, 'relaciones' => $relaciones, 'raw' => $resp];
}
