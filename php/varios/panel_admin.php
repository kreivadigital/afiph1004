<?php

add_action( 'add_meta_boxes', 'button_despacho' );
function button_despacho()
{
    add_meta_box(
        'woocommerce-order-YOUR-UNIQUE-REF',
        __( 'Servicio de Despacho' ),
        'order_meta_box_despacho',
        'shop_order',
        'side',
        'default'
    );
}
function order_meta_box_despacho()
{

  if(isset($_GET['post'])){

    global $wpdb;

    $post_id  = $_GET['post'];
  
    // Get an instance of the WC_Order object (same as before)
    $order = wc_get_order( $post_id );
  
    $order_id  = $order->get_id(); // Get the order ID
    $parent_id = $order->get_parent_id(); // Get the parent order ID (for subscriptions…)
  
    $name_complete = $order->get_billing_first_name().' '.$order->get_billing_last_name();
  //   $billing_last_name = $order->get_billing_last_name();
    $address = $order->get_billing_address_1().' '.$order->get_billing_address_2();
  //   $address2 = $order->get_billing_address_2();
    $city = $order->get_billing_city();
  
    $state = $order->get_billing_state();
    $country = $order->get_billing_country();
    $phone = $order->get_billing_phone();
    $order_status  = $order->get_status(); // Get the order status (see the conditional method has_status() below)
  
    //consulta para traer el id de despacho mas reciente e imprimir
    $sql = "SELECT COUNT(*) FROM wp_nave_cargo_despachos WHERE order_id LIKE '$order_id%' ";
    $countby = $wpdb->get_var($sql);
  
    if($countby > 0){
      switch($countby){
        case "1":
          $button = '<strong>Generar Nuevo Despacho</strong><br><button class="button" type="button" onClick="generate_despacho(\''.$order_id.'\',\''.$name_complete.'\',\''.$address.'\',\''.$city.'\')">Generar</button><br><br>';
          $button .= '<strong>Imprimir Etiqueta</strong><br><button class="button" type="button" onClick="generate_etiqueta(\''.$order_id.'\',\''.$name_complete.'\',\''.$address.'\',\''.$city.'\',\''.$phone.'\')">Imprimir</button>';
          break;
        default;
          $count_id_last = intval($countby) - 1;
          $order_id_last = $order_id.'-'.$count_id_last;
          $button = '<strong>Generar Nuevo Despacho</strong><br><button class="button" type="button" onClick="generate_despacho(\''.$order_id.'\',\''.$name_complete.'\',\''.$address.'\',\''.$city.'\')">Generar</button><br><br>';
          $button .= '<strong>Imprimir Etiqueta</strong><br><button class="button" type="button" onClick="generate_etiqueta(\''.$order_id_last.'\',\''.$name_complete.'\',\''.$address.'\',\''.$city.'\',\''.$phone.'\')">Imprimir</button>';        
      }
    }else{
      $button = '<strong>Generar Despacho</strong><br><button class="button" type="button" onClick="generate_despacho(\''.$order_id.'\',\''.$name_complete.'\',\''.$address.'\',\''.$city.'\')">Generar</button>';
    }
  
    switch($order_status){
      case "processing":
          echo $button;
        break;
      case "listo-despacho":
          echo $button;
        break;
      case "despachado":
          echo $button;
        break;
      default;
    }
  
    echo '
      <div id="overlay" style="display:none;">
        <div class="spinner"></div>
        <br/>
        <img src="https://i.gifer.com/origin/b4/b4d657e7ef262b88eb5f7ac021edda87.gif" width="80" />
      </div>
    ';

  }

}

// Add a custom metabox only for shop_order post type (order edit pages)
add_action( 'add_meta_boxes', 'add_meta_boxesws' );
function add_meta_boxesws()
{
    add_meta_box( 'custom_order_meta_box', __( 'My Title' ),
        'custom_metabox_content', 'shop_order', 'normal', 'default');
}

function custom_metabox_content(){
    $post_id = isset($_GET['post']) ? $_GET['post'] : false;
    if(! $post_id ) return; // Exit

    $value="abc";
    ?>
        <p><a href="?post=<?php echo $post_id; ?>&action=edit&abc=<?php echo $value; ?>" class="button"><?php _e('Return Shipment'); ?></a></p>
    <?php
    // The displayed value using GET method
    if ( isset( $_GET['abc'] ) && ! empty( $_GET['abc'] ) ) {
        echo '<p>Value: '.$_GET['abc'].'</p>';
    }
}

?>