<?php

global $wpdb;
$table_name = $wpdb->prefix . "hotels_config";
$table_name_transactions = $wpdb->prefix . "hotels_transactions";

$new_date = date('Y-m-d');

$data_glob = $wpdb->get_results($wpdb->prepare("SELECT code_auth,client_id,client_secret,access_token,refresh_token from $table_name"));
foreach ($data_glob as $data_g) {
    $code_auth = $data_g->code_auth;
    $client_id_global = $data_g->client_id;
    $client_secret_global = $data_g->client_secret;
    $access_token_global = $data_g->access_token;
    $refresh_token_global = $data_g->refresh_token;
}

// define("CALLBACK_URL", "https://localhost/hotel/wp-admin/admin.php?page=transactions_list_dev");
define("CALLBACK_URL", "https://contable.penthouse1004.com/wp-admin/admin.php?page=transactions_list_dev");
define("AUTH_URL", "https://hotels.cloudbeds.com/api/v1.2/oauth");
define("ACCESS_TOKEN_URL", "https://hotels.cloudbeds.com/api/v1.2/access_token");
define("TRANSACTIONS_URL", "https://hotels.cloudbeds.com/api/v1.2/getTransactions");
define("GUEST_URL", "https://hotels.cloudbeds.com/api/v1.2/getGuest");
define("SENDPDF_URL", "https://hotels.cloudbeds.com/api/v1.2/postReservationDocument");
define("CLIENT_ID", $client_id_global);
define("CLIENT_SECRET", $client_secret_global);
define("SCOPE", ""); // optional

function transactions_list_dev() {
    
    global $wpdb, $table_name, $table_name_transactions, $code_auth, $access_token_global, $refresh_token_global;
    $id = '1';

    if(isset($_GET['code'])){

        if($code_auth == null){
    
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

            if($data_update){

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
    
    }else{

        if($refresh_token_global != null){

            if(isset($_GET['resultsFrom'])){

            }else{

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

    $url = add_query_arg( array(
        'action'    => 'foo_modal_box',
        'TB_iframe' => 'false',
        'width'     => '600',
        'height'    => '150'
    ), admin_url( 'admin.php' ) );
    
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
        display: flex !important; /* Forzamos flex cuando est®¢ activo */
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
    
    /* T®™tulo */
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
    </style>
    <body>
        <div class="wrap">
            <div id="overlay" style="display:none;">
                <div class="spinner"></div>
                <img src="https://contable.penthouse1004.com/wp-content/plugins/hotels/img/loading.gif" width="80" />
            </div>

            <h2>Panel de Configuraci√≥n Api</h2>
            <!-- <div class="notice notice-success is-dismissible">
                <p>Para mostrar el buscador web a los clientes, cree una p√°gina nueva y pegue el siguiente shortcode: <code><b>[show_search]</b></code></p>
            </div> -->
                <?php if (isset($message)): ?><div class="updated"><p><?php echo $message; ?></p></div><?php endif; ?>

            <div class="tablenav top">
                <div class="alignleft actions">
                    <!--<a class="button button-primary thickbox" href="<?php echo $url ?>">Importar</a>-->
                </div>
                <div class="alignright actions">
                    <!--<form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>?page=transactions_list_dev" id="theForm">-->
                        <?php if(isset($_GET['resultsFrom'])){ ?>

                            <?php if($_GET['resultsFrom'] != ''){ ?>

                                <select name="search_metpago" id="search_metpago" class="form-control">
                                    <?php if($_GET['metpago'] == 'debit'){ ?>
                                        <option value="debit" selected>D&eacute;bito</option>
                                        <option value="credit">Cr&eacute;dito</option>
                                    <?php }else{ ?>
                                        <option value="debit">D&eacute;bito</option>
                                        <option value="credit" selected>Cr&eacute;dito</option>
                                    <?php } ?>
                                </select>
                                <label><b>DESDE</b></label>
                                <input type="date" value="<?php echo date('Y-m-d', strtotime($_GET['resultsFrom'])) ?>" placeholder="Buscar por transoper.." name="search_desde" id="search_desde">
                                <label><b>HASTA</b></label>
                                <input type="date" value="<?php echo date('Y-m-d', strtotime($_GET['resultsTo'])) ?>" placeholder="Buscar por transoper.." name="search_hasta" id="search_hasta">
                                <label><b>ID RESERVA</b></label>
                                <input type="text" value="<?php echo $_GET['reservationID']; ?>" placeholder="Buscar por Nro. Reserva" name="search_reservaid" id="search_reservaid">

                            <?php }else{ ?>

                                <select name="search_metpago" id="search_metpago" class="form-control">
                                    <?php if($_GET['metpago'] == 'debit'){ ?>
                                        <option value="debit" selected>D&eacute;bito</option>
                                        <option value="credit">Cr&eacute;dito</option>
                                    <?php }else{ ?>
                                        <option value="debit">D&eacute;bito</option>
                                        <option value="credit" selected>Cr&eacute;dito</option>
                                    <?php } ?>
                                </select>
                                <label><b>DESDE</b></label>
                                <input type="date" placeholder="Buscar por transoper.." name="search_desde" id="search_desde">
                                <label><b>HASTA</b></label>
                                <input type="date" placeholder="Buscar por transoper.." name="search_hasta" id="search_hasta">
                                <label><b>ID RESERVA</b></label>
                                <input type="text" value="<?php echo $_GET['reservationID']; ?>" placeholder="Buscar por Nro. Reserva" name="search_reservaid" id="search_reservaid">

                            <?php } ?>

                        <?php }else{ ?>
                            <select name="search_metpago" id="search_metpago" class="form-control">
                                <option value="debit">D&eacute;bito</option>
                                <option value="credit" selected>Cr&eacute;dito</option>
                            </select>
                            <label><b>DESDE</b></label>
                            <input type="date" placeholder="Buscar por transoper.." name="search_desde" id="search_desde">
                            <label><b>HASTA</b></label>
                            <input type="date" placeholder="Buscar por transoper.." name="search_hasta" id="search_hasta">
                            <label><b>ID RESERVA</b></label>
                            <input type="text" placeholder="Buscar por Nro. Reserva" name="search_reservaid" id="search_reservaid">
                        <?php } ?>


                        <button type="button" class="button-primary" onClick="validateCamps()">Buscar</button>
                        <a class="button-secondary" href="<?php echo $_SERVER['PHP_SELF']; ?>?page=transactions_list_dev">Limpiar</a>
                    <!--</form>-->
                </div>
                <br class="clear">
            </div>
            <?php
                $pagenum = isset( $_GET['pagenum'] ) ? absint( $_GET['pagenum'] ) : 1;
                $limit = 20; // number of rows in page
                $offset = ( $pagenum - 1 ) * $limit;

                if(isset($_POST['search_desde'])){

                    $desde = $_POST['search_desde'];
                    $hasta = $_POST['search_hasta'];
                    $reservaid = $_POST['search_reservaid'];
                    $metpago = $_POST['search_metpago'];

                    $total = $wpdb->get_var( "SELECT COUNT(id) FROM $table_name_transactions WHERE DATE_FORMAT(transactionDateTime, '%Y-%m-%d') between '$desde' and '$hasta' and reservationID = '$reservaid' and transactionType = '$metpago'");
                    $num_of_pages = ceil( $total / $limit );
                    $rows = $wpdb->get_results("SELECT id,passportNumber,description,invoiceUrl,completeName,reservationID,transactionDateTime,amount,transactionType, DATE_FORMAT(transactionDateTime, '%Y-%m-%d') from $table_name_transactions WHERE DATE_FORMAT(transactionDateTime, '%Y-%m-%d') between '$desde' and '$hasta' and reservationID = '$reservaid' and transactionType = '$metpago' ORDER BY id DESC LIMIT $offset, $limit");
                    $page_links = paginate_links( array(
                    'base' => add_query_arg( 'pagenum', '%#%' ),
                    'format' => '',
                    'prev_text' => __( '&laquo;', 'text-domain' ),
                    'next_text' => __( '&raquo;', 'text-domain' ),
                    'total' => $num_of_pages,
                    'current' => $pagenum
                    ) );

                }else{

                    if(isset($_GET['resultsFrom'])){

                        $datefrom = $_GET['resultsFrom'];
                        $dateto = $_GET['resultsTo'];
                        $reservationID = $_GET['reservationID'];
                        $metpago = $_GET['metpago'];

                        //search
                        if (isset($_POST['search'])) {//si hay busqueda por numero de transoper

                            $toper = $_POST["search"];

                            if($datefrom == '' && $dateto == '' && $reservationID != '' && $metpago != ''){
                                $total = $wpdb->get_var( "SELECT COUNT(id) FROM $table_name_transactions WHERE reservationID = '$reservationID' and transactionType = '$metpago' ");
                                $rows = $wpdb->get_results("SELECT id,passportNumber,description,invoiceUrl,completeName,reservationID,transactionDateTime,amount,transactionType from $table_name_transactions WHERE reservationID = '$reservationID' and transactionType = '$metpago' ");
                            }elseif($datefrom != '' && $dateto != '' && $reservationID == '' && $metpago != ''){
                                $total = $wpdb->get_var( "SELECT COUNT(id), DATE_FORMAT(transactionDateTime, '%Y-%m-%d') FROM $table_name_transactions WHERE DATE_FORMAT(transactionDateTime, '%Y-%m-%d') between '$datefrom' and '$dateto' and transactionType = '$metpago'");
                                $rows = $wpdb->get_results("SELECT id,passportNumber,description,invoiceUrl,completeName,reservationID,transactionDateTime,amount,transactionType, DATE_FORMAT(transactionDateTime, '%Y-%m-%d') from $table_name_transactions WHERE DATE_FORMAT(transactionDateTime, '%Y-%m-%d') between '$datefrom' and '$dateto' and transactionType = '$metpago'");    
                            }elseif($datefrom == '' && $dateto == '' && $reservationID == '' && $metpago != ''){
                                $total = $wpdb->get_var( "SELECT COUNT(id), DATE_FORMAT(transactionDateTime, '%Y-%m-%d') FROM $table_name_transactions WHERE DATE_FORMAT(transactionDateTime, '%Y-%m-%d') = CURDATE() and transactionType = '$metpago'");
                                $rows = $wpdb->get_results("SELECT id,passportNumber,description,invoiceUrl,completeName,reservationID,transactionDateTime,amount,transactionType, DATE_FORMAT(transactionDateTime, '%Y-%m-%d') from $table_name_transactions WHERE DATE_FORMAT(transactionDateTime, '%Y-%m-%d') = CURDATE() and transactionType = '$metpago'");    
                            }else{

                            }

                            $num_of_pages = ceil( $total / $limit );
                            $page_links = paginate_links( array(
                            'base' => add_query_arg( 'pagenum', '%#%' ),
                            'format' => '',
                            'prev_text' => __( '&laquo;', 'text-domain' ),
                            'next_text' => __( '&raquo;', 'text-domain' ),
                            'total' => $num_of_pages,
                            'current' => $pagenum
                            ) );

                        }else{//sino hay ninguna busqueda

                            if($datefrom == '' && $dateto == '' && $reservationID != ''  && $metpago != ''){
                                $total = $wpdb->get_var( "SELECT COUNT(id) FROM $table_name_transactions WHERE reservationID = '$reservationID' and transactionType = '$metpago' ");
                                $rows = $wpdb->get_results("SELECT id,passportNumber,description,invoiceUrl,completeName,reservationID,transactionDateTime,amount,transactionType from $table_name_transactions WHERE reservationID = '$reservationID' and transactionType = '$metpago' ");
                            }elseif($datefrom != '' && $dateto != '' && $reservationID == ''  && $metpago != ''){
                                $total = $wpdb->get_var( "SELECT COUNT(id), DATE_FORMAT(transactionDateTime, '%Y-%m-%d') FROM $table_name_transactions WHERE DATE_FORMAT(transactionDateTime, '%Y-%m-%d') between '$datefrom' and '$dateto' and transactionType = '$metpago'");
                                $rows = $wpdb->get_results("SELECT id,passportNumber,description,invoiceUrl,completeName,reservationID,transactionDateTime,amount,transactionType, DATE_FORMAT(transactionDateTime, '%Y-%m-%d') from $table_name_transactions WHERE DATE_FORMAT(transactionDateTime, '%Y-%m-%d') between '$datefrom' and '$dateto' and transactionType = '$metpago' ORDER BY id DESC LIMIT $offset, $limit");    
                            }elseif($datefrom == '' && $dateto == '' && $reservationID == '' && $metpago != ''){
                                $total = $wpdb->get_var( "SELECT COUNT(id), DATE_FORMAT(transactionDateTime, '%Y-%m-%d') FROM $table_name_transactions WHERE DATE_FORMAT(transactionDateTime, '%Y-%m-%d') = CURDATE() and transactionType = '$metpago'");
                                $rows = $wpdb->get_results("SELECT id,passportNumber,description,invoiceUrl,completeName,reservationID,transactionDateTime,amount,transactionType, DATE_FORMAT(transactionDateTime, '%Y-%m-%d') from $table_name_transactions WHERE DATE_FORMAT(transactionDateTime, '%Y-%m-%d') = CURDATE() and transactionType = '$metpago'");    
                            }else{

                            }

                            $num_of_pages = ceil( $total / $limit );
                            $page_links = paginate_links( array(
                            'base' => add_query_arg( 'pagenum', '%#%' ),
                            'format' => '',
                            'prev_text' => __( '&laquo;', 'text-domain' ),
                            'next_text' => __( '&raquo;', 'text-domain' ),
                            'total' => $num_of_pages,
                            'current' => $pagenum
                            ) );

                        }


                    }else{
                        //search
                        if (isset($_POST['search'])) {//si hay busqueda por numero de transoper

                            $toper = $_POST["search"];

                            $total = $wpdb->get_var( "SELECT COUNT(id), DATE_FORMAT(transactionDateTime, '%Y-%m-%d') FROM $table_name_transactions WHERE DATE_FORMAT(transactionDateTime, '%Y-%m-%d') = CURDATE() and transactionType = 'credit'");
                            $num_of_pages = ceil( $total / $limit );
                            $rows = $wpdb->get_results("SELECT id,passportNumber,description,invoiceUrl,completeName,reservationID,transactionDateTime,amount,transactionType, DATE_FORMAT(transactionDateTime, '%Y-%m-%d') from $table_name_transactions WHERE DATE_FORMAT(transactionDateTime, '%Y-%m-%d') = CURDATE() and transactionType = 'credit'");
                            $page_links = paginate_links( array(
                            'base' => add_query_arg( 'pagenum', '%#%' ),
                            'format' => '',
                            'prev_text' => __( '&laquo;', 'text-domain' ),
                            'next_text' => __( '&raquo;', 'text-domain' ),
                            'total' => $num_of_pages,
                            'current' => $pagenum
                            ) );

                        }else{//sino hay ninguna busqueda

                            $total = $wpdb->get_var( "SELECT COUNT(id) FROM $table_name_transactions WHERE DATE_FORMAT(transactionDateTime, '%Y-%m-%d') = CURDATE() and transactionType = 'credit'");
                            $num_of_pages = ceil( $total / $limit );
                            $rows = $wpdb->get_results("SELECT id,passportNumber,description,invoiceUrl,completeName,reservationID,transactionDateTime,amount,transactionType, DATE_FORMAT(transactionDateTime, '%Y-%m-%d') from $table_name_transactions WHERE DATE_FORMAT(transactionDateTime, '%Y-%m-%d') = CURDATE() and transactionType = 'credit' ORDER BY id DESC LIMIT $offset, $limit");
                            $page_links = paginate_links( array(
                            'base' => add_query_arg( 'pagenum', '%#%' ),
                            'format' => '',
                            'prev_text' => __( '&laquo;', 'text-domain' ),
                            'next_text' => __( '&raquo;', 'text-domain' ),
                            'total' => $num_of_pages,
                            'current' => $pagenum
                            ) );

                        }
                    }

                }

            ?>
            <!--<table class='wp-list-table widefat fixed striped posts'>-->
            <!--    <tr>-->
                    <!--<th class="manage-column ss-list-width"><b>ID</b></th>-->
            <!--        <th class="manage-column ss-list-width"><b>Documento de la Persona</b></th>-->
            <!--        <th class="manage-column ss-list-width"><b>Hu√©sped</b></th>-->
            <!--        <th class="manage-column ss-list-width"><b>Nro. Reserva</b></th>-->
            <!--        <th class="manage-column ss-list-width"><b>Fecha</b></th>-->
            <!--        <th class="manage-column ss-list-width"><b>Monto</b></th>-->
            <!--        <th class="manage-column ss-list-width"><b>Descripci√≥n</b></th>-->
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
            <!--            <?php if(CheckNumber($row->amount) == 'Negative'){ ?></td>-->

            <!--                <td>-->
            <!--                    Monto Negativo-->
            <!--                </td>-->

            <!--            <?php }else{ ?></td>-->

            <!--                <?php if($row->invoiceUrl != NULL){ ?></td>-->

            <!--                    <td>-->
            <!--                        <button style="color: #22b162;border-color: #22b162;" type="button" onclick="genFacturar(<?php echo $row->id; ?>);" class="button button-secondary" id="<?php echo $row->id; ?>">Descargar</button>-->
            <!--                    </td>-->

            <!--                <?php }else{ ?></td>-->

            <!--                    <div style="display: inline-flex;gap: 4px;"> -->
            <!--                        <button type="button" onclick="editModal(<?php echo $row->id; ?>);" class="button button-secondary" id="editar">Editar</button>-->
            <!--                        <button type="button" onclick="genFacturar(<?php echo $row->id; ?>);" class="button button-secondary" id="<?php echo $row->id; ?>">FB</button>-->
            <!--                    </div>-->

            <!--                <?php } ?></td>-->

            <!--            <?php } ?></td>-->
            <!--        </tr>-->
            <!--    <?php } ?>-->
            <!--</table>-->
            
            <table class='wp-list-table widefat fixed striped posts'>
                <tr>
                    <th class="manage-column ss-list-width"><b>Documento de la Persona</b></th>
                    <th class="manage-column ss-list-width"><b>Hu&eacute;sped</b></th>
                    <th class="manage-column ss-list-width"><b>Nro. Reserva</b></th>
                    <th class="manage-column ss-list-width"><b>Fecha</b></th>
                    <th class="manage-column ss-list-width"><b>Monto</b></th>
                    <th class="manage-column ss-list-width"><b>Descripci&oacute;n</b></th>
                    <th class="manage-column ss-list-width"><b>Acciones</b></th>
                </tr>
                <?php foreach ($rows as $row) { ?>
                    <tr>
                        <td class="manage-column ss-list-width"><?php echo $row->passportNumber; ?></td>
                        <td class="manage-column ss-list-width"><?php echo $row->completeName; ?></td>
                        <td class="manage-column ss-list-width"><?php echo $row->reservationID; ?></td>
                        <td class="manage-column ss-list-width"><?php echo $row->transactionDateTime; ?></td>
                        <td class="manage-column ss-list-width"><?php echo $row->amount; ?></td>
                        <td class="manage-column ss-list-width"><?php echo $row->description; ?></td>
                        <td class="manage-column ss-list-width">
                            <?php if(CheckNumber($row->amount) == 'Negative') { ?>
                                <span style="color: red; font-weight: bold;">Monto Negativo</span>
                            <?php } else { ?>
                                <?php if($row->invoiceUrl != NULL) { ?>
                                    <button style="color: #22b162; border-color: #22b162;" type="button" onclick="genFacturar(<?php echo $row->id; ?>);" class="button button-secondary">
                                        Descargar Factura
                                    </button>
                                <?php } else { ?>
                                    <div style="display: inline-flex; gap: 4px;">
                                        <button type="button" onclick="editModal(<?php echo $row->id; ?>);" class="button button-secondary">Editar</button>
                                        <button type="button" onclick="openFacturaModal(<?php echo $row->id; ?>)" class="button button-primary">
                                            Generar Factura
                                        </button>
                                    </div>
                                <?php } ?>
                            <?php } ?>
                        </td>
                    </tr>
                <?php } ?>
                    <tr>
                        <!--<td class="manage-column ss-list-width"><?php echo $row->id; ?></td>-->
                        <td class="manage-column ss-list-width">44444444444</td>
                        <td class="manage-column ss-list-width">Osward</td>
                        <td class="manage-column ss-list-width">44444444444</td>
                        <td class="manage-column ss-list-width">2025</td>
                        <td class="manage-column ss-list-width">15000</td>
                        <td class="manage-column ss-list-width">credito</td>
                        <td>
                            <div style="display: inline-flex;gap: 4px;"> <button type="button" onclick="editModal(1);" class="button button-secondary" id="editar">Editar</button>
                                <!--<button type="button" onclick="genFacturarT(1);" class="button button-secondary" id="factura-t">FT</button>-->
                                <button type="button" onclick="openFacturaModal(1)" class="button button-primary">
                                    Generar Factura
                                </button>
                            </div>
                        </td>
                    </tr>
            </table>
            <div id="editModal" class="modal-overlay" data-current-id="">
                <div class="modal-content">
                    <h3 class="modal-title">Cambiar monto</h3>
                    <div class="modal-body">
                        <input type="number" value="0" class="modal-input" id="montoInput"> </div>
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
                if ( $page_links ) {
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
    
        newFb.onclick = () => { closeFacturaModal(); genFacturar(id); };
        newFt.onclick = () => { closeFacturaModal(); genFacturarT(id); };
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
    
    <?php if($code_auth == null){ ?>
        <script type="text/javascript">
        jQuery(document).ready(function($){
        
            initAuthCodeFunctionToken();
            
        });
        </script>
    <?php }else{ ?>

        <?php if(isset($_GET['code'])){ ?>

            <script type="text/javascript">
            jQuery(document).ready(function($){
            
                initLoadRecordsTransactions();
                
            });
            </script>

        <?php }else{ ?>

            <script type="text/javascript">
            jQuery(document).ready(function($){
            
                checkGetTransactionsNow();
                
            });
            </script>

        <?php } ?>           

    <?php } ?>
    
<?php
}