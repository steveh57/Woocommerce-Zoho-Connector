<?php
/**
 * Order handling
 * Called when order is complete to post order in Zoho
 */
 // If this file is called directly, abort.
 
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}
 
include_once ( dirname( __FILE__ ) . '/bbz-definitions.php');

add_action( 'woocommerce_payment_complete', 'bbz_order_processing');

function bbz_order_processing( $order_id ){	
	// Order Setup Via WooCommerce
	$order = new WC_Order( $order_id );	
	$shipping = $order->get_shipping_method();	
	
	$order_api_fields = array(
		'customer_id'	=>	'',
		'custom_fields'	=> array (
			array (
				'index'	=>	1,		// Order source
				'value'	=>	'Website',
			),
		),
		'salesorder_number'	=> 'WO-'.$order->get_order_number();
		
	
	
	
	
	
	
	
	
	
	
		"clientID" => "WCoyote",
		"Order" => array(					
			"CustomerOrderID" => $order_id,			
			"ShippingType" => $shipping,	
			"OrderNote"	=> $order->get_customer_note( 'view' ),
			"BillingInformation" => array(								
				"BillingName" => $order->get_billing_first_name() . " " . $order->get_billing_last_name(),				
				"BillingCompany" => "",
				"BillingAddress1" => $order->get_billing_address_1(),
				"BillingAddress2" => $order->get_billing_address_2(),				
				"BillingCity" => $order->get_billing_city(),
				"BillingState" => $order->get_billing_state(),
				"BillingZipcode" => $order->get_billing_postcode(),
				"BillingCountry" => $order->get_billing_country(),
				"BillingPhoneNumber" => $order->get_billing_phone(),
				"BillingEmail" => $order->get_billing_email()
			),
			"ShippingInformation" => array(
				"ShippingName" => $order->get_shipping_first_name() . " " . $order->get_shipping_last_name(),				
				"ShippingAddress1" => $order->get_shipping_address_1(),
				"ShippingAddress2" => $order->get_shipping_address_2(),
				"ShippingCity" => $order->get_shipping_city(),
				"ShippingState" => $order->get_shipping_state(),
				"ShippingZipcode" => $order->get_shipping_postcode(),
				"ShippingCountry" => $order->get_shipping_country(),
				"ShippingPhoneNumber" => $order->get_billing_phone(),				
			)			
		)
	);		
	
	// Iterate Through Items
	$items = $order->get_items();	
	$line_num = 1;
	foreach ( $items as $item ) {
		  $quantity = $item->get_quantity();
		  $line_total = $item->get_total();
		  //Check if product/variation is a bundled item
		  $is_bundle = wc_pb_is_bundle_container_order_item( $item );
			if($is_bundle){//If bundled, loop through
				$bundled_items = wc_pb_get_bundled_order_items( $item, $order );				
				//Go through each product
				foreach($bundled_items AS $bun_item){
					 $product_id = $bun_item->get_product_id();								
					 //Check for Variation ID first & use Variation, if it exists
					 $product_variation_id = $bun_item->get_variation_id();		
					 // Check if product has variation.
					 if ($product_variation_id){ 
						$product = new WC_Product_Variation($product_variation_id);			
					 }else{
						$product = $bun_item->get_product();				
					 }
					 $post_data = process_product($product,$quantity,$line_total,$line_num);
					 if($post_data){					
						//Add to Products in Array
						$order_api_fields['Order']['Products'][] = $post_data;
						$line_num++;
					 }
				}
			}else{//process a single product			  
				//Have to check that this is not a bundled item (those are processed above in terms of the bundle
				$is_bundle_child = wc_pb_is_bundled_order_item( $item, $order );
				if(!$is_bundle_child){
					$product_id = $item->get_product_id();				
					error_log("Product ID NOT in Bundle:" . $product_id);
					 //Check for Variation ID first & use Variation, if it exists
					 $product_variation_id = $item->get_variation_id();		
					 // Check if product has variation.
					 if ($product_variation_id){ 
						$product = new WC_Product_Variation($product_variation_id);			
					 }else{
						$product = $item->get_product();				
					 }
					 $post_data = process_product($product,$quantity,$line_total,$line_num);
					 if($post_data){					
						//Add to Products in Array
						$order_api_fields['Order']['Products'][] = $post_data;
						$line_num++;
					 }
				}
			}
	}	
	if(isset($order_api_fields['Order']['Products'][0])){			
		
		//Authenticate (get token)		
		$auth_url = "https://acmefulfillment.com/api/authenticate";
		$headers = array(
			'X-Auth-Username' => '12345ABCDE',
			'X-Auth-Password' => '000-111-222-3333',
			'Accept' => 'application/json'
		);
		
			$response = wp_remote_post( $auth_url,
				array(
					'headers' => $headers,
						'method' => 'POST',
						'timeout' => 75,
						'body' => "",
						'httpversion' => '1.0'						
					)
				);

				if ( is_wp_error( $response ) ) {
					$errorResponse = $response->get_error_message();
					error_log("Error Message Acme Auth:" . print_r($errorResponse,true));
				}			
				$auth = json_decode($response['body'],true);
				$token = $auth['token'];			
		
		$url = "https://acmefulfillment.com/api/order";
		$headers_item = array(
			'X-Auth-Token' => $token,		
			'Content-Type' => 'application/json'
		);		
		$response = wp_remote_post( $url,
				array(
					'headers' => $headers_item,
						'method' => 'POST',
						'timeout' => 75,
						'body' => json_encode($order_api_fields),
						'httpversion' => '1.0'
					)
				);

				if ( is_wp_error( $response ) ) {
					$errorResponse = $response->get_error_message();
					error_log("Error Message Acme Order Submit: " . print_r($errorResponse,true));
				}else{
					$order->update_meta_data( 'Acme Order ID', $response['body'] );
					$order->save(); 
				}
	}//end check for products to send to Acme
}//end order_to_acme_api()

function process_product($product,$quantity,$line_total,$line_num){
	$sku = $product->get_sku();
	$format = $product->get_attribute( 'Format' );		
	//Check for does *not* contain "eBook"
	if(stripos($format,"eBook")===false){	
		$single_item = array();
			//assemble single product details
			$single_item['LineNumber'] = $line_num;			
			$single_item['ProductSKU'] = $sku;
			$single_item['Qty'] = $quantity;			
			$single_item['Price'] = $line_total;
		return $single_item;
	}else return false;
}//end process_product()