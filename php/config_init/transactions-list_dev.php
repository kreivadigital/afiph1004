<?php

global $wpdb;
$table_name = $wpdb->prefix . "hotels_config";
$table_name_transactions = $wpdb->prefix . "hotels_transactions";

// URL base del plugin para assets
$plugin_url = plugin_dir_url(dirname(dirname(__FILE__)));

$new_date = date('Y-m-d');

$data_glob = $wpdb->get_results("SELECT code_auth,client_id,client_secret,access_token,refresh_token FROM $table_name");
foreach ($data_glob as $data_g) {
    $code_auth = $data_g->code_auth;
    $client_id_global = $data_g->client_id;
    $client_secret_global = $data_g->client_secret;
    $access_token_global = $data_g->access_token;
    $refresh_token_global = $data_g->refresh_token;
}

// URL de callback dinamica basada en el sitio actual - usando defined() para evitar redefiniciones
defined('CALLBACK_URL') || define('CALLBACK_URL', admin_url('admin.php?page=transactions_list_dev'));
defined('AUTH_URL') || define('AUTH_URL', 'https://hotels.cloudbeds.com/api/v1.2/oauth');
defined('ACCESS_TOKEN_URL') || define('ACCESS_TOKEN_URL', 'https://hotels.cloudbeds.com/api/v1.2/access_token');
defined('TRANSACTIONS_URL') || define('TRANSACTIONS_URL', 'https://hotels.cloudbeds.com/api/v1.2/getTransactions');
defined('GUEST_URL') || define('GUEST_URL', 'https://hotels.cloudbeds.com/api/v1.2/getGuest');
defined('SENDPDF_URL') || define('SENDPDF_URL', 'https://hotels.cloudbeds.com/api/v1.2/postReservationDocument');
defined('CLIENT_ID') || define('CLIENT_ID', $client_id_global ?? '');
defined('CLIENT_SECRET') || define('CLIENT_SECRET', $client_secret_global ?? '');
defined('SCOPE') || define('SCOPE', ''); // optional

function transactions_list_dev()
{

    global $wpdb, $table_name, $table_name_transactions, $code_auth, $access_token_global, $refresh_token_global, $plugin_url;
    $id = '1';

    if (isset($_GET['code'])) {

        if ($code_auth == null) {

            $code = $_GET['code'];

            $data_token = getToken($code);
            $access_token = $data_token->access_token;
            $refresh_token = $data_token->refresh_token;

            $data_update = $wpdb->update(
                $table_name, //table
                array('code_auth' => $code, 'access_token' => $access_token, 'refresh_token' => $refresh_token), //data
                array('id' => $id), //where
                array('%s'), //data format
                array('%s') //where format
            );

            if ($data_update) {

                // $resource = getTransactions($access_token);
                // $data_save = saveTransactions($resource);

                // if($data_save){//redirect to home listing and load guest data
                //     echo "
                //         <script type='text/javascript'>
                //             window.location.href='".CALLBACK_URL."';
                //         </script>
                //     ";
                // }

            }
        }
    } else {

        if ($refresh_token_global != null) {

            if (isset($_GET['resultsFrom'])) {
            } else {

                $data_refresh_token = refreshToken($refresh_token_global);
                $new_access_token = $data_refresh_token->access_token;
                $new_refresh_token = $data_refresh_token->refresh_token;

                $data_refresh_update = $wpdb->update(
                    $table_name, //table
                    array('access_token' => $new_access_token, 'refresh_token' => $new_refresh_token), //data
                    array('id' => $id), //where
                    array('%s'), //data format
                    array('%s') //where format
                );
            }
        }
    }

    $url = add_query_arg(array(
        'action'    => 'foo_modal_box',
        'TB_iframe' => 'false',
        'width'     => '600',
        'height'    => '150'
    ), admin_url('admin.php'));

?>

    <style>
        /* MODAL CENTRADO PERFECTO - SOBRE TODO */
        #facturaModal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0, 0, 0, 0.75);
            z-index: 99999;
            justify-content: center;
            align-items: center;
            padding: 20px;
            box-sizing: border-box;
            overflow: auto;
        }

        #facturaModal.active {
            display: flex !important;
            /* Forzamos flex cuando est�� activo */
        }

        #facturaModal .modal-content {
            background: #fff;
            border-radius: 12px;
            padding: 24px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.25);
            animation: modalPop 0.3s ease-out;
            position: relative;
            margin: auto;
        }

        @keyframes modalPop {
            from {
                opacity: 0;
                transform: scale(0.9) translateY(-20px);
            }

            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        /* T��tulo */
        #facturaModal .modal-title {
            margin: 0 0 16px 0;
            font-size: 19px;
            font-weight: 600;
            text-align: center;
            color: #1d2327;
        }

        /* Botones FB y FT */
        #facturaModal #btn-fb,
        #facturaModal #btn-ft {
            flex: 1;
            padding: 12px;
            font-size: 15px;
            font-weight: 600;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
        }

        #btn-fb {
            background: #0073aa;
            color: white;
        }

        #btn-fb:hover {
            background: #005a87;
            transform: translateY(-1px);
        }

        #btn-ft {
            background: #d63638;
            color: white;
        }

        #btn-ft:hover {
            background: #b32d2e;
            transform: translateY(-1px);
        }

        #facturaModal .modal-actions {
            margin-top: 20px;
            text-align: right;
        }

        /* Responsive */
        @media (max-width: 782px) {
            #facturaModal {
                padding: 15px;
            }

            #facturaModal .modal-content {
                padding: 20px;
            }

            #facturaModal #btn-fb,
            #facturaModal #btn-ft {
                font-size: 14px;
                padding: 11px;
            }
        }

        /* ========== TABLA RESPONSIVE ========== */
        .sah-transactions-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .sah-transactions-table th,
        .sah-transactions-table td {
            padding: 10px 8px;
            text-align: left;
            vertical-align: middle;
            border-bottom: 1px solid #e1e1e1;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        .sah-transactions-table th {
            background: #f9f9f9;
            font-weight: 600;
            font-size: 13px;
            white-space: nowrap;
        }
        /* Anchos de columnas */
        .sah-transactions-table .col-doc { width: 12%; }
        .sah-transactions-table .col-guest { width: 18%; }
        .sah-transactions-table .col-reservation { width: 10%; }
        .sah-transactions-table .col-date { width: 14%; }
        .sah-transactions-table .col-amount { width: 10%; text-align: right; }
        .sah-transactions-table .col-desc { width: 18%; }
        .sah-transactions-table .col-actions { width: 18%; text-align: center; }

        /* Truncado con elipsis */
        .sah-truncate {
            display: block;
            max-width: 100%;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .sah-truncate:hover {
            white-space: normal;
            word-break: break-word;
            background: #fff3cd;
            position: relative;
            z-index: 1;
        }
        /* Monto con formato */
        .sah-amount {
            font-family: 'Consolas', 'Monaco', monospace;
            font-weight: 600;
            text-align: right;
            display: block;
        }
        .sah-amount.positive { color: #155724; }
        .sah-amount.negative { color: #dc3232; }

        /* Botones de acciones */
        .sah-actions {
            display: flex;
            gap: 4px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .sah-actions .button {
            font-size: 12px;
            padding: 4px 8px;
            min-height: 28px;
            line-height: 1.5;
        }
        .btn-download {
            color: #22b162 !important;
            border-color: #22b162 !important;
        }
        .btn-download:hover {
            background: #22b162 !important;
            color: #fff !important;
        }

        /* Mensaje cuando no hay facturas */
        .sah-no-invoice {
            color: #dc3232;
            font-weight: bold;
            font-size: 12px;
        }

        /* Responsive para pantallas pequenas */
        @media (max-width: 1200px) {
            .sah-transactions-table .col-desc { display: none; }
            .sah-transactions-table th:nth-child(6),
            .sah-transactions-table td:nth-child(6) { display: none; }
        }
        @media (max-width: 900px) {
            .sah-transactions-table .col-date { width: 18%; }
            .sah-transactions-table .col-doc { width: 15%; }
        }

        /* Filtros responsive */
        .sah-filters {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
        }
        .sah-filters label {
            font-weight: 600;
            font-size: 12px;
        }
        .sah-filters input[type="date"],
        .sah-filters input[type="text"],
        .sah-filters select {
            padding: 4px 8px;
            font-size: 13px;
        }
        @media (max-width: 782px) {
            .sah-filters {
                flex-direction: column;
                align-items: stretch;
            }
            .sah-filters input,
            .sah-filters select {
                width: 100%;
            }
        }
    </style>

    <body>
        <div class="wrap">
            <div id="overlay" style="display:none;">
                <div class="spinner"></div>
                <img src="<?php echo esc_url($plugin_url . 'img/loading.gif'); ?>" width="80" />
            </div>

            <h2>Panel de Configuracion Api - Listado de Transacciones</h2>

            <?php
            // Mostrar indicador de ambiente AFIP
            $afip_config = function_exists('get_afip_config') ? get_afip_config() : null;
            if ($afip_config):
                $is_test = $afip_config['is_test'];
                $env_class = $is_test ? 'notice-info' : 'notice-warning';
                $env_icon = $is_test ? '🧪' : '🔴';
                $env_label = $is_test ? 'HOMOLOGACION (Testing)' : 'PRODUCCION';
                $env_desc = $is_test
                    ? 'Las facturas generadas NO son validas fiscalmente.'
                    : 'Las facturas generadas son REALES con CAE valido.';
            ?>
            <div class="notice <?php echo $env_class; ?>" style="padding: 10px 15px; display: flex; align-items: center; gap: 15px;">
                <span style="font-size: 24px;"><?php echo $env_icon; ?></span>
                <div>
                    <strong>Ambiente AFIP: <?php echo $env_label; ?></strong>
                    <br>
                    <small><?php echo $env_desc; ?> - CUIT: <?php echo esc_html($afip_config['cuit']); ?></small>
                </div>
                <a href="<?php echo admin_url('admin.php?page=config_list'); ?>" class="button button-small" style="margin-left: auto;">
                    Cambiar Ambiente
                </a>
            </div>
            <?php endif; ?>

            <?php if (isset($message)): ?><div class="updated">
                    <p><?php echo $message; ?></p>
                </div><?php endif; ?>

            <div class="tablenav top">
                <div class="alignleft actions">
                    <!--<a class="button button-primary thickbox" href="<?php echo $url ?>">Importar</a>-->
                </div>
                <div class="alignright actions">
                    <div class="sah-filters">
                    <?php
                    // Determinar valores actuales de los filtros
                    $current_metpago = isset($_GET['metpago']) ? $_GET['metpago'] : 'credit';
                    $current_desde = isset($_GET['resultsFrom']) && $_GET['resultsFrom'] != '' ? date('Y-m-d', strtotime($_GET['resultsFrom'])) : '';
                    $current_hasta = isset($_GET['resultsTo']) && $_GET['resultsTo'] != '' ? date('Y-m-d', strtotime($_GET['resultsTo'])) : '';
                    $current_reservaid = isset($_GET['reservationID']) ? $_GET['reservationID'] : '';
                    ?>
                        <select name="search_metpago" id="search_metpago">
                            <option value="debit" <?php selected($current_metpago, 'debit'); ?>>D&eacute;bito</option>
                            <option value="credit" <?php selected($current_metpago, 'credit'); ?>>Cr&eacute;dito</option>
                        </select>
                        <label>Desde</label>
                        <input type="date" value="<?php echo esc_attr($current_desde); ?>" name="search_desde" id="search_desde">
                        <label>Hasta</label>
                        <input type="date" value="<?php echo esc_attr($current_hasta); ?>" name="search_hasta" id="search_hasta">
                        <label>Reserva</label>
                        <input type="text" value="<?php echo esc_attr($current_reservaid); ?>" placeholder="Nro. Reserva" name="search_reservaid" id="search_reservaid" style="width: 120px;">
                        <button type="button" class="button button-primary" onClick="validateCamps()">Buscar</button>
                        <a class="button button-secondary" href="<?php echo admin_url('admin.php?page=transactions_list_dev'); ?>">Limpiar</a>
                    </div>
                </div>
                <br class="clear">
            </div>
            <?php
            $pagenum = isset($_GET['pagenum']) ? absint($_GET['pagenum']) : 1;
            $limit = 20; // number of rows in page
            $offset = ($pagenum - 1) * $limit;

            // Inicializar $rows como array vacio para evitar errores
            $rows = array();
            $total = 0;
            $num_of_pages = 0;

            if (isset($_POST['search_desde'])) {

                $desde = $_POST['search_desde'];
                $hasta = $_POST['search_hasta'];
                $reservaid = $_POST['search_reservaid'];
                $metpago = $_POST['search_metpago'];

                $total = $wpdb->get_var("SELECT COUNT(id) FROM $table_name_transactions WHERE DATE_FORMAT(transactionDateTime, '%Y-%m-%d') between '$desde' and '$hasta' and reservationID = '$reservaid' and transactionType = '$metpago'");
                $num_of_pages = ceil($total / $limit);
                $rows = $wpdb->get_results("SELECT id,passportNumber,description,invoiceUrl,completeName,reservationID,transactionDateTime,amount,transactionType, DATE_FORMAT(transactionDateTime, '%Y-%m-%d') from $table_name_transactions WHERE DATE_FORMAT(transactionDateTime, '%Y-%m-%d') between '$desde' and '$hasta' and reservationID = '$reservaid' and transactionType = '$metpago' ORDER BY id DESC LIMIT $offset, $limit");
                $page_links = paginate_links(array(
                    'base' => add_query_arg('pagenum', '%#%'),
                    'format' => '',
                    'prev_text' => __('&laquo;', 'text-domain'),
                    'next_text' => __('&raquo;', 'text-domain'),
                    'total' => $num_of_pages,
                    'current' => $pagenum
                ));
            } else {

                if (isset($_GET['resultsFrom'])) {

                    $datefrom = $_GET['resultsFrom'];
                    $dateto = $_GET['resultsTo'];
                    $reservationID = $_GET['reservationID'];
                    $metpago = $_GET['metpago'];

                    //search
                    if (isset($_POST['search'])) { //si hay busqueda por numero de transoper

                        $toper = $_POST["search"];

                        if ($datefrom == '' && $dateto == '' && $reservationID != '' && $metpago != '') {
                            $total = $wpdb->get_var("SELECT COUNT(id) FROM $table_name_transactions WHERE reservationID = '$reservationID' and transactionType = '$metpago' ");
                            $rows = $wpdb->get_results("SELECT id,passportNumber,description,invoiceUrl,completeName,reservationID,transactionDateTime,amount,transactionType from $table_name_transactions WHERE reservationID = '$reservationID' and transactionType = '$metpago' ");
                        } elseif ($datefrom != '' && $dateto != '' && $reservationID == '' && $metpago != '') {
                            $total = $wpdb->get_var("SELECT COUNT(id), DATE_FORMAT(transactionDateTime, '%Y-%m-%d') FROM $table_name_transactions WHERE DATE_FORMAT(transactionDateTime, '%Y-%m-%d') between '$datefrom' and '$dateto' and transactionType = '$metpago'");
                            $rows = $wpdb->get_results("SELECT id,passportNumber,description,invoiceUrl,completeName,reservationID,transactionDateTime,amount,transactionType, DATE_FORMAT(transactionDateTime, '%Y-%m-%d') from $table_name_transactions WHERE DATE_FORMAT(transactionDateTime, '%Y-%m-%d') between '$datefrom' and '$dateto' and transactionType = '$metpago'");
                        } elseif ($datefrom == '' && $dateto == '' && $reservationID == '' && $metpago != '') {
                            $total = $wpdb->get_var("SELECT COUNT(id), DATE_FORMAT(transactionDateTime, '%Y-%m-%d') FROM $table_name_transactions WHERE DATE_FORMAT(transactionDateTime, '%Y-%m-%d') = CURDATE() and transactionType = '$metpago'");
                            $rows = $wpdb->get_results("SELECT id,passportNumber,description,invoiceUrl,completeName,reservationID,transactionDateTime,amount,transactionType, DATE_FORMAT(transactionDateTime, '%Y-%m-%d') from $table_name_transactions WHERE DATE_FORMAT(transactionDateTime, '%Y-%m-%d') = CURDATE() and transactionType = '$metpago'");
                        } else {
                        }

                        $num_of_pages = ceil($total / $limit);
                        $page_links = paginate_links(array(
                            'base' => add_query_arg('pagenum', '%#%'),
                            'format' => '',
                            'prev_text' => __('&laquo;', 'text-domain'),
                            'next_text' => __('&raquo;', 'text-domain'),
                            'total' => $num_of_pages,
                            'current' => $pagenum
                        ));
                    } else { //sino hay ninguna busqueda

                        if ($datefrom == '' && $dateto == '' && $reservationID != ''  && $metpago != '') {
                            $total = $wpdb->get_var("SELECT COUNT(id) FROM $table_name_transactions WHERE reservationID = '$reservationID' and transactionType = '$metpago' ");
                            $rows = $wpdb->get_results("SELECT id,passportNumber,description,invoiceUrl,completeName,reservationID,transactionDateTime,amount,transactionType from $table_name_transactions WHERE reservationID = '$reservationID' and transactionType = '$metpago' ");
                        } elseif ($datefrom != '' && $dateto != '' && $reservationID == ''  && $metpago != '') {
                            $total = $wpdb->get_var("SELECT COUNT(id), DATE_FORMAT(transactionDateTime, '%Y-%m-%d') FROM $table_name_transactions WHERE DATE_FORMAT(transactionDateTime, '%Y-%m-%d') between '$datefrom' and '$dateto' and transactionType = '$metpago'");
                            $rows = $wpdb->get_results("SELECT id,passportNumber,description,invoiceUrl,completeName,reservationID,transactionDateTime,amount,transactionType, DATE_FORMAT(transactionDateTime, '%Y-%m-%d') from $table_name_transactions WHERE DATE_FORMAT(transactionDateTime, '%Y-%m-%d') between '$datefrom' and '$dateto' and transactionType = '$metpago' ORDER BY id DESC LIMIT $offset, $limit");
                        } elseif ($datefrom == '' && $dateto == '' && $reservationID == '' && $metpago != '') {
                            $total = $wpdb->get_var("SELECT COUNT(id), DATE_FORMAT(transactionDateTime, '%Y-%m-%d') FROM $table_name_transactions WHERE DATE_FORMAT(transactionDateTime, '%Y-%m-%d') = CURDATE() and transactionType = '$metpago'");
                            $rows = $wpdb->get_results("SELECT id,passportNumber,description,invoiceUrl,completeName,reservationID,transactionDateTime,amount,transactionType, DATE_FORMAT(transactionDateTime, '%Y-%m-%d') from $table_name_transactions WHERE DATE_FORMAT(transactionDateTime, '%Y-%m-%d') = CURDATE() and transactionType = '$metpago'");
                        } else {
                        }

                        $num_of_pages = ceil($total / $limit);
                        $page_links = paginate_links(array(
                            'base' => add_query_arg('pagenum', '%#%'),
                            'format' => '',
                            'prev_text' => __('&laquo;', 'text-domain'),
                            'next_text' => __('&raquo;', 'text-domain'),
                            'total' => $num_of_pages,
                            'current' => $pagenum
                        ));
                    }
                } else {
                    //search
                    if (isset($_POST['search'])) { //si hay busqueda por numero de transoper

                        $toper = $_POST["search"];

                        $total = $wpdb->get_var("SELECT COUNT(id), DATE_FORMAT(transactionDateTime, '%Y-%m-%d') FROM $table_name_transactions WHERE DATE_FORMAT(transactionDateTime, '%Y-%m-%d') = CURDATE() and transactionType = 'credit'");
                        $num_of_pages = ceil($total / $limit);
                        $rows = $wpdb->get_results("SELECT id,passportNumber,description,invoiceUrl,completeName,reservationID,transactionDateTime,amount,transactionType, DATE_FORMAT(transactionDateTime, '%Y-%m-%d') from $table_name_transactions WHERE DATE_FORMAT(transactionDateTime, '%Y-%m-%d') = CURDATE() and transactionType = 'credit'");
                        $page_links = paginate_links(array(
                            'base' => add_query_arg('pagenum', '%#%'),
                            'format' => '',
                            'prev_text' => __('&laquo;', 'text-domain'),
                            'next_text' => __('&raquo;', 'text-domain'),
                            'total' => $num_of_pages,
                            'current' => $pagenum
                        ));
                    } else { //sino hay ninguna busqueda - mostrar todas las transacciones credit

                        $total = $wpdb->get_var("SELECT COUNT(id) FROM $table_name_transactions WHERE transactionType = 'credit'");
                        $num_of_pages = ceil($total / $limit);
                        $rows = $wpdb->get_results("SELECT id,passportNumber,description,invoiceUrl,completeName,reservationID,transactionDateTime,amount,transactionType, DATE_FORMAT(transactionDateTime, '%Y-%m-%d') from $table_name_transactions WHERE transactionType = 'credit' ORDER BY id DESC LIMIT $offset, $limit");
                        $page_links = paginate_links(array(
                            'base' => add_query_arg('pagenum', '%#%'),
                            'format' => '',
                            'prev_text' => __('&laquo;', 'text-domain'),
                            'next_text' => __('&raquo;', 'text-domain'),
                            'total' => $num_of_pages,
                            'current' => $pagenum
                        ));
                    }
                }
            }

            ?>
            <!--<table class='wp-list-table widefat fixed striped posts'>-->
            <!--    <tr>-->
            <!--<th class="manage-column ss-list-width"><b>ID</b></th>-->
            <!--        <th class="manage-column ss-list-width"><b>Documento de la Persona</b></th>-->
            <!--        <th class="manage-column ss-list-width"><b>Huésped</b></th>-->
            <!--        <th class="manage-column ss-list-width"><b>Nro. Reserva</b></th>-->
            <!--        <th class="manage-column ss-list-width"><b>Fecha</b></th>-->
            <!--        <th class="manage-column ss-list-width"><b>Monto</b></th>-->
            <!--        <th class="manage-column ss-list-width"><b>Descripción</b></th>-->
            <!--        <th class="manage-column ss-list-width"><b>Acciones</b></th>-->
            <!--    </tr>-->
            <!--    <?php foreach ($rows as $row) { ?>-->
            <!--        <tr>-->
            <!--<td class="manage-column ss-list-width"><?php echo $row->id; ?></td>-->
            <!--            <td class="manage-column ss-list-width"><?php echo $row->passportNumber; ?></td>-->
            <!--            <td class="manage-column ss-list-width"><?php echo $row->completeName; ?></td>-->
            <!--            <td class="manage-column ss-list-width"><?php echo $row->reservationID; ?></td>-->
            <!--            <td class="manage-column ss-list-width"><?php echo $row->transactionDateTime; ?></td>-->
            <!--            <td class="manage-column ss-list-width"><?php echo $row->amount; ?></td>-->
            <!--            <td class="manage-column ss-list-width"><?php echo $row->description; ?></td>-->
            <!--            <?php if (CheckNumber($row->amount) == 'Negative') { ?></td>-->

            <!--                <td>-->
            <!--                    Monto Negativo-->
            <!--                </td>-->

            <!--            <?php } else { ?></td>-->

            <!--                <?php if ($row->invoiceUrl != NULL) { ?></td>-->

            <!--                    <td>-->
            <!--                        <button style="color: #22b162;border-color: #22b162;" type="button" onclick="genFacturar(<?php echo $row->id; ?>);" class="button button-secondary" id="<?php echo $row->id; ?>">Descargar</button>-->
            <!--                    </td>-->

            <!--                <?php } else { ?></td>-->

            <!--                    <div style="display: inline-flex;gap: 4px;"> -->
            <!--                        <button type="button" onclick="editModal(<?php echo $row->id; ?>);" class="button button-secondary" id="editar">Editar</button>-->
            <!--                        <button type="button" onclick="genFacturar(<?php echo $row->id; ?>);" class="button button-secondary" id="<?php echo $row->id; ?>">FB</button>-->
            <!--                    </div>-->

            <!--                <?php } ?></td>-->

            <!--            <?php } ?></td>-->
            <!--        </tr>-->
            <!--    <?php } ?>-->
            <!--</table>-->

            <table class="sah-transactions-table wp-list-table widefat striped">
                <thead>
                    <tr>
                        <th class="col-doc">Documento</th>
                        <th class="col-guest">Hu&eacute;sped</th>
                        <th class="col-reservation">Reserva</th>
                        <th class="col-date">Fecha</th>
                        <th class="col-amount">Monto</th>
                        <th class="col-desc">Descripci&oacute;n</th>
                        <th class="col-actions">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row) {
                    $is_negative = CheckNumber($row->amount) == 'Negative';
                    $amount_class = $is_negative ? 'negative' : 'positive';
                ?>
                    <tr>
                        <td class="col-doc">
                            <span class="sah-truncate" title="<?php echo esc_attr($row->passportNumber); ?>">
                                <?php echo esc_html($row->passportNumber); ?>
                            </span>
                        </td>
                        <td class="col-guest">
                            <span class="sah-truncate" title="<?php echo esc_attr($row->completeName); ?>">
                                <?php echo esc_html($row->completeName); ?>
                            </span>
                        </td>
                        <td class="col-reservation">
                            <?php echo esc_html($row->reservationID); ?>
                        </td>
                        <td class="col-date">
                            <?php echo esc_html(date('d/m/Y H:i', strtotime($row->transactionDateTime))); ?>
                        </td>
                        <td class="col-amount">
                            <span class="sah-amount <?php echo $amount_class; ?>">
                                $<?php echo esc_html(number_format((float)$row->amount, 2, ',', '.')); ?>
                            </span>
                        </td>
                        <td class="col-desc">
                            <span class="sah-truncate" title="<?php echo esc_attr($row->description); ?>">
                                <?php echo esc_html($row->description); ?>
                            </span>
                        </td>
                        <td class="col-actions">
                            <?php if ($is_negative) { ?>
                                <span class="sah-no-invoice">Monto Negativo</span>
                            <?php } else { ?>
                                <?php if ($row->invoiceUrl != NULL) { ?>
                                    <div class="sah-actions">
                                        <button type="button" onclick="genFacturar(<?php echo $row->id; ?>);" class="button btn-download">
                                            Descargar
                                        </button>
                                    </div>
                                <?php } else { ?>
                                    <div class="sah-actions">
                                        <button type="button" onclick="editModal(<?php echo $row->id; ?>);" class="button button-secondary">Editar</button>
                                        <button type="button" onclick="openFacturaModal(<?php echo $row->id; ?>)" class="button button-primary">Facturar</button>
                                    </div>
                                <?php } ?>
                            <?php } ?>
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
            <div id="editModal" class="modal-overlay" data-current-id="">
                <div class="modal-content">
                    <h3 class="modal-title">Cambiar monto</h3>
                    <div class="modal-body">
                        <input type="number" value="0" class="modal-input" id="montoInput">
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="button button-tertiary" onclick="closeModal()">Cancelar</button>
                        <button type="button" class="button button-primary" onclick="guardarMonto()">Guardar</button>
                    </div>
                </div>
            </div>

            <!-- Modal para elegir tipo de factura -->
            <div id="facturaModal" class="modal-overlay" style="display: none;">
                <div class="modal-content">
                    <h3 class="modal-title">Generar Factura</h3>
                    <div class="modal-body">
                        <p><strong>Transacci&oacute;n ID:</strong> <span id="modal-transaction-id" style="font-weight: bold; color: #0073aa;">-</span></p>
                        <p style="margin: 16px 0 8px; font-weight: 600;">Seleccione el tipo de factura:</p>
                        <div style="display: flex; gap: 12px;">
                            <button type="button" id="btn-fb" class="button">
                                FB (Factura B)
                            </button>
                            <button type="button" id="btn-ft" class="button">
                                FT (Factura T)
                            </button>
                        </div>
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="button button-tertiary" onclick="closeFacturaModal()">
                            Cancelar
                        </button>
                    </div>
                </div>
            </div>

            <?php
            if ($page_links) {
                echo '<div class="tablenav"><div class="tablenav-pages" style="margin: 1em 0">' . $page_links . '</div></div>';
            }
            ?>
        </div>
    </body>

    <script type="text/javascript">
        let currentTransactionId = null;

        function openFacturaModal(id) {
            currentTransactionId = id;
            document.getElementById('modal-transaction-id').textContent = id;

            const modal = document.getElementById('facturaModal');
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';

            // Reasignar eventos (evita duplicados)
            const btnFb = document.getElementById('btn-fb');
            const btnFt = document.getElementById('btn-ft');

            // Clonar para limpiar eventos previos
            const newFb = btnFb.cloneNode(true);
            const newFt = btnFt.cloneNode(true);
            btnFb.parentNode.replaceChild(newFb, btnFb);
            btnFt.parentNode.replaceChild(newFt, btnFt);

            newFb.onclick = () => {
                closeFacturaModal();
                genFacturar(id);
            };
            newFt.onclick = () => {
                closeFacturaModal();
                genFacturarT(id);
            };
        }

        function closeFacturaModal() {
            const modal = document.getElementById('facturaModal');
            modal.classList.remove('active');
            document.body.style.overflow = 'auto';
            currentTransactionId = null;
        }

        // Cerrar al hacer clic fuera
        document.getElementById('facturaModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeFacturaModal();
            }
        });
    </script>

    <?php if ($code_auth == null) { ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {

                initAuthCodeFunctionToken();

            });
        </script>
    <?php } else { ?>

        <?php if (isset($_GET['code'])) { ?>

            <script type="text/javascript">
                jQuery(document).ready(function($) {

                    initLoadRecordsTransactions();

                });
            </script>

        <?php } else { ?>

            <script type="text/javascript">
                jQuery(document).ready(function($) {

                    checkGetTransactionsNow();

                });
            </script>

        <?php } ?>

    <?php } ?>

<?php
}
