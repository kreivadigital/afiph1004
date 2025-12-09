<?php

// error_reporting(true);
// error_reporting(E_ALL);
// ini_set('display_errors', '1');

include plugin_dir_path( __DIR__ ). '../vendor/autoload.php';

// reference the Dompdf namespace
use Dompdf\Dompdf;

//define variables
define ("WSDL", "../afip/wsfe.wsdl");     # The WSDL corresponding to WSAA
define ("CERT", "../afip/cert/certificado.pem");       # The X.509 certificate in PEM format
define ("PRIVATEKEY", "../afip/cert/MiClavePrivada.key"); # The private key correspoding to CERT (PEM)
define ("PASSPHRASE", ""); # The passphrase (if any) to sign
define ("PROXY_HOST", "10.20.152.112"); # Proxy IP, to reach the Internet
define ("PROXY_PORT", "80");            # Proxy TCP port
define ("URL", "https://wsaahomo.afip.gov.ar/ws/services/LoginCms");
#define ("URL", "https://wsaa.afip.gov.ar/ws/services/LoginCms");
define ("CUIT", "20085031202");            # Proxy TCP port
define ("URL_WEB_FILES", 'https://contable.penthouse1004.com/wp-content/plugins/hotels/php/invoicesxml/');
define ("URL_WEB_FILES_PDF", 'https://contable.penthouse1004.com/wp-content/plugins/hotels/php/invoicespdf/');

function ajax_foo_handler() {
              
    global $wpdb, $table_name, $table_name_transactions, $access_token_global;

    if ( isset($_POST['init']) ) {//generar despacho

        $sql = "SELECT api_endpoint,version,client_id,client_secret,redirect_url FROM $table_name ";
        $result = $wpdb->get_results($sql) or die(mysql_error());
    
        foreach($result  as $key => $row) {
            // each column in your row will be accessible like this
            $api_endpoint =  $row->api_endpoint;
            $version =  $row->version;
            $client_id =  $row->client_id;
            $client_secret =  $row->client_secret;
            $redirect_url =  $row->redirect_url;
        }

        $url_access = AUTH_URL."?"."&response_type=code"."&client_id=".$client_id."&redirect_uri=".$redirect_url;

        wp_send_json_success( $url_access );
    
    }

    if ( isset($_POST['generate_token']) ) {//generar despacho

        $code_auth = $_POST['code_auth'];

        wp_send_json_success( $code_auth );
    
    }

    if ( isset($_POST['imp_transac']) ) {//generar despacho

        $data_fetch_import = fetch_import($_POST['resultsFrom'],$_POST['resultsTo'],$_POST['reservationID']);
        wp_send_json_success( $data_fetch_import );
    
    }
    
    if ( isset($_POST['imp_transac_date_today']) ) {//generar despacho

        $datenow = date('Y-m-d');

        $data_fetch_import = getTransactionsByDate($access_token_global,$datenow);
        $data_fetch_guest = getDataByGuest();
        
        wp_send_json_success(['data' => $data_fetch_import, 'url_search' => CALLBACK_URL]);
        
    }

    if ( isset($_POST['act_values_transactions']) ) {//generar despacho

        $datenow = date('Y-m-d');

        $data_fetch_import = getTransactionsByDate($access_token_global,$datenow);
        $data_fetch_guest = getDataByGuest();
        
        wp_send_json_success(['data' => GUEST_URL, 'url_search' => CALLBACK_URL]);
    
    }

    if ( isset($_POST['init_load_records']) ) {//generar despacho

        $data_fetch_import = getTransactions($access_token_global);
        // $data_fetch_guest = getDataByGuest();
        wp_send_json_success(['data' => GUEST_URL, 'url_search' => CALLBACK_URL]);
    
    }

    if ( isset($_POST['gen_facturar']) ) {//generar despacho

        $code_transaction = $_POST['code_transaction'];

        ini_set("soap.wsdl_cache_enabled", "0");
        // if (!file_exists(CERT)) {exit("Failed to open ".CERT."\n");}
        // if (!file_exists(PRIVATEKEY)) {exit("Failed to open ".PRIVATEKEY."\n");}
        // if (!file_exists(WSDL)) {exit("Failed to open ".WSDL."\n");}
        // //if ( $argc < 2 ) {ShowUsage($argv[0]); exit();}
        date_default_timezone_set('America/Argentina/Buenos_Aires');
        $hoy = date('Y-m-d H:i:s'); 
        $SERVICE="wsfe";

        $xml_token = '';
        $xml_sign = '';
        
        if (file_exists(plugin_dir_path( __FILE__ )."../afip/TRA_WSFE.xml")){
           $TA=simplexml_load_file(plugin_dir_path( __FILE__ )."../afip/TRA_WSFE.xml");
           $inicio=substr($TA->header->generationTime,0,-10);
           $ini=str_replace('T',' ',$inicio);
           $expira=substr($TA->header->expirationTime,0,-10);
           $ec=str_replace('T',' ',$expira);
        
            if ($hoy>=$ec){//solicitamos nuevo token y sign
        
              CreateTRA($SERVICE);
              $CMS=SignTRA();
              $TARES=CallWSAA(base64_decode($CMS));
              if (!file_put_contents(plugin_dir_path( __FILE__ )."../afip/TRA_WSFE.xml", $TARES)) {exit();}
              $xml=simplexml_load_string($TARES) or die("Error: Cannot create object");
              $xml_token = $xml->credentials->token;
              $xml_sign = $xml->credentials->sign;

            }else{//usamos sign y token generados anteriormente
        
              $xml_token = $TA->credentials->token;
              $xml_sign = $TA->credentials->sign;

            }
        
        }else{//solicitud primera vez
        
          CreateTRA($SERVICE);
          $CMS=SignTRA();
          $TARES=CallWSAA(base64_decode($CMS));
          if (!file_put_contents(plugin_dir_path( __FILE__ )."../afip/TRA_WSFE.xml", $TARES)) {exit();}
          $xml=simplexml_load_string($TARES) or die("Error: Cannot create object");
          $xml_token = $xml->credentials->token;
          $xml_sign = $xml->credentials->sign;

        }

        $sql_res = "SELECT id,propertyID,transactionID,guestID,amount,invoiceUrl FROM $table_name_transactions WHERE id=".$code_transaction;
        $res_t = $wpdb->get_results($sql_res) or die(mysql_error());
    
        foreach($res_t  as $key => $row) {
          
            // each column in your row will be accessible like this
            $code = $row->id;
            $propertyID =  $row->propertyID;
            $transactionID =  $row->transactionID;
            $amount =  $row->amount;
            $guestID =  $row->guestID;
            $invoiceUrl =  $row->invoiceUrl;

        }

        // wp_send_json_success(['data' => getGuest($propertyID,$guestID)]);

        if ($invoiceUrl != null){

          //generate pdf
          $generate_pdf = generatePDF($code,$transactionID);
          $url_file_web = URL_WEB_FILES_PDF.basename($invoiceUrl);

          wp_send_json_success(['data' => 'Generado con éxito.', 'name_file' => $invoiceUrl, 'rell' => $url_file_web]);
          
        }else{

          //solicitar fecae afip
          $data_fecae = FECAESolicitar($xml_token,$xml_sign,CUIT,$code_transaction);

            if(file_put_contents(plugin_dir_path( __FILE__ )."../invoicesxml/".$transactionID.".xml",$data_fecae)){

                //generate pdf
                $generate_pdf = generatePDF($code,$transactionID);
                $url_file_web = URL_WEB_FILES_PDF.basename($generate_pdf);

                $save_name_file = saveUrlFile($code,$generate_pdf);
                
                if($save_name_file){
                    wp_send_json_success(['data' => 'Generado con éxito.',     'name_file' => $generate_pdf, 'rell' => $url_file_web]);
                }else{
                    wp_send_json_error(['data' => 'error.', 'name_file' => $generate_pdf, 'rell' => $url_file_web]);
                }

            }else{
                wp_send_json_success(['data' => 'Generado con éxito.', 'name_file' => 'error', 'rell' => 'https://google.com']);
            }

        }

        // if (file_exists(plugin_dir_path( __FILE__ )."../invoicesxml/".$transactionID.".xml")){

        //         $generate_pdf = generatePDF($code,$transactionID);
                
        //         wp_send_json_success(['data' => 'Generado con éxito.', 'name_file' => $transactionID, 'rell' => $generate_pdf]);
          
        // }else{

        //   $data_fecae = FECAESolicitar($xml_token,$xml_sign,CUIT,$code_transaction);

        //     if(file_put_contents(plugin_dir_path( __FILE__ )."../invoicesxml/".$transactionID.".xml",$data_fecae)){
        //         $generate_pdf = generatePDF($code,$transactionID);
                
        //         wp_send_json_success(['data' => 'Generado con éxito.', 'name_file' => $transactionID, 'rell' => $generate_pdf]);
        //     }else{
        //         wp_send_json_success(['data' => 'Generado con éxito.', 'name_file' => 'error', 'rell' => 'https://google.com']);
        //     }


        // }

    }

}
add_action( 'wp_ajax_foo', 'ajax_foo_handler' );        // for authenticated users


function getToken($code) {
 
    global $client_id_global,$client_secret_global;

    $content = "grant_type=authorization_code&code=".$code."&redirect_uri=".CALLBACK_URL;
    $authorization = base64_encode("$client_id_global:$client_secret_global");

    $header = array("Authorization: Basic {$authorization}","Content-Type: application/x-www-form-urlencoded");
 
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

function refreshToken($refresh_token) {
    global $client_id_global, $client_secret_global;
 
    $content = "grant_type=refresh_token&refresh_token=".$refresh_token."&redirect_uri=".CALLBACK_URL;
    $authorization = base64_encode("$client_id_global:$client_secret_global");

    $header = array("Authorization: Basic {$authorization}","Content-Type: application/x-www-form-urlencoded");
 
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

function getTransactions($access_token){

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
        'Authorization: Bearer '.$access_token
      ),
    ));
    
    $response = curl_exec($curl);
    
    curl_close($curl);

    $data_res = json_decode($response);
    $verifyData = saveTransactions($data_res);

    return $verifyData;
    
}

function getTransactionsByDate($access_token,$datefrom){

    $content = "resultsFrom=".$datefrom."&resultsTo=".$datefrom;

    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => TRANSACTIONS_URL.'?'.$content,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'GET',
      CURLOPT_POSTFIELDS => 'status=confirmed',
      CURLOPT_HTTPHEADER => array(
        'Authorization: Bearer '.$access_token
      ),
    ));
    
    $response = curl_exec($curl);
    
    curl_close($curl);
    
    $data_res = json_decode($response);
    $verifyData = saveTransactions($data_res);

    return $verifyData;

}

function saveTransactions($resource){

    global $wpdb, $table_name_transactions, $access_token_global;

    if(is_array($resource->data) && sizeof($resource->data) > 0){
        foreach($resource->data as $res){

            $data_count = checkDatabase($res->transactionID);

            if($data_count > 0){

            }else{
                $data_insert = $wpdb->insert(
                    $table_name_transactions, //table
                    array('propertyID' => $res->propertyID, 'transactionID' => $res->transactionID, 'reservationID' => $res->reservationID, 'guestID' => $res->guestID, 'transactionDateTime' => $res->transactionDateTime, 'completeName' => $res->guestName, 'description' => $res->description, 'amount' => $res->amount, 'currency' => $res->currency, 'transactionType' => $res->transactionType), //data
                    array('%s','%s','%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s') //data format			
                );
            }

        }

    }else{
        return false;
    }

    return true;

}

function checkDatabase($transactionID){

    global $wpdb;

    $table_name_transactions = $wpdb->prefix . "hotels_transactions";

    $total = $wpdb->get_var( "SELECT COUNT(transactionID) FROM $table_name_transactions WHERE transactionID = '$transactionID'");
    return $total;

}

function getDataByGuest(){

  global $wpdb, $table_name_transactions, $access_token_global;

  try {

      $pass_dni = '';
      $data_reserv = $wpdb->get_results($wpdb->prepare("SELECT * from $table_name_transactions"));
      foreach ($data_reserv as $dat_all) {
  
          if($dat_all->country != null){
  
          }else{
              $data_guest = getGuest($dat_all->propertyID,$dat_all->guestID);

              $i = 0;
              foreach($data_guest->data->customFields as $dat_arr){//for para extraer el nro de dni o passport en el primer elemento
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

function getGuest($property_id,$guest_id){

  global $access_token_global;

  $content = "propertyID=".$property_id."&guestID=".$guest_id;

  $curl = curl_init();

  curl_setopt_array($curl, array(
    CURLOPT_URL => GUEST_URL.'?'.$content,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'GET',
    CURLOPT_POSTFIELDS => 'status=confirmed',
    CURLOPT_HTTPHEADER => array(
      'Authorization: Bearer '.$access_token_global
    ),
  ));
  
  $response = curl_exec($curl);
  
  curl_close($curl);
  
  return json_decode($response);
  
}

function fetch_import($resultsFrom,$resultsTo,$reservationID){
    
    global $wpdb, $table_name_transactions, $access_token_global;

    $content = "resultsFrom=".$resultsFrom."&resultsTo=".$resultsTo."&reservationID=".$reservationID;

    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => TRANSACTIONS_URL.'?'.$content,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'GET',
      CURLOPT_POSTFIELDS => 'status=confirmed',
      CURLOPT_HTTPHEADER => array(
        'Authorization: Bearer '.$access_token_global
      ),
    ));
    
    $response = curl_exec($curl);
    
    curl_close($curl);

    $data_res = json_decode($response);
    $verifyData = saveTransactions($data_res);

    wp_send_json_success(['data' => $verifyData, 'url_search' => CALLBACK_URL.'&resultsFrom='.$resultsFrom.'&resultsTo='.$resultsTo.'&reservationID='.$reservationID]);

}

#------------------------------------------------------------------------------
# You shouldn't have to change anything below this line!!!
#==============================================================================
function CreateTRA($SERVICE)
{
  $TRA = new SimpleXMLElement(
    '<?xml version="1.0" encoding="UTF-8"?>' .
    '<loginTicketRequest version="1.0">'.
    '</loginTicketRequest>');
  $TRA->addChild('header');
  $TRA->header->addChild('source','serialNumber=CUIT 20085031202, cn=facturacion');
  $TRA->header->addChild('destination','cn=wsaahomo,o=afip,c=ar,serialNumber=CUIT 33693450239');
  $TRA->header->addChild('uniqueId',date('U'));
  $TRA->header->addChild('generationTime',date('c',date('U')-60));
  $TRA->header->addChild('expirationTime',date('c',date('U')+60));
  $TRA->addChild('service',$SERVICE);
  $TRA->asXML(plugin_dir_path( __FILE__ ).'../afip/TRA_WSFE.xml');
}
#==============================================================================
# This functions makes the PKCS#7 signature using TRA as input file, CERT and
# PRIVATEKEY to sign. Generates an intermediate file and finally trims the 
# MIME heading leaving the final CMS required by WSAA.C:\\laragon\www\hotel\wp-content\plugins\hotels\php\afip\TRA_WSFE.xml
function SignTRA()
{
$certificado="file://".plugin_dir_path( __FILE__ )."../afip/cert/certificado.crt";
$privatekey="file://".plugin_dir_path( __FILE__ )."../afip/cert/MiClavePrivada.key";
$args = array(
               'extracerts' => $certificado,
               'friendly_name' => 'My signed cert by CA certificate'
              );
 // $STA=openssl_pkcs12_export($pedido,$certificado,$privatekey, "caruso12021991", $args);  
  // $tra="../afip/cert/TRA_WSFE.xml";
  $STATUS=openssl_pkcs7_sign(plugin_dir_path( __FILE__ )."../afip/TRA_WSFE.xml", plugin_dir_path( __FILE__ )."../afip/TRA_WSFE.tmp", "file://".plugin_dir_path( __FILE__ ).CERT,
    array("file://".plugin_dir_path( __FILE__ ).PRIVATEKEY, PASSPHRASE),
    array(),
    !PKCS7_DETACHED
    );
  if (!$STATUS) {exit("ERROR generating PKCS#7 signature\n");}
  $inf=fopen(plugin_dir_path( __FILE__ )."../afip/TRA_WSFE.tmp" , "r");
  $i=0;
  $CMS="";
  while (!feof($inf)) 
    { 
      $buffer=fgets($inf);
      if ( $i++ >= 4 ) {$CMS.=$buffer;}
    }
  fclose($inf);
#  unlink("TRA_WSFE.xml");
  unlink(plugin_dir_path( __FILE__ )."../afip/TRA_WSFE.tmp");
  return $CMS;
}
#==============================================================================
function CallWSAA($CMS)
{
  $client=new SoapClient(plugin_dir_path( __FILE__ ).WSDL, array(
          
          'soap_version'   => SOAP_1_2,
          'location'       => URL,
          'trace'          => 1,
          'exceptions'     => 0
          )); 
  $results=$client->loginCms(array('in0'=>base64_encode($CMS)));
  file_put_contents(plugin_dir_path( __FILE__ )."../afip/request-loginCms.xml",$client->__getLastRequest());
  file_put_contents(plugin_dir_path( __FILE__ )."../afip/response-loginCms.xml",$client->__getLastResponse());
  if (is_soap_fault($results)) 
    {exit("SOAP Fault: ".$results->faultcode."\n".$results->faultstring."\n");}
  return $results->loginCmsReturn;
}
#==============================================================================
function ShowUsage($MyPath)
{
  printf("Uso  : %s Arg#1 Arg#2\n", $MyPath);
  printf("donde: Arg#1 debe ser el service name del WS de negocio.\n");
  printf("  Ej.: %s wsfe\n", $MyPath);
}

function FECAESolicitar($Token,$Sign,$Cuit,$code_transaction)
{

    global $wpdb, $table_name_transactions;

    //Iniciamos la consulta a database para traer datos importantes de la factura

    $sql_res = "SELECT id,transactionID,passportNumber,amount FROM $table_name_transactions WHERE id=".$code_transaction;
    $res_t = $wpdb->get_results($sql_res) or die(mysql_error());

    foreach($res_t  as $key => $row) {
        // each column in your row will be accessible like this
        $transactionID =  $row->transactionID;
        $passportNumber =  $row->passportNumber;
        $amount = $row->amount;
    }
    
    $sf = preg_replace('/[^0-9]/', '', $passportNumber);

    //calculo de subtotal number_format($dat1,2,',','.')
    $monto_n = calc_neto($amount);
    $monto_neto = round($monto_n, 2);

    //calculo de iva
    $monto_i = calc_iva($monto_n);
    $monto_iva = round($monto_i, 2);

    //obtener el ultimo comprobante autorizado
    $get_last_comp_autorizado = FECompUltimoAutorizado($Token,$Sign,$Cuit);
    $plainXML = mungXML( trim($get_last_comp_autorizado) );
    $xml_now=SimpleXML_Load_String($plainXML, 'SimpleXMLElement', LIBXML_NOCDATA);

    $last_comp_autorizado = intval(json_decode($xml_now->soap_Body->FECompUltimoAutorizadoResponse[0]->FECompUltimoAutorizadoResult->CbteNro, true)) + 1;
    
    //Iniciamos la consulta a la api FECAESolicitar

    $soapUrl = "https://wswhomo.afip.gov.ar/wsfev1/service.asmx?op=FECAESolicitar";

    date_default_timezone_set('America/Argentina/Buenos_Aires');
    $date_now = intval(date('Ymd'));

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
                            <PtoVta>3</PtoVta>
                            <CbteTipo>6</CbteTipo>
                          </FeCabReq>
                          <FeDetReq>
                            <FECAEDetRequest>
                              <Concepto>1</Concepto>';

    if(strlen($sf) > 0){
        $xml_post_string .=
            '<DocTipo>94</DocTipo>
                              <DocNro>' . $sf . '</DocNro>';
    }else{
        $xml_post_string .=
            '<DocTipo>99</DocTipo>';
    }

    $xml_post_string .=
        '<CbteDesde>' . $last_comp_autorizado . '</CbteDesde>
                              <CbteHasta>' . $last_comp_autorizado . '</CbteHasta>
                              <CbteFch>' . $date_now . '</CbteFch>
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

    $headers = ["POST /wsfev1/service.asmx HTTP/1.1", "Host: wswhomo.afip.gov.ar", "Content-Type: text/xml; charset=utf-8", "Content-Length: " . strlen($xml_post_string)];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $soapUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $xml_post_string);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

    $respons = curl_exec($ch);
    curl_close($ch);

    return $respons;
    // return $amount.' - '.$monto_total.' - '.$monto_iva.' - '.$monto_trib;

}

function FECompUltimoAutorizado($Token,$Sign,$Cuit)
{

    global $wpdb, $table_name_transactions;

    //Iniciamos la consulta a la api FECompUltimoAutorizado

    $soapUrl = "https://wswhomo.afip.gov.ar/wsfev1/service.asmx?op=FECompUltimoAutorizado";

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
                      <PtoVta>3</PtoVta>
                      <CbteTipo>6</CbteTipo>
                    </FECompUltimoAutorizado >
                  </soap:Body>
                </soap:Envelope>';


    $headers = ["POST /wsfev1/service.asmx HTTP/1.1", "Host: wswhomo.afip.gov.ar", "Content-Type: text/xml; charset=utf-8", "Content-Length: " . strlen($xml_post_string)];

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

function generatePDF($code,$transactionID){

    global $wpdb, $table_name_transactions;

    //Iniciamos la consulta a database para traer datos importantes de la factura

    $sql_res = "SELECT id,transactionID,reservationID,description,passportNumber,completeName,amount,city,address FROM $table_name_transactions WHERE id=".$code;
    $res_t = $wpdb->get_results($sql_res) or die(mysql_error());

    foreach($res_t  as $key => $row) {
        // each column in your row will be accessible like this
        $passportNumber =  $row->passportNumber;
        $completeName =  $row->completeName;
        $amount =  $row->amount;
        $description =  $row->description;
        $reservationID =  $row->reservationID;
        $city =  $row->city;
        $address =  $row->address;
    }

    //calculo de subtotal number_format($dat1,2,',','.')
    $monto_n = calc_neto($amount);
    $monto_neto = round($monto_n, 2);

    //calculo de iva
    $monto_i = calc_iva($monto_n);
    $monto_iva = round($monto_i, 2);

    $xml_file=file_get_contents(plugin_dir_path( __FILE__ )."../invoicesxml/".$transactionID.".xml");

    $doc = new DOMDocument();
    $doc->loadXML($xml_file);

    $pto_venta = $doc->getElementsByTagName('PtoVta')->item(0)->nodeValue;

    $num_factura = $doc->getElementsByTagName('CbteDesde')->item(0)->nodeValue;

    $orgDate = $doc->getElementsByTagName('FchProceso')->item(0)->nodeValue;  
    $newDate = date("d/m/Y", strtotime($orgDate)); 
    
    $nro_cae = $doc->getElementsByTagName('CAE')->item(0)->nodeValue;  
    $date_cae = $doc->getElementsByTagName('CAEFchVto')->item(0)->nodeValue;  
    $fecha_cae = date("d/m/Y", strtotime($date_cae)); 
    
    $date_process = $doc->getElementsByTagName('FchProceso')->item(0)->nodeValue;  
    $fecha_process = date("d_m_Y_h_i_s", strtotime($date_process)); 
    $name_file = $reservationID.'_'.$fecha_process;
    
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
                                <li><b>Razón Social:</b> DOS SANTOS LARA EDUARDO CESAR</li>
                                <li><b>Domicilio Comercial:</b> San Martin 127 Piso:10 Dpto:1004 - San Carlos De Bariloche, Río Negro</li>
                                <li><b>Condición frente al IVA:</b> IVA Responsable Inscripto</li>
                            </ul>
                        </td>

                        <td width="261">
                            <ul style="list-style-type: none;font-size:14px">
                                <li><b>Punto de Venta:</b> 0000'.$pto_venta.' Comp. Nro: 0000'.$num_factura.'</li>
                                <li><b>Fecha de Emisión:</b> '.$newDate.'</li>
                                <li>
                                    <b>CUIT:</b> 20085031202<br>
                                    <b>Ingresos Brutos:</b> 45586616<br>
                                    <b>Fecha de Inicio de Actividades:</b> 01/03/2010
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
                            <p style="font-size:14px">Período Facturado Desde: '.$newDate.'</p>
                        </td>

                        <td class="whitet" width="172">
                            <p style="font-size:14px;text-align:center">Hasta: '.$newDate.'</p>
                        </td>

                        <td class="whitet" width="172">
                            <p style="font-size:14px">Fecha de Vto. para el pago: '.$newDate.'</p>
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
        
        if($passportNumber != null OR $passportNumber != ''){
            $html .= '<li><b>Doc.:</b> '.$passportNumber.'</li>';
        }

        $html .= '<li><b>Condición frente al IVA:</b> Consumidor Final</li>
                                <li><b>Condición de venta:</b> Contado</li>
                            </ul>
                        </td>
                        <td width="261">
                            <ul style="list-style-type: none;font-size:14px">
                                <li><b>Apellido y Nombre / Razón Social:</b> '.$completeName.'</li>';

        if($city != null OR $city != ''){
            $html .= '<li><b>Domicilio:</b> '.$city.' '.$address.'</li>
                        </ul>
                            </td>';
        }

        $html .= '</tr>
                </table>
            </td>
        </tr>';

        $html .= '<tr height="340px">
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
                        <td class="whitet">BOOKING ID: '.$reservationID.'</td>
                        <td class="whitet">1,00</td>
                        <td class="whitet">unidades</td>
                        <td class="whitet">'.$monto_neto.'</td>
                        <td class="whitet">0,00</td>
                        <td class="whitet">0,00</td>
                        <td class="whitet">'.$monto_neto.'</td>
                    </tr>
                    </tbody>
                </table>
            </td>
        </tr>

        <tr height="340px"></tr>

    </tbody>
  </table>

  <div style="position:fixed;bottom: 120px;left: 0px;right: 0px;height: 0px;">

    <table border=1 width="100%" class="whitet">
        <tbody>
            <tr>
                <td align="right" class="gray">
                    <ul style="list-style-type: none;">
                        <li><b>Subtotal:</b> $'.$monto_neto.'</li>
                        <li><b>Importe Otros Tributos:</b> $'.$monto_iva.'</li>
                        <li><b>Importe Total:</b> $'.$amount.'</li>
                    </ul>
                </td>
            </tr>
        </tbody>
    </table>
    
    <table width="100%" style="border:1px solid white">
        <tbody>
            <tr>
                <td style="border:1px solid white" align="right">
                    <ul style="list-style-type: none;font-size:16px">
                        <li><b>CAE N°:</b> '.$nro_cae.'</li>
                        <li><b>Fecha de Vto. de CAE:</b> '.$fecha_cae.'</li>
                    </ul>
                </td>
            </tr>
        </tbody>
    </table>

  </div>
  </main>

    </body>
    ';

  // instantiate and use the dompdf class
  $dompdf = new Dompdf();

  // Cargamos el contenido HTML.
  $dompdf->load_html($html);

  // (Optional) Setup the paper size and orientation
  $dompdf->setPaper('A4', 'portrait');

  // Render the HTML as PDF
  $dompdf->render();

  // Output the generated PDF to variable and return it to save it into the file
  $output = $dompdf->output();

  $filen = $name_file.'.pdf';

  file_put_contents(plugin_dir_path( __FILE__ )."../invoicespdf/".$name_file.".pdf",$output);

  return $filen;

}

function saveUrlFile($code,$name_file){

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

function calc_neto($val){

  $value = $val * 100 / 121;
  return $value;

}

function calc_iva($val){

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

function CheckNumber($x) {
  if ($x > 0)
    {$message = "Positive";}
  if ($x == 0)
    {$message = "Zero";}
  if ($x < 0)
    {$message = "Negative";}
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
    foreach ($nsm as $key)
    {
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