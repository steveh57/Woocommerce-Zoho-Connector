<?php
/**
 * Testing functions for bb-zoho-connector
 *
 * 
 */
 
 // If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class bbz_test_form extends bbz_admin_form {

	private $testform = array (
			'name'		=>	'bbzform-test',
			'class'		=>	'bbz_test_form',
			'title'		=>	'<h2>Other Tests</h2>',
			'text_before'	=> '<p>Select the Zoho request to test</p>',
			'fields'	=>	array (
				'function'			=> array (
					'type'			=> 'select',
					'title'		=> 'Test',
					'options'	=> array (
						'get-order'			=> 'Get woo order (key=order no)',
						'get-product'		=> 'Get woo product (key=product id)',
						'get-user'			=> 'Get woo user (key=user id)',
						'get-shipping-classes' => 'Get wc shippng classes',
						'get-product-detail'	=> 'Call product function (key=product_id, value=function)',
						'availability'		=> 'Check product availability filters (key=product_id, value=user_id)',
						
						'call-function'		=> 'Call function filterkey(filtervalue)',
						
					//	'submit-order'		=> 'Submit order (key=order no) to Zoho',
						'process-orders'	=> 'New process outstanding orders',
						'get-user-meta'		=> 'Get user meta (key=user id, val=meta key(optional)',
						'delete-user-meta'	=> 'Delete user meta (key=user_id or ALL, val=meta key (required)',
						'get-post-meta'		=> 'Get post meta (key=post id, val=meta key(optional)',
						'show-options'		=> 'Show bbz option data',
						'set-option'		=> 'Set bbz option (key=>value)',
						'delete-option'		=> 'Delete bbz option(s)(key)',
						'product-filter'	=> 'Test product filter',
						'add-shipping-addresses' => 'Add a new shipping address (key=ALL or val=user id',
						'get_cross_sells'	=> 'Get all product cross sells',
						'get_trackship_row'	=> 'Get Trackship row (key=order id)',
						'get_sales_history'	=> 'Get Sales History',
					)
				),

				'filterkey'		=> array (
					'type'		=> 'text',
					'title'		=> 'Key'
				),
				'filtervalue'	=> array (
					'type'		=> 'text',
					'title'		=> 'Value'
				),
				'state'			=> array(    // Status hidden
					'type'          => 'hidden',
					'title'         => 'State',
					'value'			=>	'bbztest'
				)
			),
			'button'		=> array (
				'name'			=> 'submit',
				'type'			=> 'primary',
				'title'			=> 'Fetch data'
			)
	);


	function __construct () {

		parent::__construct($this->testform);
	}
	
	public function action () {
	}

	public function display_data () {
		$function = $this->options->get('function');
		$filterkey = $this->options->get ('filterkey');
		$filtervalue = $this->options->get ('filtervalue');
		$filtervalue = strstr ($filtervalue, ',') ? str_getcsv ($filtervalue) : $filtervalue;
		$filter = array($filterkey=>$filtervalue);
		
		if (empty ( $function )) return false;
		echo '<h2>Results for "'.$function.'"</h2>';
		switch ($function) {
		
		case 'get-order':
			$data = wc_get_order ( $filterkey);
			break;
			
		case 'get-product':
			$data = wc_get_product ( $filterkey);
			break;

		case 'get-user':
			$data = get_userdata ( $filterkey);
			break;
			
		case 'availability':
			$product_id = $filterkey;
			$user_id = $filtervalue;
			$product = wc_get_product ( $product_id);
			$data = array (
				'Product' => array(
					'name' => $product->get_name(),
					'is_visible' => $product->is_visible(),
					'is_purchasable' => $product->is_purchasable(),
					'get_backorders' => $product->get_backorders(),
					'get_stock_status' => $product->get_stock_status(),
					'get_stock_quantity' => $product->get_stock_quantity() ),
				'User role' => get_user_by('ID', $user_id)->roles,
				'BBZ_PM_AVAILABILITY' => get_post_meta ($product_id, BBZ_PM_AVAILABILITY, true),
				'BBZ_PM_RESTRICTIONS' => get_post_meta ($product_id, BBZ_PM_RESTRICTIONS, true),
				'BBZ_PM_INACTIVE_REASON' => get_post_meta ($product_id, BBZ_PM_INACTIVE_REASON, true),
				'bbz_default_visibility' => bbz_default_visibility ($product_id, $product->get_stock_quantity()),
				'bbz_availability_filter' => bbz_availability_filter( array(), $product, $user_id ),
				'bbz_availability_text' => bbz_availability_text ($product_id, $user_id),
				'bbz_get_availability' => bbz_get_availability ($product_id, $user_id),
				'bbz_is_visible' => bbz_is_visible (false, $product_id, '', $user_id),
				'bbz_add_to_cart_button_text' => bbz_add_to_cart_button_text ("Default", $product, $user_id ),
				'bbz_is_purchasable' => (bbz_is_purchasable( true, $product, $user_id )? 'true': 'false'),
				'bbz_get_backorders' => bbz_get_backorders( 'no', $product, $user_id),
				'bbz_get_stock_status' => bbz_get_stock_status( $product->get_stock_status(), $product, $user_id),

			);
		
			break;
			

		case 'call-function':
			$data = call_user_func ($filterkey,$this->options->get ('filtervalue')) ;
			break;

		
		
		case 'get-shipping-classes':
			$shipping= new WC_shipping();
			$data = $shipping->get_shipping_classes();
			break;
			
		
		case 'get-product-detail':
			$product = wc_get_product ($filterkey);
			$data = call_user_func (array($product, $filtervalue));
			break;
			
		case 'product-filter':
			$args = array();
			$args ['post__in'][] = $filtervalue;
			$data = bbz_wwof_product_filter ($args);
			break;
			
		case 'get-user-meta':
			$user_id = !empty ($filterkey) ? $filterkey : wp_get_current_user()->ID;
			$data = get_user_meta ($user_id, $filtervalue);
			break;
		
		case 'delete-user-meta':
			$user_id = !empty ($filterkey) ? $filterkey : wp_get_current_user()->ID;
			if ($user_id === 'ALL') {
				$data = array();
				$users = get_users();
				foreach ($users as $user) {
					$result = delete_user_meta ($user->ID, $filtervalue);
					if ($result) $data[$user->data->display_name] = $filtervalue.' deleted';
				}
								
			} else {
				
				$data = delete_user_meta ($user_id, $filtervalue);
			}
			break;
		
		case 'get-post-meta':
			$post_id = $filterkey;
			$data = get_post_meta ($post_id, $filtervalue, true);
			break;
			
		case 'show-options':
			$data = $this->options->getall();
			break;
			
		case 'set-option':
			$this->options->update ($filter);
			$data = $this->options->getall();
			break;
			
		case 'delete-option':
			$this->options->delete (explode(',',$filterkey));
			$data = $this->options->getall();
			break;
			
		case 'show-order':
			$order_id = $filterkey;
			$data['order'] = wc_get_order($order_id);// ($order_id);
			$data['items'] = $data['order']->get_items();
			//$data = array ($order_id=>$order->get_data());
			break;
		
		case 'process-orders':
			bbz_process_orders ($resubmit=$filtervalue);
			break;

		case 'add-shipping-addresses':
			if ($filterkey === 'ALL') {
				echo ('All function disabled');
			} else {
				
				$user_id = $filtervalue;
				$usermeta = new bbz_usermeta ($user_id);
				$data['shipto'] = $usermeta->get_woo_address ('shipping');
				$data['shipto']['email'] = "test@unilake.co.uk";					
				$bbz_addresses = new bbz_addresses ($user_id);
				
				$data['address_id'] = $bbz_addresses->get_zoho_address_id ($data['shipto'], 'shipping');
				
				$data['usermeta'] =	get_user_meta ($user_id);
			}		
			break;
		case 'get_cross_sells';
			// get list of product posts
			$args = array (
				'post_type' => 'product',	// only get product posts
				'numberposts' => -1,
				'fields' => 'ids',			// get all of the ids in an array
			);
			$product_posts = get_posts ( $args);
			foreach ( $product_posts as $post_id ) {  // for each woo product
				$product = wc_get_product ($post_id);
				$csids = $product->get_cross_sell_ids();
				if (!empty($csids)) {
					$data[$post_id] = $csids;
				}
			}
			break;
			
		case 'get_shipmentorders':
			$zoho_so = new zoho_shipmentorders;
			if (!empty($filterkey)) {
				$data= $zoho_so->get_shipmentorder_by_id ($filterkey);
			} else{
				$data = $zoho_so->get_shipmentorders_by_status ($filtervalue);
			}
			break;
			
		case 'get_packages':
			$zoho_so = new zoho_shipmentorders;
			if (!empty($filterkey)) {
				$data= $zoho_so->get_package_by_id ($filterkey);
			} else{
				$data = $zoho_so->get_packages_by_status ($filtervalue);
			}
			break;
		
		case 'get_trackship_row':
			$ts = WC_Trackship_Actions::get_instance();
			$data = $ts->get_shipment_rows($filterkey);
			break;
			
		case 'get_sales_history':
			$sh = new bbz_sales_history();
			$data = $sh->load();
			break;
/*				
		case 'update_shipment_test':
			$zoho_so = new zoho_shipmentorders;
			if (!empty($filterkey)) {
				$package= $zoho_so->get_package_by_id ($filterkey);
				if(is_wp_error($package)) break;
				$shipment = $package['shipment_order'];
				$shipment['date'] = $shipment['shipping_date'];
				$shipment['package_id'] = $package['package_id'];
				$shipment['salesorder_id'] = $package['salesorder_id'];
				$shipment['shipment_sub_status'] = $filtervalue;
				$data = $zoho_so->update_shipmentorder ($shipment);
				if(is_wp_error($data) ) {
					$data->add('test-001', 'shipment update error', $package);
				}
			}
			break;
			
		case 'update_shipment_status':
			$zoho_so = new zoho_shipmentorders;
			if (in_array ($filtervalue, array ('shipped', 'delivered') ) ) {
				$data = $zoho_so->update_status ($filterkey, $filtervalue);
			} else {
				$data = $zoho_so->update_status ($filterkey, 'shipped', $filtervalue);
			}
			break;
*/				
		}
		
		
		if (!empty($data)){
			if (is_wp_error ($data) ) {
				$codes = $data->get_error_codes();
				foreach ($codes as $error_code) {
					echo 'Error: '.$error_code.' -> '.$data->get_error_message ($error_code)."\n";
					echo 'Error data: <pre>'.print_r ($data->get_error_data ($error_code), true)."</pre>\n";
				}
			}					
		
			elseif (is_array($data)) {
				echo count ($data) . ' items returned.<br>';
				echo '<pre>'; print_r ($data); echo '</pre>';
			} else {
				echo '<pre>'; var_dump ($data); echo '</pre>';
			}
		} else {
			echo '<br>No data returned';
		}
		$this->options->reload();
		//$this->options->delete ('function', true);

		
		$data= array ('Options'=>$this->options->getall());
		echo '<pre>'; print_r ($data); echo '</pre>';
		
		
	}




}
?>