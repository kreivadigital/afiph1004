<?php

function config_update() {
    global $wpdb;
    $message = "";
    $table_name = $wpdb->prefix . "hotels_config";
    $id = $_GET["id"];
//update
    if (isset($_POST['update'])) {

        $api_endpoint = $_POST["api_endpoint"];
        $version = $_POST["version"];
        $client_id = $_POST["client_id"];
        $client_secret = $_POST["client_secret"];
        $redirect_url = $_POST["redirect_url"];

        $wpdb->update(
                $table_name, //table
                array('api_endpoint' => $api_endpoint, 'version' => $version, 'client_id' => $client_id, 'client_secret' => $client_secret, 'redirect_url' => $redirect_url), //data
                array('id' => $id), //where
                array('%s'), //data format
                array('%s') //where format
        );

        $message.="Configuración actualizada con éxito.";
    }
//delete
    else if (isset($_POST['delete'])) {

        $wpdb->delete(
                $table_name, //table
                array('id' => $id), //data
                array('%s') //where format
        );

        $message.="Configuración eliminada con éxito.";

    } else {//selecting value to update	
        $dataconfig = $wpdb->get_results($wpdb->prepare("SELECT id,api_endpoint,version,client_id,client_secret,redirect_url from $table_name where id=%s", $id));
        foreach ($dataconfig as $s) {
            $api_endpoint = $s->api_endpoint;
            $version = $s->version;
            $client_id = $s->client_id;
            $client_secret = $s->client_secret;
            $redirect_url = $s->redirect_url;
        }
        
        $message.="Configuración actualizada con éxito.";
    }
    ?>
    <link type="text/css" href="<?php echo WP_PLUGIN_URL; ?>/cita/css/style-admin.css" rel="stylesheet" />
    <div class="wrap">
        <h2>Edición de Datos</h2>

        <?php if (isset($_POST['delete'])) { ?>

            <?php if (isset($message)): ?><div class="updated"><p><?php echo $message; ?></p></div><?php endif; ?>
            <?php 
                if (isset($message)){

                    echo "
                        <script type='text/javascript'>
                            window.setTimeout(function() {
                                window.location.href='".admin_url('admin.php?page=config_list')."';
                            }, 500);
                        </script>
                    ";

                }
            ?>

        <?php } else if (isset($_POST['update'])) { ?>

            <?php if (isset($message)): ?><div class="updated"><p><?php echo $message; ?></p></div><?php endif; ?>
            <?php 
                if (isset($message)){

                    echo "
                        <script type='text/javascript'>
                            window.setTimeout(function() {
                                window.location.href='".admin_url('admin.php?page=config_list')."';
                            }, 500);
                        </script>
                    ";

                }
            ?>

        <?php } else { ?>
            <form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
                <table class='wp-list-table widefat fixed'>
                    <tr><th>API Endpoint</th><td><input type="text" name="api_endpoint" value="<?php echo $api_endpoint; ?>"/></td></tr>
                    <tr><th>Version</th><td><input type="text" name="version" value="<?php echo $version; ?>"/></td></tr>
                    <tr><th>ClientID</th><td><input type="text" name="client_id" value="<?php echo $client_id; ?>"/></td></tr>
                    <tr><th>ClientSecret</th><td><input type="text" name="client_secret" value="<?php echo $client_secret; ?>"/></td></tr>
                    <tr><th>RedirectURL</th><td><input type="text" name="redirect_url" value="<?php echo $redirect_url; ?>"/></td></tr>
                </table>
                <input type='submit' name="update" value='Actualizar' class='button'> &nbsp;&nbsp;
                <input type='submit' name="delete" value='Eliminar' class='button' onclick="return confirm('&iquest;Est&aacute;s seguro de borrar este elemento?')">
            </form>
        <?php } ?>

    </div>
    <?php
}