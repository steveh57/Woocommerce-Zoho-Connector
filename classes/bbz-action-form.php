<?php
/**
 * Action functions for bb-zoho-connector
 *
 * 
 */
 
 // If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class bbz_action_form extends bbz_admin_form {

	private $actionform	= array (
			'name'		=>	'bbzform-action',
			'class'		=>	'bbz_action_form',
			'title'		=>	'<h2>Update from Zoho</h2>',
			'text_before'	=> '<p>Choose an option and click the Execute button to import data and carry out the updates.<br>'.
				'Note that Update Products fetches product data from Zoho and uses it to overrides various product settings in '.
				'woocommerce with data from Zoho:<br>'.
				'* - Price<br>'.
				'* - Sale Price (if ORP set in Zoho)<br>'.
				'* - Wholesale price<br>'.
				'* - Tax Class<br>'.
				'* - Tax Status<br>'.
				'* - Shipping class (if set in Zoho)<br>'.
				'* - Stock level<br>'.
				'* - Backorder setting<br>'.
				'* - Catalog visibility<br>'.
				'.* - Wholesale visibility</p>',
			'fields'	=>	array (
				'function'	=> array (
					'type'		=> 'select',
					'title'		=> 'Action',
					'options'	=> array (
						'update-products'	=>	'Update Products',
						'update-users'	=>	'Update user info and sales history',
						'update-addresses' => 'Update all user addresses from zoho',
						'check-products'	=>	'Check for missing products',
					)
				),
				'state'			=> array(    // Status hidden
					'type'          => 'hidden',
					'title'         => 'State',
					'value'			=>	'bbzaction'
				)
			),
			'button'		=> array (
				'name'			=> 'submit',
				'type'			=> 'primary',
				'title'			=> 'Execute'
			)
		);



	function __construct () {
		$this->form = $this->actionform;
	}
	
	public function action ($options) {
		switch ($options['function']) {
		case 'update-products':
			$result= $this->update_products();
			if (! $result) {
				$this->set_admin_notice ($options, 'Product update failed', 'error');
			} else {
				$this->set_admin_notice ($options, $result.' Products Updated', 'success');
			}
			break;
		case 'check-products':
			break;  // this is dealt with in display_data
		
		case 'update-users':
			$result= bbz_load_sales_history('all');  //update sales history  all linked users
			if ($result) $result = bbz_update_payment_terms('all');
			if (! $result) {
				$this->set_admin_notice ($options, 'Sales history load failed', 'error');
			} else {
				$this->set_admin_notice ($options, $result.' users updated', 'success');
			}
			break;
			
		case 'update-addresses':
			$result= $this->update_addresses('all',true);  //update sales history and payment terms for all linked users
			if (! $result) {
				$this->set_admin_notice ($options, 'Update addresses failed', 'error');
			} else {
				$this->set_admin_notice ($options, $result.' user addresses updated', 'success');
			}
			break;	
		}
		
		
		update_option(OPTION_NAME, $options);

	}

	public function display_data ($options) {
		if (!isset($options['function'])) return false;
		switch ($options['function']) {
			case 'check-products':
			case 'update-products':
				$data = $this->get_missing_items();
				if (is_array($data)) {
					echo '<h2>Missing Products</h2>';
					echo 'The following items are not present on the website:<br>';
					echo '<pre>'; print_r ($data); echo '</pre>';
				}
				break;
		}
		unset ($options['function']);
		update_option(OPTION_NAME, $options);
	}
	/*****
	* GET MISSING ITEMS
	*
	* get list of items not present in woocommerce
	* returns array isbn => name
	*****/
	private function get_missing_items() {
	
		//get list of zoho items indexed by sku
		$zoho = new zoho_connector;
		$items = $zoho->get_items();
		if (is_array($items)) {
			// get list of product posts
			$args = array (
				'post_type' => 'product',	// only get product posts
				'numberposts' => -1,		// get all of them
			);
			$products = get_posts ( $args);
			$index = array();
			//build index of sku => product_id
			foreach ( $products as $product ) {
				$sku = get_post_meta ($product->ID, '_sku', $single=true);	// sku is in meta data
				if ( !('' == $sku) ) {
					$index [$sku] = $product->ID;
				}
			}
			// if item sku from zoho is not in product list
			foreach ($items as $sku=>$item) {
				if (!isset ($index[$sku]) && $item['status']=='active') {
					$results [$sku] = $item['name'];
				}
			}
			return $results;
		} else {
			return false;
		}
	}
	/*******
	*
	* Update products from Zoho
	*
	* Fetches product data from Zoho and uses it to update product pricing and availability,
	* tax class and shipping class if specified in zoho.
	*
	* Note that this overrides various product settings in woocommerce with data from Zoho
	* - Price
	* - Sale Price (if ORP set in Zoho)
	* - Wholesale price
	* - Tax Class
	* - Tax Status
	* - Shipping class (if set in Zoho)
	* - Stock level
	* - Backorder setting
	* - Catalog visibility
	* - Wholesale visibility
	* 
	*******/
	private function update_products () {
		$tax_class_map = array (
			'Standard Rate'	=> '',
			'Reduced Rate'	=> 'reduced-rate',
			'Zero Rate'		=> 'zero-rate'
		);

		// Buiid shipping map name=>id
		$shipping= new WC_shipping();
		$shipping_map = array();
		foreach ($shipping->get_shipping_classes() as $shipping_class) {
			$shipping_map [$shipping_class->name] = $shipping_class->term_id;
		}
		
		// get zoho item data
		$zoho = new zoho_connector;
		$items = $zoho->get_items();
		if (is_array($items)) {
		
			// get list of product posts
			$args = array (
				'post_type' => 'product',	// only get product posts
				'numberposts' => -1,		// get all of them
			);
			$product_posts = get_posts ( $args);
			
			$update_count = 0;
			foreach ( $product_posts as $post ) {  // for each woo product
				$product = wc_get_product ($post);
				$sku = $product->get_sku();
				if ( !empty($sku) && isset ($items[$sku]) ) {	// have we got zoho data for this sku?
					$item = $items[$sku];
					//$items [ $sku ]['pid'] = $product->ID;

					$post_id = $post->ID;
					update_post_meta ($post_id, 'wholesale_customer_wholesale_price', $item['wsp']);
					update_post_meta ($post_id, 'wholesale_customer_have_wholesale_price', 'yes');
					update_post_meta ($post_id, BBZ_PM_ZOHO_ID, $item['zoho_id']);

					$product->set_price ($item['rrp']);  //set active price
					if (!empty ($item['orp']) && $item['orp'] >= $item['rrp']) { 
						// if an original price is set, (and greater than RRP) set RRP as sale price
						$product->set_regular_price ($item['orp']);
						$product->set_sale_price ($item['rrp']);
					} else {
						$product->set_regular_price ($item['rrp']);
					}
					if (!empty ($item['tax_class']) && isset ($tax_class_map[$item['tax_class']]) ) {
						$product->set_tax_class ($tax_class_map[$item['tax_class']]);
						$product->set_tax_status ('taxable');
					}
					if (!empty ($item['shipping_class']) && isset ($shipping_map[$item['shipping_class']]) ) {
						$product->set_shipping_class_id ($shipping_map[$item['shipping_class']]);
					}
					if ( $item['status'] == 'inactive' && !empty ($item ['inactive_reason'] )) {
						update_post_meta ($post_id, BBZ_PM_INACTIVE_REASON, $item['inactive_reason']);
					}

				}
				if ( !empty($sku) && isset ($items[$sku]) && $item['status'] == 'active') {
					// product is available and can be backordered
					$product->set_stock ($item['stock']);
					$product->set_backorders ('notify');
					$product->set_catalog_visibility ('visible'); 
					// but if stock level is zero or wholesale only, restrict to wholesale customers
					if ($item['stock'] == 0 || $item['wholesale_only'] == 'Yes') {
						update_post_meta ($post_id, 'wwpp_product_wholesale_visibility_filter', 'wholesale_customer');
					} else {
						update_post_meta ($post_id, 'wwpp_product_wholesale_visibility_filter', 'all');
					}
				} else {  //product is inactive or not listed on zoho (not available)
					$product->set_stock (0);
					$product->set_backorders ('no');
					$product->set_catalog_visibility ('search');  //only visible in searches to wholesale customers
					update_post_meta ($post_id, 'wwpp_product_wholesale_visibility_filter', 'wholesale_customer');

				}
				$product->save();
				$update_count += 1; 
			}	
			wc_update_product_lookup_tables();  // update cache
			wc_delete_product_transients();
			return $update_count;
//			echo '<pre>'; print_r ($items); echo '</pre>';
		} else {
			return false;
		}
	}
	
	// update all users with a zoho id with addresses from zoho.
	// this is really only to initialize the bbz_addresses array
	
	private function update_addresses () {
		// if user not specified get list of all users
		$users =  get_users() ;
		$zoho = new zoho_connector;
		$update_count = 0;
		if (!empty ($users)) {
			foreach ($users as $user) {
				$user_meta = new bbz_usermeta ($user->ID);
				$zoho_cust_id = $user_meta->get_zoho_id();
				if (!empty ($zoho_cust_id) ) {
					$zoho_contact = $zoho->get_contact_by_id($zoho_cust_id);
					if (is_array ($zoho_contact)) {
						$bbz_addresses = new bbz_addresses ($user->ID);
						$bbz_addresses->load_from_zoho_contact ($zoho_contact);
						$update_count += 1;
					}
				}
			}
		}
		return $update_count;
	}

}
?>