<?php
// This file includes utility funnctions for bbz

 // If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}
/****
* bbz_link_user
*
* Used to link a woo user to a zoho customer.  The zoho_id is loaded into user meta, 
* along with payment terms and addresses.
*
* $user_id	if specified this is the user to be linked.
*			if blank, defaults to current user
* $zoho_id	optional - if unknown we look up the zoho id from the email address
*
*****/

function bbz_link_user ($user_id='', $zoho_id='') {  //$user is wp user object

	if ( empty( $user_id )) $user_id = get_current_user_id();  // default to current user
		
	// first get zohocontact details by email address
	$zoho = new zoho_connector;
	if (empty ($zoho_id) ) {
		$userdata = get_userdata ($user_id);
		$zoho_contact = $zoho->get_contact_by_email ($userdata->user_email);
	} else {
		$zoho_contact = $zoho->get_contact_by_id ( $zoho_id);
	}
	
	if (is_wp_error ($zoho_contact) ){
		$zoho_contact->add ('bbz-ut-001', 'In bbz_link_user', array (
			'user_id'=>$user_id,
			'$zoho_id'=>$zoho_id) );
		return $zoho_contact;
	} else {
	// Match found
		$user_meta = new bbz_usermeta ($user_id);
		
		$result = $user_meta->load_zoho_id ($zoho_contact);
		
		$bbz_addresses = new bbz_addresses ($user_id);
		if (!is_wp_error($result)) $result = $bbz_addresses->load_from_zoho_contact ( $zoho_contact);
		if (!is_wp_error($result)) $result = $user_meta->load_payment_terms ( $zoho_contact);
		// Now load sales history from zoho
		if (!is_wp_error($result)) $result = bbz_load_sales_history ($user_id);
		if (is_wp_error ($result)) {
			$result->add ('bbz-ut-002', 'In bbz_link_user', array (
				'user_id'=>$user_id,
				'$zoho_id'=>$zoho_id) );
		}

		return $result;
	}
	return false;

}

 
/*******
*
* Load sales history from Zoho
*
* Fetches sales history data from Zoho and loads it in user_meta for each linked user.
*
* Arg can either be:
* - empty, defaulting to current user
* - 'all' meaining all users
* - a single user_id
* 
*******/
function bbz_load_sales_history ($arg='') {
	$zoho = new zoho_connector;
	$sales_history = $zoho->get_sales_history();
	if (is_array($sales_history)) {
		$user_list = bbz_build_user_list ($arg);
		// Now update user meta with sales history where applicable
		$update_count = 0;
		
		foreach ($user_list as $user_id) {
			$user_meta = new bbz_usermeta ($user_id);
			$zoho_cust_id = $user_meta->get_zoho_id();
			if (!empty ($zoho_cust_id) && isset($sales_history[$zoho_cust_id])) {
				$user_meta->load_sales_history ($sales_history[$zoho_cust_id]);
				$update_count += 1;
			}
		}
		return $update_count;
	}
	return false;
}

// Update payment terms in user meta from zoho contact data
function bbz_update_payment_terms ( $arg='') {
	$user_list = bbz_build_user_list ($arg);
	$update_count = 0;
	$zoho = new zoho_connector;

	foreach ($user_list as $user_id) {

		$user_meta = new bbz_usermeta ($user_id);
		$zoho_cust_id = $user_meta->get_zoho_id();
				
		if (!empty ($zoho_cust_id)) {
			$zoho_contact = $zoho->get_contact_by_id ( $zoho_cust_id);
			if (! empty ( $zoho_contact )) {
				$user_meta->load_payment_terms ($zoho_contact);
				$update_count += 1;
			}
		}
	}
	return $update_count;
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
function bbz_update_products () {
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
			$post_id = $post->ID;
			if ( !empty($sku) && isset ($items[$sku]) ) {	// have we got zoho data for this sku?
				$item = $items[$sku];
				//$items [ $sku ]['pid'] = $product->ID;

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
				$product->set_manage_stock (true) ;  // Ensure stock management enabled
				if ($product->get_low_stock_amount() == 0) {
					$product->set_low_stock_amount(3);  //set warning level to 3 if not set
				}
				if (!empty ($item ['availability'] )) update_post_meta ($post_id, BBZ_PM_INACTIVE_REASON, $item['availability']);
				if ($item['status'] == 'active') {
					$product->set_stock_quantity ($item['stock']);
					
					// Restrict out of stock items to wholesale, unless available to pre-order
					if ($item['wholesale_only'] === 'Yes' || ($item['stock'] <= 0 && !in_array ($item ['availability'], BBZ_AVAIL_PRE ) )) {
						update_post_meta ($post_id, 'wwpp_product_wholesale_visibility_filter', 'wholesale_customer');
					} else {
						update_post_meta ($post_id, 'wwpp_product_wholesale_visibility_filter', 'all');
					}			
					// Only allow backorders for temp unavailable or pre order items that are out of stock	
					// if availability is blank, assume out of stock is temporary and allow backorders
					if ( empty ($item ['availability']) || in_array ($item ['availability'], BBZ_AVAIL_TEMP)) {
						$product->set_backorders ('notify');
					} else {
						$product->set_backorders ('no');
					}
					$product->set_catalog_visibility ('visible'); 
					

				} else {  //product is inactive on zoho (not available)
					$product->set_stock_quantity (0);
					$product->set_backorders ('no');
					$product->set_catalog_visibility ('search');  //only visible in searches to wholesale customers
					
					update_post_meta ($post_id, 'wwpp_product_wholesale_visibility_filter', 'wholesale_customer');

				} 
			} else {  //product is not listed on zoho (not available)
					$product->set_stock_quantity (0);
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


function bbz_debug ($data, $title='', $exit=true) {
	if ( BBZ_DEBUG ) {
		if (is_null ($data)) $data = 'NULL';
		if ($data===false) $data = 'FALSE';
		if (empty($data)) $data = 'NO DATA';
		echo '<br>', $title, '<pre>';
		print_r ($data);
		echo '</pre>';
		if ($exit) exit;
	}
}
function bbz_build_user_list ($arg) {
	$user_list = array();
	// if user not specified get list of all users
	if (empty($arg) ) {
		$user_list[] = get_current_user_id();
	} elseif ($arg === 'all') {
		$users = get_users();
		foreach ($users as $user) {
			$user_list[] = $user->ID;
		}
	} else {
		$user_list[] = $arg;
	}
	return $user_list;
}

function bbz_is_wholesale_customer ($user_id = '') {
	$user = false;
	if (!empty($user_id)) {
		$user = get_user_by ('id', $user_id);
	} elseif ( is_user_logged_in()) {
		$user = wp_get_current_user();
	}
	
	if (is_object ($user) ) {
		foreach ($user->roles as $role) {
			if ('wholesale_customer' == $role) return true;
		}
	}
	return false;
}

function bbz_email_admin ($subject, $message) {
	bbz_debug (array ('Subject'=>$subject, 'Message'=>$message), 'Email to Admin');
	$admin_email = get_option ('admin_email');
	wp_mail ($admin_email, $subject, $message);

}

?>