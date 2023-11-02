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
* Run daily from bbz-functions cron
*
* Arg can either be:
* - empty, defaulting to current user
* - 'all' meaining all users
* - a single user_id
* 
*******/
function bbz_load_sales_history ($arg='') {
	$zoho = new zoho_connector;
	$response = $zoho->get_sales_history(array ('2020','2021','2022', '2023'));
	if (is_wp_error($response)) {
		$response->add('bbz-ut-003', 'bbz_load_sales_history failed');
		return $response;
	} else {
		$user_list = bbz_build_user_list ($arg);
		// Now update user meta with sales history where applicable
		$update_count = 0;
		
		foreach ($user_list as $user_id) {
			$user_meta = new bbz_usermeta ($user_id);
			$zoho_cust_id = $user_meta->get_zoho_id();
			if (!empty ($zoho_cust_id) && isset($response[$zoho_cust_id])) {
				$user_meta->load_sales_history ($response[$zoho_cust_id]);
				$update_count += 1;
			}
		}
		return $update_count;
	}
	return false;
}

// Update payment terms in user meta from zoho contact data
// Run when user enters checkout page

function bbz_update_payment_terms ($user_id='') {

	if (empty($user_id) ) $user_id = get_current_user_id();
	
	if ($user_id !== 0) {
		$user_meta = new bbz_usermeta ($user_id);
		$zoho_cust_id = $user_meta->get_zoho_id();
		if (!empty ($zoho_cust_id)) {
		
			$zoho = new zoho_connector;
			$zoho_contact = $zoho->get_contact_by_id ( $zoho_cust_id);
			if (is_wp_error ($zoho_contact)) {
				$zoho_contact->add ('bbz-ut-004', 'In bbz_update_payment_terms', array (
					'user_id'=>$user_id,
					'zoho_id'=>$zoho_cust_id) );
				return $zoho_contact;
			} else {
				$user_meta->load_payment_terms ($zoho_contact);
				return true;
			}
		}
	}
	return false;

}

function bbz_debug ($data, $title='', $exit=false, $always=false) {
	if ( BBZ_DEBUG || $always == true) {  //always forces output
		$log_file = "./bbz_debug.log";
		if (is_null ($data)) {
			$data = 'NULL';
		} elseif ($data===false) {
			$data = 'FALSE';
		} elseif (empty($data)){
			$data = 'NO DATA';
		}
		if (is_scalar ($data) || is_string($data)) {
			bbz_debug_line ($title.' '. $data, $log_file);
		} else {
			bbz_debug_line ($title, $log_file);
			if (is_wp_error ($data) ) {
				$codes = $data->get_error_codes();
				foreach ($codes as $error_code) {
					bbz_debug_line ('Error: '.$error_code.' -> '.$data->get_error_message ($error_code), $log_file);
					bbz_debug_line ('Error data: <pre>'.print_r ($data->get_error_data ($error_code), true)."</pre>", $log_file);
				}
			} else {
				
				bbz_debug_line ('Data: <pre>'.print_r ($data, true).'<\pre>', $log_file);
			}
		}
		if ($exit) exit;
	}
}
function bbz_debug_line ($line, $log) {
	error_log (date('[Y-m-d H:i:s]').$line."\n", 3, $log);
	echo $line.'<br>';
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

function bbz_email_admin ($subject, $message='') {
	bbz_debug (array ('Subject'=>$subject, 'Message'=>$message), 'Email to Admin');
	if (is_wp_error ($message) ) {
		$data = $message;
		$message = '';
		$codes = $data->get_error_codes();
		foreach ($codes as $error_code) {
			$message .= 'Error: '.$error_code.' -> '.$data->get_error_message ($error_code)."\n";
			$message .= 'Error data: <pre>'.print_r ($data->get_error_data ($error_code), true)."</pre>\n";
		}
	} elseif (empty($message)) {
		$message = $subject;
	} elseif (!is_string($message) ) {
		$message = print_r($message, true);
	}

	$admin_email = get_option ('admin_email');
	wp_mail ($admin_email, $subject, $message);
}
/*******
*	bbz_update_cross_sells 
*
*	Adds reciprocals to cross sells for all products
*	e.g. if product A has products B and C as cross sells, 
*	A is added as a cross sell to B and C
*
*******/
 
function bbz_update_cross_sells () {

		// get list of product posts
		$args = array (
			'post_type' => 'product',	// only get product posts
			'numberposts' => -1,
			'fields' => 'ids',			// get all of the ids in an array
		);
		$product_posts = get_posts ( $args);
		$related = array();
		$cross_sells = array();
		
		// Loop through all products, 
		
		foreach ( $product_posts as $post_id ) {  // for each woo product
			$product = wc_get_product ($post_id);
			$csids = $product->get_cross_sell_ids();
			if (!empty($csids)) {
				$cross_sells[$post_id] = $csids;
				foreach ($csids as $cross_sell_id) {
					$related [$cross_sell_id][] = $post_id;
				}
			}
		}
		
		// Now add related ids to cross sell ids
		
		foreach ($related as $post_id=>$related_ids) {
			foreach ($related_ids as $rid) {
				//check if already in cross sells, and add if not
				if (!isset ($cross_sells[$post_id]) || !in_array ($rid, $cross_sells[$post_id])) {
					$cross_sells[$post_id][] = $rid;
				}
			}
		}
		
		// And finally update the product records
		foreach ($cross_sells as $post_id=>$cross_sell_ids) {
			$product = wc_get_product ($post_id);
			$product->set_cross_sell_ids($cross_sell_ids);
			$product->save();
		}
		return $cross_sells;
	}