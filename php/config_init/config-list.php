<?php
/**
 * Pagina de configuracion del plugin
 *
 * @package ServicesAPIHotel
 * @since 1.0.0
 */

function config_list() {
    global $wpdb;
    $table_name = $wpdb->prefix . "hotels_config";
    $message = "";
    $message_type = "updated";

    // Procesar cambio de ambiente AFIP
    if (isset($_POST['save_afip_environment']) && wp_verify_nonce($_POST['afip_nonce'], 'save_afip_environment')) {
        $new_environment = sanitize_text_field($_POST['afip_environment']);

        if (in_array($new_environment, array('prod', 'test'))) {
            $result = $wpdb->update(
                $table_name,
                array('afip_environment' => $new_environment),
                array('status' => '1'),
                array('%s'),
                array('%s')
            );

            if ($result !== false) {
                $message = "Ambiente AFIP actualizado a: " . ($new_environment === 'test' ? 'HOMOLOGACION (Testing)' : 'PRODUCCION');
                $message_type = "updated";
            } else {
                $message = "Error al actualizar el ambiente AFIP.";
                $message_type = "error";
            }
        }
    }

    // Procesar guardado de credenciales AFIP
    if (isset($_POST['save_afip_credentials']) && wp_verify_nonce($_POST['afip_creds_nonce'], 'save_afip_credentials')) {
        $update_data = array(
            'cert_prod' => sanitize_text_field($_POST['cert_prod']),
            'key_prod' => sanitize_text_field($_POST['key_prod']),
            'cuit_prod' => sanitize_text_field($_POST['cuit_prod']),
            'cert_test' => sanitize_text_field($_POST['cert_test']),
            'key_test' => sanitize_text_field($_POST['key_test']),
            'cuit_test' => sanitize_text_field($_POST['cuit_test']),
        );

        $result = $wpdb->update(
            $table_name,
            $update_data,
            array('status' => '1'),
            array('%s', '%s', '%s', '%s', '%s', '%s'),
            array('%s')
        );

        if ($result !== false) {
            $message = "Credenciales AFIP actualizadas correctamente.";
            $message_type = "updated";
        } else {
            $message = "Error al actualizar las credenciales AFIP.";
            $message_type = "error";
        }
    }

    // Procesar activacion de configuracion
    if (isset($_POST['valueactive'])) {
        $stat1 = "0";
        $stat2 = "1";
        $idcode = intval($_POST["valueactive"]);

        $cns1 = $wpdb->get_results($wpdb->prepare("SELECT id from $table_name where status=%s", $stat2));
        foreach ($cns1 as $tc) {
            $id_last = $tc->id;
        }

        $wpdb->update(
                $table_name,
                array('status' => $stat1),
                array('id' => $id_last),
                array('%s'),
                array('%s')
        );

        $wpdb->update(
                $table_name,
                array('status' => $stat2),
                array('id' => $idcode),
                array('%s'),
                array('%s')
        );

        $message = "Activacion realizada con exito.";
    }

    // Obtener configuracion actual
    $current_config = $wpdb->get_row("SELECT * FROM $table_name WHERE status = '1' LIMIT 1");
    $afip_environment = isset($current_config->afip_environment) ? $current_config->afip_environment : 'prod';
?>
    <style>
        .afip-environment-box {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-left: 4px solid #0073aa;
            padding: 20px;
            margin: 20px 0;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        .afip-environment-box.test-mode {
            border-left-color: #00a0d2;
            background: #f0f8ff;
        }
        .afip-environment-box.prod-mode {
            border-left-color: #dc3232;
            background: #fff8f8;
        }
        .afip-toggle-container {
            display: flex;
            gap: 20px;
            margin: 15px 0;
        }
        .afip-toggle-option {
            flex: 1;
            padding: 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .afip-toggle-option:hover {
            border-color: #0073aa;
        }
        .afip-toggle-option.selected {
            border-color: #0073aa;
            background: #f0f8ff;
        }
        .afip-toggle-option.selected.prod {
            border-color: #dc3232;
            background: #fff8f8;
        }
        .afip-toggle-option input[type="radio"] {
            margin-right: 10px;
        }
        .afip-toggle-option h4 {
            margin: 0 0 5px 0;
            display: inline;
        }
        .afip-toggle-option p {
            margin: 5px 0 0 24px;
            color: #666;
            font-size: 12px;
        }
        .credentials-section {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        .credentials-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .credential-box {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 5px;
        }
        .credential-box h4 {
            margin-top: 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #ddd;
        }
        .credential-box.prod h4 { color: #dc3232; }
        .credential-box.test h4 { color: #00a0d2; }
        .credential-box label {
            display: block;
            margin: 10px 0 5px;
            font-weight: 600;
        }
        .credential-box input[type="text"] {
            width: 100%;
        }
    </style>

    <div class="wrap">
        <h2>Panel de Configuracion API</h2>

        <?php if (!empty($message)): ?>
            <div class="<?php echo esc_attr($message_type); ?>"><p><?php echo esc_html($message); ?></p></div>
        <?php endif; ?>

        <!-- Seccion Ambiente AFIP -->
        <div class="afip-environment-box <?php echo $afip_environment === 'test' ? 'test-mode' : 'prod-mode'; ?>">
            <h3>Configuracion Ambiente AFIP</h3>
            <p>Selecciona el ambiente de AFIP para la facturacion electronica:</p>

            <form method="post" action="">
                <?php wp_nonce_field('save_afip_environment', 'afip_nonce'); ?>

                <div class="afip-toggle-container">
                    <label class="afip-toggle-option <?php echo $afip_environment === 'prod' ? 'selected prod' : ''; ?>">
                        <input type="radio" name="afip_environment" value="prod"
                            <?php checked($afip_environment, 'prod'); ?>
                            onchange="this.form.submit();">
                        <h4>PRODUCCION</h4>
                        <p>Genera facturas reales con CAE valido. Usar solo para facturacion oficial.</p>
                    </label>

                    <label class="afip-toggle-option <?php echo $afip_environment === 'test' ? 'selected' : ''; ?>">
                        <input type="radio" name="afip_environment" value="test"
                            <?php checked($afip_environment, 'test'); ?>
                            onchange="this.form.submit();">
                        <h4>HOMOLOGACION (Testing)</h4>
                        <p>Ambiente de pruebas de AFIP. Las facturas generadas NO son validas fiscalmente.</p>
                    </label>
                </div>

                <input type="hidden" name="save_afip_environment" value="1">
            </form>

            <!-- Credenciales AFIP -->
            <div class="credentials-section">
                <h4>Credenciales por Ambiente</h4>
                <form method="post" action="">
                    <?php wp_nonce_field('save_afip_credentials', 'afip_creds_nonce'); ?>

                    <div class="credentials-grid">
                        <div class="credential-box prod">
                            <h4>Produccion</h4>
                            <label>Certificado (.pem)</label>
                            <input type="text" name="cert_prod"
                                value="<?php echo esc_attr($current_config->cert_prod ?? 'facturacion2025.pem'); ?>">

                            <label>Clave Privada (.key)</label>
                            <input type="text" name="key_prod"
                                value="<?php echo esc_attr($current_config->key_prod ?? 'MiClavePrivada.key'); ?>">

                            <label>CUIT</label>
                            <input type="text" name="cuit_prod"
                                value="<?php echo esc_attr($current_config->cuit_prod ?? '30718446976'); ?>">
                        </div>

                        <div class="credential-box test">
                            <h4>Homologacion (Testing)</h4>
                            <label>Certificado (.pem)</label>
                            <input type="text" name="cert_test"
                                value="<?php echo esc_attr($current_config->cert_test ?? 'certificado.pem'); ?>">

                            <label>Clave Privada (.key)</label>
                            <input type="text" name="key_test"
                                value="<?php echo esc_attr($current_config->key_test ?? 'MiClavePrivada.key'); ?>">

                            <label>CUIT</label>
                            <input type="text" name="cuit_test"
                                value="<?php echo esc_attr($current_config->cuit_test ?? '20000000001'); ?>">
                        </div>
                    </div>

                    <p style="margin-top: 15px;">
                        <em>Ubicacion de certificados:<br>
                        - Produccion: <code>/php/afip/cert/</code><br>
                        - Homologacion: <code>/php/afip/cert_preprod/</code></em>
                    </p>

                    <p>
                        <input type="hidden" name="save_afip_credentials" value="1">
                        <button type="submit" class="button button-primary">Guardar Credenciales</button>
                    </p>
                </form>
            </div>
        </div>

        <!-- Seccion Configuracion CloudBeds -->
        <h3 style="margin-top: 30px;">Configuracion CloudBeds API</h3>

        <?php
        $rows = $wpdb->get_results("SELECT id,api_endpoint,version,client_id,client_secret,redirect_url,access_token,refresh_token,status from $table_name");
        ?>

        <?php if(count($rows) == 0){ ?>
            <div class="tablenav top">
                <div class="alignleft actions">
                    <a class="button button-primary" href="<?php echo admin_url('admin.php?page=config_create'); ?>">Agregar configuracion de API</a>
                </div>
                <br class="clear">
            </div>
        <?php } ?>

        <style>
            .sah-table {
                width: 100%;
                border-collapse: collapse;
                table-layout: fixed;
            }
            .sah-table th,
            .sah-table td {
                padding: 10px 8px;
                text-align: left;
                vertical-align: top;
                border-bottom: 1px solid #e1e1e1;
                word-wrap: break-word;
                overflow-wrap: break-word;
            }
            .sah-table th {
                background: #f9f9f9;
                font-weight: 600;
                font-size: 13px;
            }
            .sah-table .col-id { width: 40px; }
            .sah-table .col-endpoint { width: 20%; }
            .sah-table .col-version { width: 60px; }
            .sah-table .col-credentials { width: 25%; }
            .sah-table .col-tokens { width: 25%; }
            .sah-table .col-status { width: 70px; text-align: center; }
            .sah-table .col-actions { width: 120px; text-align: center; }

            .sah-truncate {
                display: block;
                max-width: 100%;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
                font-family: monospace;
                font-size: 11px;
                background: #f5f5f5;
                padding: 4px 6px;
                border-radius: 3px;
                margin: 2px 0;
            }
            .sah-truncate:hover {
                white-space: normal;
                word-break: break-all;
                background: #fff3cd;
            }
            .sah-label {
                font-size: 11px;
                color: #666;
                display: block;
                margin-bottom: 2px;
            }
            .sah-status {
                display: inline-block;
                padding: 3px 10px;
                border-radius: 12px;
                font-size: 11px;
                font-weight: 600;
            }
            .sah-status.active {
                background: #d4edda;
                color: #155724;
            }
            .sah-status.inactive {
                background: #f8d7da;
                color: #721c24;
            }
            .sah-token-status {
                font-size: 11px;
                padding: 2px 6px;
                border-radius: 3px;
                display: inline-block;
            }
            .sah-token-status.valid {
                background: #d4edda;
                color: #155724;
            }
            .sah-token-status.empty {
                background: #f8d7da;
                color: #721c24;
            }
        </style>

        <table class="sah-table wp-list-table widefat striped">
            <thead>
                <tr>
                    <th class="col-id">ID</th>
                    <th class="col-endpoint">API Endpoint</th>
                    <th class="col-version">Ver.</th>
                    <th class="col-credentials">Credenciales</th>
                    <th class="col-tokens">Tokens</th>
                    <th class="col-status">Estado</th>
                    <th class="col-actions">Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row) {
                $is_active = $row->status > 0;
                $has_access_token = !empty($row->access_token);
                $has_refresh_token = !empty($row->refresh_token);
            ?>
                <tr>
                    <td class="col-id"><?php echo esc_html($row->id); ?></td>
                    <td class="col-endpoint">
                        <span class="sah-truncate" title="<?php echo esc_attr($row->api_endpoint); ?>">
                            <?php echo esc_html($row->api_endpoint); ?>
                        </span>
                        <span class="sah-label">Redirect:</span>
                        <span class="sah-truncate" title="<?php echo esc_attr($row->redirect_url); ?>">
                            <?php echo esc_html($row->redirect_url); ?>
                        </span>
                    </td>
                    <td class="col-version"><?php echo esc_html($row->version); ?></td>
                    <td class="col-credentials">
                        <span class="sah-label">Client ID:</span>
                        <span class="sah-truncate" title="<?php echo esc_attr($row->client_id); ?>">
                            <?php echo esc_html($row->client_id); ?>
                        </span>
                        <span class="sah-label">Client Secret:</span>
                        <span class="sah-truncate" title="<?php echo esc_attr($row->client_secret); ?>">
                            <?php echo esc_html(substr($row->client_secret, 0, 20)); ?>...
                        </span>
                    </td>
                    <td class="col-tokens">
                        <span class="sah-label">Access Token:</span>
                        <span class="sah-token-status <?php echo $has_access_token ? 'valid' : 'empty'; ?>">
                            <?php echo $has_access_token ? 'Configurado' : 'Sin configurar'; ?>
                        </span>
                        <br><br>
                        <span class="sah-label">Refresh Token:</span>
                        <span class="sah-token-status <?php echo $has_refresh_token ? 'valid' : 'empty'; ?>">
                            <?php echo $has_refresh_token ? 'Configurado' : 'Sin configurar'; ?>
                        </span>
                    </td>
                    <td class="col-status">
                        <span class="sah-status <?php echo $is_active ? 'active' : 'inactive'; ?>">
                            <?php echo $is_active ? 'Activo' : 'Inactivo'; ?>
                        </span>
                    </td>
                    <td class="col-actions">
                        <?php if(!$is_active){ ?>
                            <form method="post" action="<?php echo esc_url($_SERVER['REQUEST_URI']); ?>" style="display:inline;">
                                <input type="hidden" name="valueactive" value="<?php echo esc_attr($row->id); ?>" />
                                <button type="submit" class="button button-small">Activar</button>
                            </form>
                        <?php } ?>
                        <a class="button button-small button-primary" href="<?php echo admin_url('admin.php?page=config_update&id=' . $row->id); ?>">Editar</a>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>

        <?php if(count($rows) > 0){ ?>
            <p style="margin-top: 10px;">
                <a class="button" href="<?php echo admin_url('admin.php?page=config_create'); ?>">Agregar nueva configuracion</a>
            </p>
        <?php } ?>
    </div>
<?php
}