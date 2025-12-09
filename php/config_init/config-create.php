<?php

function config_create() {
    //insert
    if (isset($_POST['insert'])) {
        $message = "";
        $api_endpoint = $_POST["api_endpoint"];
        $version = $_POST["version"];
        $client_id = $_POST["client_id"];
        $client_secret = $_POST["client_secret"];
        $redirect_url = $_POST["redirect_url"];

        global $wpdb;
        $table_name = $wpdb->prefix . "hotels_config";

        $varn = '1';
        $stat = '0';
        $dataquery= $wpdb->get_results("SELECT COUNT(id) as config_id FROM $table_name WHERE status = '$varn' ");
        foreach ( $dataquery as $row ) {
            $datares = $row->config_id;
        }

        if($datares > 0){

            $wpdb->insert(
                    $table_name, //table
                    array('api_endpoint' => $api_endpoint, 'version' => $version, 'client_id' => $client_id, 'client_secret' => $client_secret, 'redirect_url' => $redirect_url, 'status' => $stat), //data
                    array('%s', '%s', '%s', '%s', '%s', '%s') //data format			
            );

        }else{

            $wpdb->insert(
                    $table_name, //table
                    array('api_endpoint' => $api_endpoint, 'version' => $version, 'client_id' => $client_id, 'client_secret' => $client_secret, 'redirect_url' => $redirect_url), //data
                    array('%s', '%s', '%s', '%s', '%s') //data format			
            );

        }

        $message.="Configuración guardada correctamente.";
    }else{
        $api_endpoint ="";
        $version ="";
        $client_id = "";
        $client_secret = "";
        $redirect_url = "";
    }
    ?>
    <link type="text/css" href="<?php echo WP_PLUGIN_URL; ?>/cita/css/style-admin.css" rel="stylesheet" />
    <div class="wrap">
        <h2>Añade información de la api</h2>
        <?php if (isset($message)): ?><div class="updated"><p><?php echo $message; ?></p></div><?php endif; ?>
        <?php 
            if (isset($message)){

                echo "
                    <script type='text/javascript'>
                        window.setTimeout(function() {
                            window.location.href='".admin_url('admin.php?page=config_list')."';
                        }, 200);
                    </script>
                ";

            }
        ?>        
        <form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
            <p>Registro</p>
            <table class='wp-list-table widefat fixed'>
                <tr>
                    <th class="ss-th-width">API Endpoint</th>
                    <td><input type="text" name="api_endpoint" value="<?php echo $api_endpoint; ?>" class="ss-field-width" /></td>
                </tr>
                <tr>
                    <th class="ss-th-width">Version</th>
                    <td><input type="text" name="version" value="<?php echo $version; ?>" class="ss-field-width" /></td>
                </tr>
                <tr>
                    <th class="ss-th-width">ClientID</th>
                    <td><input type="text" name="client_id" value="<?php echo $client_id; ?>" class="ss-field-width" /></td>
                </tr>
                <tr>
                    <th class="ss-th-width">ClientSecret</th>
                    <td><input type="text" name="client_secret" value="<?php echo $client_secret; ?>" class="ss-field-width" /></td>
                </tr>
                <tr>
                    <th class="ss-th-width">RedirectURL</th>
                    <td><input type="text" name="redirect_url" value="<?php echo $redirect_url; ?>" class="ss-field-width" /></td>
                </tr>
            </table>
            <input type='submit' name="insert" value='Guardar' class='button'>
        </form>
    </div>
    <?php
}