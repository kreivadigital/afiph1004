<?php

//insertar js admin logged in
add_action( 'admin_enqueue_scripts', 'insertar_js' );  

function insertar_js() {  

  wp_enqueue_script( 'main_js', WP_PLUGIN_URL . '/hotels/js/main.js', array ( 'jquery' ), 1.1, true); 
  wp_register_script('main_js', WP_PLUGIN_URL . '/hotels/js/main.js');

  wp_localize_script('main_js', 'ajax_var', array(
    'admin_url' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('nonce')
  ));

  // Toast Message
  wp_register_script('toastmessage_js', WP_PLUGIN_URL . '/hotels/css/Toast-Message/toastr.min.js', array('jquery'), '1', false ); 
  wp_enqueue_script('toastmessage_js'); 
  
  wp_register_style( 'toastmessage_css',  WP_PLUGIN_URL . '/hotels/css/Toast-Message/toastr.min.css' );
  wp_enqueue_style( 'toastmessage_css' ); 

  wp_register_style( 'style_css',  WP_PLUGIN_URL . '/hotels/css/style.css' );
  wp_enqueue_style( 'style_css' );

  // SweetAlert
  wp_register_script('sweetalert_js', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', array('jquery'), '1', false ); 
  wp_enqueue_script('sweetalert_js'); 

}

add_thickbox();

function foo_render_action_page() {
  define( 'IFRAME_REQUEST', true );
  iframe_header();

  echo '
    <table style="padding: 0px 0px 0px 40px;">
      <tr>
        <th>Desde</th>
        <th>Hasta</th>
        <th>ID reserva</th>
      </tr>
      <tr>
        <td><input type="date" id="datefrom" /></td>
        <td><input type="date" id="dateto" /></td>
        <td><input type="text" id="reservation_id" placeholder="ID reserva" /></td>
      </tr>
    </table><br>

    <center><button type="button" class="button button-success" id="importFunct">Importar</button></center>

    <script>
      jQuery("#importFunct").click(function() { 

        var datefrom = document.getElementById("datefrom");
        var dateto = document.getElementById("dateto");
        var reservation_id = document.getElementById("reservation_id");
    
        if (!datefrom.value && !dateto.value && !reservation_id.value) {
            parent.closeThickboxTwo(); 
        }else{
            if (!datefrom.value) {
                toastr.error("El campo de fecha DESDE no puede estar vacío");
            }else if(!dateto.value){
                toastr.error("El campo de fecha HASTA no puede estar vacío");
            }else if(!reservation_id.value){
                toastr.error("El campo ID RESERVA no puede estar vacío");
            }else{
              sessionStorage.setItem("resultsFrom", datefrom.value);
              sessionStorage.setItem("resultsTo", dateto.value);
              sessionStorage.setItem("reservationID", reservation_id.value);
      
              parent.closeThickbox(); 
            }
        }

      });
    </script>
  ';

  // ... your content here ...
  iframe_footer();
  exit;
}
add_action( 'admin_action_foo_modal_box', 'foo_render_action_page' );
?>