<?php
function config_list() {
    global $wpdb;
    $table_name = $wpdb->prefix . "hotels_config";

    if (isset($_POST['valueactive'])) {

        $message = "";
        $stat1 = "0";
        $stat2 = "1";
        $idcode = $_POST["valueactive"];

        $cns1 = $wpdb->get_results($wpdb->prepare("SELECT id from $table_name where status=%s", $stat2));
        foreach ($cns1 as $tc) {
            $id_last = $tc->id;
        }

        $wpdb->update(
                $table_name, //table
                array('status' => $stat1), //data
                array('id' => $id_last), //where
                array('%s'), //data format
                array('%s') //where format
        );

        $wpdb->update(
                $table_name, //table
                array('status' => $stat2), //data
                array('id' => $idcode), //where
                array('%s'), //data format
                array('%s') //where format
        );

        $message.="Activación realizada con éxito.";
    }
?>
    <link type="text/css" href="<?php echo WP_PLUGIN_URL; ?>/cita/css/style-admin.css" rel="stylesheet" />
    <div class="wrap">
        <h2>Panel de Configuración Api</h2>
        <!-- <div class="notice notice-success is-dismissible">
            <p>Para mostrar el buscador web a los clientes, cree una página nueva y pegue el siguiente shortcode: <code><b>[show_search]</b></code></p>
        </div> -->
            <?php if (isset($message)): ?><div class="updated"><p><?php echo $message; ?></p></div><?php endif; ?>

        <?php
        $rows = $wpdb->get_results("SELECT id,api_endpoint,version,client_id,client_secret,redirect_url,access_token,refresh_token,status from $table_name");
        ?>

        <?php if(count($rows) > 0){ ?>

        <?php }else{ ?>
            <div class="tablenav top">
                <div class="alignleft actions">
                    <a class="button button-primary" href="<?php echo admin_url('admin.php?page=config_create'); ?>">Añadir información de la api</a>
                </div>
                <br class="clear">
            </div>
        <?php } ?>

        <table class='wp-list-table widefat fixed striped posts'>
            <tr>
                <th class="manage-column ss-list-width">ID</th>
                <th class="manage-column ss-list-width">API Endpoint</th>
                <th class="manage-column ss-list-width">Version</th>
                <th class="manage-column ss-list-width">ClientID</th>
                <th class="manage-column ss-list-width">ClientSecret</th>
                <th class="manage-column ss-list-width">RedirectURL</th>
                <th class="manage-column ss-list-width">Access Token</th>
                <th class="manage-column ss-list-width">Refresh Token</th>
                <th class="manage-column ss-list-width">Estado</th>
                <th>Acciones</th>
            </tr>
            <?php foreach ($rows as $row) { ?>
                <?php 
                if($row->status > 0){
                    $stat = "Activo";
                }else{
                    $stat = "Inactivo";
                } 
                ?>
                <tr>
                    <td class="manage-column ss-list-width"><?php echo $row->id; ?></td>
                    <td class="manage-column ss-list-width"><?php echo $row->api_endpoint; ?></td>
                    <td class="manage-column ss-list-width"><?php echo $row->version; ?></td>
                    <td class="manage-column ss-list-width"><?php echo $row->client_id; ?></td>
                    <td class="manage-column ss-list-width"><?php echo $row->client_secret; ?></td>
                    <td class="manage-column ss-list-width"><?php echo $row->redirect_url; ?></td>
                    <td class="manage-column ss-list-width"><?php echo $row->access_token; ?></td>
                    <td class="manage-column ss-list-width"><?php echo $row->refresh_token; ?></td>
                    <td class="manage-column ss-list-width"><?php echo $stat; ?></td>
                    <form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
                        <td>
                            <?php if($row->status > 0){ ?>
                                <!-- <a class="button" href="<?php echo admin_url('admin.php?page=config_update&id=' . $row->id); ?>">Actualizar</a> -->
                            <?php }else{ ?>
                                <input type="hidden" name="valueactive" value="<?php echo $row->id; ?>" />
                                <button type="submit" class="button">Activar</button>
                            <?php } ?>
                            <a class="button button-primary" href="<?php echo admin_url('admin.php?page=config_update&id=' . $row->id); ?>">Actualizar</a>
                        </td>
                    </form>
                </tr>
            <?php } ?>
        </table>
    </div>
    <?php
}