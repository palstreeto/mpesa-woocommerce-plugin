<?php



// In order to prevent direct access to the plugin

 defined('ABSPATH') or die("No access please!");



// Plugin header- notifies wordpress of the existence of the plugin



/* Plugin Name: Mbiki WooCommerce M-PESA Payment Gateway Pro

* Plugin URI: https://www.payment-plugins.com/page/integrating-mpesa-with-your-woocommerce-plugin

* Description: Mbikipay M-PESA Payment Gateway for woocommerce. 

* Version: 2.2.0 

* Author: Sir Brian Mbiki

* Author URI: https://paymentprocessor-script.com/

* Licence: GPL2 

* WC requires at least: 2.2

* WC tested up to: 8.7.0

*/



add_action('plugins_loaded', 'woompesa_payment_gateway_init');


//defining the classclass



/**

 * M-PESA Payment Gateway

 *

 * @class          WC_Gateway_Mpesa

 * @extends        WC_Payment_Gateway

 * @version        2.2.0

 */

 

 function woompesa_adds_to_the_head() {

 

   wp_enqueue_script('Callbacks', plugin_dir_url(__FILE__) . 'trxcheck.js', array('jquery'));

   wp_enqueue_style( 'Responses', plugin_dir_url(__FILE__) . '/display.css',false,'1.1','all');

 

}

//Add the css and js files to the header.

add_action( 'wp_enqueue_scripts', 'woompesa_adds_to_the_head' );

//Calls the woompesa_mpesatrx_install function during plugin activation which creates table that records transactions.



register_activation_hook(__FILE__,'woompesa_mpesatrx_install');



//Request payment function start//



add_action( 'init', function() {

    /** Add a custom path and set a custom query argument. */

    add_rewrite_rule( '^/payment/?([^/]*)/?', 'index.php?payment_action=1', 'top' );

} );



add_filter( 'query_vars', function( $query_vars ) {

    /** Make sure WordPress knows about this custom action. */

    $query_vars []= 'payment_action';

    return $query_vars;

} );



add_action( 'wp', function() {

    /** This is an call for our custom action. */

    if ( get_query_var( 'payment_action' ) ) {

        // your code here

		woompesa_request_payment();

    }

} );

//Request payment function end//



//Callback handler function start



add_action( 'init', function() {

   

    add_rewrite_rule( '^/callback/?([^/]*)/?', 'index.php?callback_action=1', 'top' );

} );



add_filter( 'query_vars', function( $query_vars ) {

    

    $query_vars []= 'callback_action';

    return $query_vars;

} );



add_action( 'wp', function() {

   

    if ( get_query_var( 'callback_action' ) ) {

        // invoke callback function

		woompesa_callback_handler();

    }

} );



//Callback handler function end



//Callback scanner function start

add_action( 'init', function() {

    

    add_rewrite_rule( '^/scanner/?([^/]*)/?', 'index.php?scanner_action=1', 'top' );

} );



add_filter( 'query_vars', function( $query_vars ) {

    

    $query_vars []= 'scanner_action';

    return $query_vars;

} );



add_action( 'wp', function() {

  

    if ( get_query_var( 'scanner_action' ) ) {

        // invoke scanner function

		woompesa_scan_transactions();

    }

} );

//Callback scanner function end

function woompesa_payment_gateway_init() {


    if( !class_exists( 'WC_Payment_Gateway' )) return;


class WC_Gateway_Mpesa extends WC_Payment_Gateway {





/**

*  Plugin constructor for the class

*/

public function __construct(){		

		

		if(!isset($_SESSION)){			        session_start(); 				}

        // Basic settings

		$this->id                 = 'mpesa';

		$this->icon               = plugin_dir_url(__FILE__) . 'logo.jpg';

        $this->has_fields         = false;

        $this->method_title       = __( 'M-PESA', 'woocommerce' );

        $this->method_description = __( 'Enable customers to make payments to your business shortcode' );

       

        // load the settings

        $this->init_form_fields();

        $this->init_settings();



        // Define variables set by the user in the admin section

        $this->title            = $this->get_option( 'title' );

        $this->description      = $this->get_option( 'description' );

        $this->instructions     = $this->get_option( 'instructions', $this->description );

        $this->mer              = $this->get_option( 'mer' );						$_SESSION['head_office']			= $this->get_option('ho');						$_SESSION['identity_type']			= $this->get_option('identity_type');

		$_SESSION['credentials_endpoint']   = $this->get_option( 'credentials_endpoint' ); 

		$_SESSION['payments_endpoint']   	= $this->get_option( 'payments_endpoint' ); 

		$_SESSION['passkey']      			= $this->get_option( 'passkey' ); 

		$_SESSION['ck']      				= $this->get_option( 'consumer_key' ); 

		$_SESSION['cs']   					= $this->get_option( 'consumer_secret' );

		$_SESSION['shortcode']  			= $this->get_option( 'shortcode' ); 

				



        //Save the admin options

        if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {



            add_action( 'woocommerce_update_options_payment_gateways_'.$this->id, array( $this, 'process_admin_options' ) );



        } else {



            add_action( 'woocommerce_update_options_payment_gateways', array( $this, 'process_admin_options' ) );



        }



        add_action( 'woocommerce_receipt_mpesa', array( $this, 'receipt_page' ));

		



    }



/**

*Initialize form fields that will be displayed in the admin section.

*/



public function init_form_fields() {



    $this->form_fields = array(

        'enabled' => array(

            'title'   => __( 'Enable/Disable', 'woocommerce' ),

            'type'    => 'checkbox',

            'label'   => __( 'Enable Mpesa Payments Gateway', 'woocommerce' ),

            'default' => 'yes'

            ),

        'title' => array(

            'title'       => __( 'Title', 'woocommerce' ),

            'type'        => 'text',

            'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),

            'default'     => __( 'M-PESA', 'woocommerce' ),

            'desc_tip'    => true,

            ),

        'description' => array(

            'title'       => __( 'Description', 'woocommerce' ),

            'type'        => 'textarea',

            'description' => __( 'Payment method description that the customer will see on your checkout.', 'woocommerce' ),

            'default'     => __( 'Place order and pay using M-PESA.'),

            'desc_tip'    => true,

            ),

        'instructions' => array(

            'title'       => __( 'Instructions', 'woocommerce' ),

            'type'        => 'textarea',

            'description' => __( 'Instructions that will be added to the thank you page and emails.', 'woocommerce' ),

            'default'     => __( 'Place order and pay using M-PESA.', 'woocommerce' ),

                // 'css'         => 'textarea { read-only};',

            'desc_tip'    => true,

            ),

        'mer' => array(

            'title'       => __( 'Merchant Name', 'woocommerce' ),

            'description' => __( 'Company name', 'woocommerce' ),

            'type'        => 'text',

            'default'     => __( 'Company Name', 'woocommerce'),

            'desc_tip'    => false,

            ),			'identity_type' => array( 					'title'       => __( 'Identifier Type', 'woocommerce' ),					'type'        => 'select',					'options' => array(				      					      	1 => __( 'Paybill Number', 'woocommerce' ),				     	2 => __( 'Till Number', 'woocommerce' )				    ),					'description' => __( 'Identifier Type for M-PESA', 'woocommerce' ),					'desc_tip'    => false,			),
		'credentials_endpoint' => array(

			'title'       =>  __( 'Credentials Endpoint(Sandbox/Production)', 'woocommerce' ),

			'default'     => __( 'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials', 'woocommerce'),
			
			'description' => __( 'Replace \'api\' in the endpoint with \'sandbox\' for testing in sandbox', 'woocommerce' ),

			'type'        => 'text',
			

		),				

		'payments_endpoint' => array(

			'title'       =>  __( 'Payments Endpoint(Sandbox/Production)', 'woocommerce' ),

			'default'     => __( 'https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest', 'woocommerce'),
			
			'description' => __( 'Replace \'api\' in the endpoint with \'sandbox\' for testing in sandbox', 'woocommerce' ),

			'type'        => 'text',

		),

		'passkey' => array(

			'title'       =>  __( 'PassKey', 'woocommerce' ),

			 'default'     => __( '', 'woocommerce'),

			'type'        => 'password',

		),

		

		'consumer_key' => array(

			'title'       =>  __( 'Consumer Key', 'woocommerce' ),

			 'default'     => __( '', 'woocommerce'),

			'type'        => 'password',

		),

		'consumer_secret' => array(

			'title'       =>  __( 'Consumer Secret', 'woocommerce' ),

			 'default'     => __( '', 'woocommerce'),

			'type'        => 'password',

		),		'ho' => array( 					'title'       => __( 'Head Office Number/Store Number', 'woocommerce' ),					'type'        => 'text',					'description' => __( 'Paybill Number(for Paybill)/HO or Store Number(for Till Number)', 'woocommerce' ),					'default'     => __( '', 'woocommerce' ),					'desc_tip'    => false,				 ),
		'shortcode' => array(
			'title'       =>  __( 'Shortcode', 'woocommerce' ),
			'default'     => __( '', 'woocommerce'),
			'description' => __( 'Paybill or Till Number'),
			'type'        => 'number',

		)

		);

}







/**

 * Generates the HTML for admin settings page

 */

public function admin_options(){

    /*

     *The heading and paragraph below are the ones that appear on the backend M-PESA settings page

     */

    echo '<h3>' . 'M-PESA Payments Gateway' . '</h3>';

    

    echo '<p>' . 'Payments Made Simple' . '</p>';

    

    echo '<table class="form-table">';

    

    $this->generate_settings_html( );

    

    echo '</table>';

}



/**

 * Receipt Page

 **/

public function receipt_page( $order_id ) {



    echo $this->woompesa_generate_iframe( $order_id );



}



/**

 * Function that posts the params to mpesa and generates the html for the page

 */

public function woompesa_generate_iframe( $order_id ) {



    global $woocommerce;



    $order = new WC_Order ( $order_id );

    $_SESSION['total'] = (int)$order->order_total;

    $tel = $order->billing_phone;



    //cleanup the phone number and remove unecessary symbols

    $tel = str_replace("-", "", $tel);



    $tel = str_replace( array(' ', '<', '>', '&', '{', '}', '*', "+", '!', '@', '#', "$", '%', '^', '&'), "", $tel );

	

	$_SESSION['tel'] = "254".substr($tel, -9);

	

	    



/**

 * Make the payment here by clicking on pay button and confirm by clicking on complete order button

 */

if ($_GET['transactionType']=='checkout') {

	
	echo "<h4>Payment Instructions:</h4>";

    echo "

		  1. Click on the <b>Pay</b> button in order to initiate the M-PESA payment.<br/>

		  2. Check your mobile phone for a prompt asking to enter M-PESA pin. If no prompt appears on your phone please get in touch with us for assistance (+254 706745202)<br/>

    	  3. Enter your <b>M-PESA PIN</b> and the amount specified on the 

    	  	notification will be deducted from your M-PESA account when you press send.<br/>

    	  4. When you enter the pin and click on send, you will receive an M-PESA payment confirmation message on your mobile phone.<br/>     	

    	  5. After receiving the M-PESA payment confirmation message please click on the <b>Complete Order</b> button below to complete the order and confirm the payment made.<br/>";

    echo "<br/>";?>

	

	<input type="hidden" value="" id="txid"/>	

	<?php echo $_SESSION['response_status']; ?>

	<div id="commonname"></div>

	<button onClick="pay()" id="pay_btn">Pay</button>

	<button onClick="x()" id="complete_btn">Complete Order</button>	

    <?php	

    echo "<br/>";



}



}



/**

* Process the payment field and redirect to checkout/pay page.

*

*

*

*/



public function process_payment( $order_id ) {



		$order = new WC_Order( $order_id );		



		$_SESSION["orderID"] = $order->id;      		

       // Redirect to checkout/pay page

        $checkout_url = $order->get_checkout_payment_url(true);

        $checkout_edited_url = $checkout_url."&transactionType=checkout";

        return array(

            'result' => 'success',

            'redirect' => add_query_arg('order', $order->id,

                add_query_arg('key', $order->order_key, $checkout_edited_url))

            ); 

}



}

}

/**

 * Telling woocommerce that mpesa payments gateway class exists

 * Filtering woocommerce_payment_gateways

 * Add the Gateway to WooCommerce

 **/



function woompesa_add_gateway_class( $methods ) {



    $methods[] = 'WC_Gateway_Mpesa';



    return $methods;



}



if(!add_filter( 'woocommerce_payment_gateways', 'woompesa_add_gateway_class' )){

    die;

}



//Create Table for M-PESA Transactions

function woompesa_mpesatrx_install() {

	

	global $wpdb;

	global $trx_db_version;

	$trx_db_version = '1.0';



	$table_name = $wpdb->prefix .'mpesa_trx';

	

	$charset_collate = $wpdb->get_charset_collate();



	$sql = "CREATE TABLE IF NOT EXISTS $table_name (

		id mediumint(9) NOT NULL AUTO_INCREMENT,

		order_id varchar(150) DEFAULT '' NULL,

		phone_number varchar(150) DEFAULT '' NULL,

		trx_time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,

		merchant_request_id varchar(150) DEFAULT '' NULL,

		checkout_request_id varchar(150) DEFAULT '' NULL,

		resultcode varchar(150) DEFAULT '' NULL,

		resultdesc varchar(150) DEFAULT '' NULL,

		processing_status varchar(20) DEFAULT '0' NULL,

		PRIMARY KEY  (id)

	) $charset_collate;";
		
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );

	add_option( 'trx_db_version', $trx_db_version );

		

}



//Payments start

function woompesa_request_payment(){

		if(!isset($_SESSION)){			        session_start(); 				}

		global $wpdb; 

		

		if(isset($_SESSION['ReqID'])){

		

		$table_name = $wpdb->prefix . 'mpesa_trx';

		$trx_count = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name WHERE merchant_request_id = '".$_SESSION['ReqID']."' and processing_status = 0");

		//If it exists do not allow the transaction to proceed to the next step

		if($trx_count > 0){

			echo json_encode(array("rescode" => "99", "resmsg" => "A similar transaction is in progress, to check its status click on Confirm Order button"));

			exit();

			}

		}

		$total = $_SESSION['total'];

		$url = $_SESSION['credentials_endpoint'];



    $YOUR_APP_CONSUMER_KEY =  $_SESSION['ck'];

    $YOUR_APP_CONSUMER_SECRET = $_SESSION['cs'];

    $credentials = base64_encode($YOUR_APP_CONSUMER_KEY . ':' . $YOUR_APP_CONSUMER_SECRET);



	//Request for access token

   

	$token_response = wp_remote_get( $url, array('headers' => array('Authorization' => 'Basic ' . $credentials)));	

	

	$token_array = json_decode('{"token_results":[' . $token_response['body'] . ']}');



	

    if (array_key_exists("access_token", $token_array->token_results[0])) {

        $access_token = $token_array->token_results[0]->access_token;

    } 

	else {		

		echo json_encode(array("rescode" => "1", "resmsg" => "Error, unable to send payment request"));

		exit();

    }



    ///If the access token is available, start lipa na mpesa process

    if (array_key_exists("access_token", $token_array->token_results[0])) {



        ////Starting lipa na mpesa process

        $url = $_SESSION['payments_endpoint'];

		

		$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";

		$domainName = $_SERVER['HTTP_HOST'].'/';

		$callback_url =  $protocol.$domainName;

				

		//Generate the password//

		$timestamp = date("YmdHis");

		$b64 = $_SESSION['head_office'].$_SESSION['passkey'] .$timestamp;

		$pwd = base64_encode($b64);

		

		///End in pwd generation//

		

        $curl_post_data = array(

            //Fill in the request parameters with valid values

            'BusinessShortCode' => $_SESSION['head_office'],

			'Password' => $pwd,

            'Timestamp' => $timestamp,

            'TransactionType' => ( $_SESSION['identity_type'] == 1 ) ? 'CustomerPayBillOnline' : 'CustomerBuyGoodsOnline',

            'Amount' => $total,

            'PartyA' => $_SESSION['tel'],

            'PartyB' => $_SESSION['shortcode'],

            'PhoneNumber' => $_SESSION['tel'],

            'CallBackURL' => $callback_url.'/index.php?callback_action=1',

            'AccountReference' => $_SESSION["orderID"],

            'TransactionDesc' => 'Online Payment'

        );



        $data_string = json_encode($curl_post_data);

		

		$response = wp_remote_post( $url, array('headers' => array('Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $access_token),

		'body'    => $data_string));	

		

		$response_array = json_decode('{"callback_results":[' . $response['body'] . ']}');	

		

		if(array_key_exists("ResponseCode", $response_array->callback_results[0]) && $response_array->callback_results[0]->ResponseCode == 0){

			$_SESSION['ReqID'] =$response_array->callback_results[0]->MerchantRequestID;

			woompesa_insert_transaction($_SESSION['ReqID']);			

				

			echo json_encode(array("rescode" => "0", "resmsg" => "Request accepted for processing, check your phone to enter M-PESA pin"));	

			

		}

		else{

			echo json_encode(array("rescode" => "1", "resmsg" => "Payment request failed, please try again"));	

			

		}

        exit();

		

    }



}



//Payments end



/////Scanner start

function woompesa_scan_transactions(){

//The code below is invoked after customer clicks on the Confirm Order button



// Get transaction data from the table and return the result to the user

		if(!isset($_SESSION)){			        session_start(); 				}

global $wpdb; 

$ReqId = $_SESSION['ReqID'];$orderID = $_SESSION["orderID"];$table_name = $wpdb->prefix . 'mpesa_trx';

$result = $wpdb->get_results( "SELECT * FROM $table_name WHERE merchant_request_id = '".$ReqId."' and processing_status = 1");

if(!empty($result)){

		if($result[0]->resultcode == "0"){

			

			global $woocommerce;

			

			$order = new WC_Order ($orderID);

				

			    // Reduce stock levels			

			 $order->reduce_order_stock();

				//Change status to completed

			 $order->update_status( 'completed' ); 

				// Remove cart contents				

			$woocommerce->cart->empty_cart();

				// Finally, destroy the session.

				session_destroy();

			echo json_encode(array("rescode" => "0", "resmsg" => "Order completed successfully"));



		}

        else if($result[0]->resultcode == "1032"){

			echo json_encode(array("rescode" => "1032", "resmsg" => "You have cancelled the payment request."));

						

		}

		 else if($result[0]->resultcode == "1001"){

			echo json_encode(array("rescode" => "1001", "resmsg" => "A similar transaction is in progress, please wait as we process the transaction."));

			

		}

		else if($result[0]->resultcode == "2001"){

			

			echo json_encode(array("rescode" => "2001", "resmsg" => "Wrong M-PESA pin entered, please click on pay and enter pin again."));

		}

		else if($result[0]->resultcode == "1"){

			

			echo json_encode(array("rescode" => "1", "resmsg" => "The balance is insufficient for the transaction."));

		}		

		else{

			echo json_encode(array("rescode" => "9990", "resmsg" => "Error encountered during payment processing"));

		}

}

else{

	echo json_encode(array("rescode" => "9999", "resmsg" => "Payment results not received, please pay first."));

}



exit();

}



////Scanner end



function woompesa_insert_transaction( $merchant_id ) {

		if(!isset($_SESSION)){			        session_start(); 				}

  global $wpdb; 

  $table_name = $wpdb->prefix . 'mpesa_trx';

  $wpdb->insert( $table_name, array(	'order_id' => $_SESSION["orderID"],	'phone_number' => $_SESSION['tel'],

    'merchant_request_id' => $merchant_id,

	'trx_time' => date("Y-m-d H:i:s")

  ) );

}



////Callback function start

function woompesa_callback_handler(){

	

 $postData = file_get_contents('php://input');



    //Get the callback results and add the result to the database....

	$encapsulate = '{"callback_results":[' . $postData . ']}';

	$json_data = json_decode($encapsulate, true);

	 $key1 = 0;

	 

	 $merchant_id = $json_data["callback_results"][$key1]["Body"]["stkCallback"]["MerchantRequestID"];

	 $checkout_id = $json_data["callback_results"][$key1]["Body"]["stkCallback"]["CheckoutRequestID"];

	 $rescode = $json_data["callback_results"][$key1]["Body"]["stkCallback"]["ResultCode"];

	 $resdesc = $json_data["callback_results"][$key1]["Body"]["stkCallback"]["ResultDesc"];

//Update the transaction here



	woompesa_update_transaction($merchant_id,$rescode,$resdesc);	

}	

function woompesa_update_transaction( $merchant_id,$rescode,$resdesc ) {

		

	  global $wpdb;

	 

	  $table_name = $wpdb->prefix . 'mpesa_trx';

	  $wpdb->update($table_name, array('resultcode' => $rescode,

			'resultdesc' => $resdesc, 'processing_status' => '1'),

			array('merchant_request_id' => $merchant_id), array('%s','%s', '%s'),

			 array('%s'));

	}



////Callback function end



?>
