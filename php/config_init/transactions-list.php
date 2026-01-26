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
defined('CALLBACK_URL') || define('CALLBACK_URL', admin_url('admin.php?page=transactions_list'));
defined('AUTH_URL') || define('AUTH_URL', 'https://hotels.cloudbeds.com/api/v1.2/oauth');
defined('ACCESS_TOKEN_URL') || define('ACCESS_TOKEN_URL', 'https://hotels.cloudbeds.com/api/v1.2/access_token');
defined('TRANSACTIONS_URL') || define('TRANSACTIONS_URL', 'https://hotels.cloudbeds.com/api/v1.2/getTransactions');
defined('GUEST_URL') || define('GUEST_URL', 'https://hotels.cloudbeds.com/api/v1.2/getGuest');
defined('SENDPDF_URL') || define('SENDPDF_URL', 'https://hotels.cloudbeds.com/api/v1.2/postReservationDocument');
defined('CLIENT_ID') || define('CLIENT_ID', $client_id_global ?? '');
defined('CLIENT_SECRET') || define('CLIENT_SECRET', $client_secret_global ?? '');
defined('SCOPE') || define('SCOPE', ''); // optional

function transactions_list()
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
        /* MODAL FACTURA */
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

        #facturaModal .modal-title {
            margin: 0 0 16px 0;
            font-size: 19px;
            font-weight: 600;
            text-align: center;
            color: #1d2327;
        }

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

        #facturaModal #btn-fb {
            background: #0073aa;
            color: white;
        }

        #facturaModal #btn-fb:hover {
            background: #005a87;
            transform: translateY(-1px);
        }

        #facturaModal #btn-ft {
            background: #d63638;
            color: white;
        }

        #facturaModal #btn-ft:hover {
            background: #b32d2e;
            transform: translateY(-1px);
        }

        #facturaModal .modal-actions {
            margin-top: 20px;
            text-align: right;
        }
    </style>

    <body>
        <div class="wrap">
            <div id="overlay" style="display:none;">
                <div class="spinner"></div>
                <img src="<?php echo esc_url($plugin_url . 'img/loading.gif'); ?>" width="80" />
            </div>

            <h2>Panel de Configuración Api</h2>
            <!-- <div class="notice notice-success is-dismissible">
                <p>Para mostrar el buscador web a los clientes, cree una página nueva y pegue el siguiente shortcode: <code><b>[show_search]</b></code></p>
            </div> -->
            <?php if (isset($message)): ?><div class="updated">
                    <p><?php echo $message; ?></p>
                </div><?php endif; ?>

            <div class="tablenav top">
                <div class="alignleft actions">
                    <!--<a class="button button-primary thickbox" href="<?php echo $url ?>">Importar</a>-->
                </div>
                <div class="alignright actions" style="display: flex; align-items: flex-end; gap: 10px;">
                    <?php if (isset($_GET['resultsFrom'])) { ?>

                        <?php if ($_GET['resultsFrom'] != '') { ?>

                            <div style="display: flex; flex-direction: column;">
                                <label style="margin-bottom: 4px;"><b>TIPO DE PAGO</b></label>
                                <select name="search_metpago" id="search_metpago" class="form-control">
                                    <option value="all" <?php echo ($_GET['metpago'] == 'all' || $_GET['metpago'] == '') ? 'selected' : ''; ?>>Ambos</option>
                                    <option value="credit" <?php echo ($_GET['metpago'] == 'credit') ? 'selected' : ''; ?>>Cr&eacute;dito</option>
                                    <option value="debit" <?php echo ($_GET['metpago'] == 'debit') ? 'selected' : ''; ?>>D&eacute;bito</option>
                                </select>
                            </div>
                            <div style="display: flex; flex-direction: column;">
                                <label style="margin-bottom: 4px;"><b>DESDE</b></label>
                                <input type="date" value="<?php echo date('Y-m-d', strtotime($_GET['resultsFrom'])) ?>" name="search_desde" id="search_desde">
                            </div>
                            <div style="display: flex; flex-direction: column;">
                                <label style="margin-bottom: 4px;"><b>HASTA</b></label>
                                <input type="date" value="<?php echo date('Y-m-d', strtotime($_GET['resultsTo'])) ?>" name="search_hasta" id="search_hasta">
                            </div>
                            <div style="display: flex; flex-direction: column;">
                                <label style="margin-bottom: 4px;"><b>ID RESERVA</b></label>
                                <input type="text" value="<?php echo $_GET['reservationID']; ?>" placeholder="Nro. Reserva" name="search_reservaid" id="search_reservaid">
                            </div>

                        <?php } else { ?>

                            <div style="display: flex; flex-direction: column;">
                                <label style="margin-bottom: 4px;"><b>TIPO DE PAGO</b></label>
                                <select name="search_metpago" id="search_metpago" class="form-control">
                                    <option value="all" <?php echo ($_GET['metpago'] == 'all' || $_GET['metpago'] == '') ? 'selected' : ''; ?>>Ambos</option>
                                    <option value="credit" <?php echo ($_GET['metpago'] == 'credit') ? 'selected' : ''; ?>>Cr&eacute;dito</option>
                                    <option value="debit" <?php echo ($_GET['metpago'] == 'debit') ? 'selected' : ''; ?>>D&eacute;bito</option>
                                </select>
                            </div>
                            <div style="display: flex; flex-direction: column;">
                                <label style="margin-bottom: 4px;"><b>DESDE</b></label>
                                <input type="date" name="search_desde" id="search_desde">
                            </div>
                            <div style="display: flex; flex-direction: column;">
                                <label style="margin-bottom: 4px;"><b>HASTA</b></label>
                                <input type="date" name="search_hasta" id="search_hasta">
                            </div>
                            <div style="display: flex; flex-direction: column;">
                                <label style="margin-bottom: 4px;"><b>ID RESERVA</b></label>
                                <input type="text" value="<?php echo $_GET['reservationID']; ?>" placeholder="Nro. Reserva" name="search_reservaid" id="search_reservaid">
                            </div>

                        <?php } ?>

                    <?php } else { ?>
                        <div style="display: flex; flex-direction: column;">
                            <label style="margin-bottom: 4px;"><b>TIPO DE PAGO</b></label>
                            <select name="search_metpago" id="search_metpago" class="form-control">
                                <option value="all" selected>Ambos</option>
                                <option value="credit">Cr&eacute;dito</option>
                                <option value="debit">D&eacute;bito</option>
                            </select>
                        </div>
                        <div style="display: flex; flex-direction: column;">
                            <label style="margin-bottom: 4px;"><b>DESDE</b></label>
                            <input type="date" name="search_desde" id="search_desde">
                        </div>
                        <div style="display: flex; flex-direction: column;">
                            <label style="margin-bottom: 4px;"><b>HASTA</b></label>
                            <input type="date" name="search_hasta" id="search_hasta">
                        </div>
                        <div style="display: flex; flex-direction: column;">
                            <label style="margin-bottom: 4px;"><b>ID RESERVA</b></label>
                            <input type="text" placeholder="Nro. Reserva" name="search_reservaid" id="search_reservaid">
                        </div>
                    <?php } ?>


                    <button type="button" class="button-primary" onClick="validateCamps()">Buscar</button>
                    <a class="button-secondary" href="<?php echo $_SERVER['PHP_SELF']; ?>?page=transactions_list">Limpiar</a>
                    <!--</form>-->
                </div>
                <br class="clear">
            </div>
            <?php
            $pagenum = isset($_GET['pagenum']) ? absint($_GET['pagenum']) : 1;
            $limit = 20; // number of rows in page
            $offset = ($pagenum - 1) * $limit;

            // Obtener valores de filtros (GET tiene prioridad sobre POST)
            $datefrom = isset($_GET['resultsFrom']) ? sanitize_text_field($_GET['resultsFrom']) : '';
            $dateto = isset($_GET['resultsTo']) ? sanitize_text_field($_GET['resultsTo']) : '';
            $reservationID = isset($_GET['reservationID']) ? sanitize_text_field($_GET['reservationID']) : '';
            $metpago = isset($_GET['metpago']) ? sanitize_text_field($_GET['metpago']) : 'all';

            // Construir WHERE dinámico
            $where_conditions = array();

            // Filtro por fechas
            if (!empty($datefrom) && !empty($dateto)) {
                $where_conditions[] = $wpdb->prepare(
                    "DATE_FORMAT(transactionDateTime, '%%Y-%%m-%%d') BETWEEN %s AND %s",
                    $datefrom,
                    $dateto
                );
            } elseif (empty($datefrom) && empty($dateto) && empty($reservationID)) {
                // Sin fechas ni reserva: mostrar solo fecha actual
                $where_conditions[] = "DATE_FORMAT(transactionDateTime, '%Y-%m-%d') = CURDATE()";
            }

            // Filtro por reservationID
            if (!empty($reservationID)) {
                $where_conditions[] = $wpdb->prepare("reservationID = %s", $reservationID);
            }

            // Filtro por tipo de pago (solo si no es "all")
            if (!empty($metpago) && $metpago !== 'all') {
                $where_conditions[] = $wpdb->prepare("transactionType = %s", $metpago);
            }

            // Construir la cláusula WHERE
            $where_clause = '';
            if (!empty($where_conditions)) {
                $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
            }

            // Consultas
            $total = $wpdb->get_var("SELECT COUNT(id) FROM $table_name_transactions $where_clause");
            $num_of_pages = ceil($total / $limit);
            $rows = $wpdb->get_results("SELECT id, passportNumber, description, invoiceUrl, completeName, reservationID, transactionDateTime, amount, transactionType FROM $table_name_transactions $where_clause ORDER BY id DESC LIMIT $offset, $limit");

            $page_links = paginate_links(array(
                'base' => add_query_arg('pagenum', '%#%'),
                'format' => '',
                'prev_text' => __('&laquo;', 'text-domain'),
                'next_text' => __('&raquo;', 'text-domain'),
                'total' => $num_of_pages,
                'current' => $pagenum
            ));

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
                <?php if (empty($rows)) { ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 20px; color: #666;">
                            No se encontraron transacciones con los filtros seleccionados.
                        </td>
                    </tr>
                <?php } else { ?>
                    <?php foreach ($rows as $row) { ?>
                        <tr>
                            <td class="manage-column ss-list-width"><?php echo $row->passportNumber; ?></td>
                            <td class="manage-column ss-list-width"><?php echo $row->completeName; ?></td>
                            <td class="manage-column ss-list-width"><?php echo $row->reservationID; ?></td>
                            <td class="manage-column ss-list-width"><?php echo $row->transactionDateTime; ?></td>
                            <td class="manage-column ss-list-width"><?php echo $row->amount; ?></td>
                            <td class="manage-column ss-list-width"><?php echo $row->description; ?></td>
                            <td class="manage-column ss-list-width">
                                <?php if (CheckNumber($row->amount) == 'Negative') { ?>
                                    Monto Negativo
                                <?php } else { ?>
                                    <?php if ($row->invoiceUrl != NULL) { ?>
                                        <button style="color: #22b162;border-color: #22b162;" type="button" onclick="genFacturar(<?php echo $row->id; ?>);" class="button button-secondary" id="<?php echo $row->id; ?>">Descargar</button>
                                    <?php } else { ?>
                                        <div style="display: inline-flex; gap: 4px;">
                                            <button type="button" onclick="editModal(<?php echo $row->id; ?>);" class="button button-secondary">Editar</button>
                                            <button type="button" onclick="openFacturaModal(<?php echo $row->id; ?>, '<?php echo esc_js($row->passportNumber ?? ''); ?>');" class="button button-primary">Facturar</button>
                                            <button type="button" onclick="syncTransaction(<?php echo $row->id; ?>);" class="button" style="background:#f0f0f1;" title="Sincronizar con CloudBeds">&#x21bb;</button>
                                        </div>
                                    <?php } ?>
                                <?php } ?>
                            </td>
                        </tr>
                    <?php } ?>
                <?php } ?>
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
            <div id="facturaModal">
                <div class="modal-content">
                    <h3 class="modal-title">Generar Factura</h3>
                    <div class="modal-body">
                        <p><strong>Transacci&oacute;n ID:</strong> <span id="modal-transaction-id" style="font-weight: bold; color: #0073aa;">-</span></p>
                        <p style="margin: 16px 0 8px; font-weight: 600;">Seleccione el tipo de factura:</p>
                        <div style="display: flex; gap: 12px;">
                            <button type="button" id="btn-fb" class="button">FB (Factura B)</button>
                            <button type="button" id="btn-ft" class="button">FT (Factura T)</button>
                        </div>
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="button button-tertiary" onclick="closeFacturaModal()">Cancelar</button>
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

        function openFacturaModal(id, passportNumber) {
            currentTransactionId = id;
            document.getElementById('modal-transaction-id').textContent = id;

            const modal = document.getElementById('facturaModal');
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';

            // Verificar si tiene documento para Factura T
            const hasDocument = passportNumber && passportNumber.trim() !== '';

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

            // Habilitar/deshabilitar botón FT según documento
            if (hasDocument) {
                newFt.disabled = false;
                newFt.style.opacity = '1';
                newFt.style.cursor = 'pointer';
                newFt.title = '';
                newFt.onclick = () => {
                    closeFacturaModal();
                    genFacturarT(id);
                };
            } else {
                newFt.disabled = true;
                newFt.style.opacity = '0.5';
                newFt.style.cursor = 'not-allowed';
                newFt.title = 'Requiere documento/pasaporte para Factura T';
                newFt.onclick = null;
            }
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

        function syncTransaction(id) {
            if (!confirm('¿Sincronizar datos de esta transacción con CloudBeds?')) {
                return;
            }

            // Mostrar overlay de carga
            jQuery('#overlay').show();

            jQuery.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'foo',
                    sync_transaction: 1,
                    transaction_id: id
                },
                success: function(response) {
                    jQuery('#overlay').hide();
                    if (response.success) {
                        alert('Transacción sincronizada correctamente.');
                        window.location.reload();
                    } else {
                        alert('Error: ' + (response.data?.message || 'Error desconocido'));
                    }
                },
                error: function(xhr, status, error) {
                    jQuery('#overlay').hide();
                    alert('Error de conexión: ' + error);
                }
            });
        }
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
