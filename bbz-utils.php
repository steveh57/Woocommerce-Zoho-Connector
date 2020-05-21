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

	if (is_array ($zoho_contact) ) {
	// Match found
		$user_meta = new bbz_usermeta ($user_id);
		
		$result = $user_meta->load_zoho_id ($zoho_contact);
		
		$bbz_addresses = new bbz_addresses ($user_id);
		if ($result) $result = $bbz_addresses->load_from_zoho_contact ( $zoho_contact);
		if ($result) $result = $user_meta->load_payment_terms ( $zoho_contact);
		// Now load sales history from zoho
		if ($result) $result = bbz_load_sales_history ($user_id);

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


function bbz_debug ($data, $title='', $exit=true) {
	if (is_null ($data)) $data = 'NULL';
	if ($data===false) $data = 'FALSE';
	if (empty($data)) $data = 'NO DATA';
	echo '<br>', $title, '<pre>';
	print_r ($data);
	echo '</pre>';
	if ($exit) exit;
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
	if (empty($user_id) && is_user_logged_in()) {
		$user = wp_get_current_user();
	} else {
		$user = get_user_by ('id', $user_id);
	}
	if (is_object ($user) ) {
		foreach ($user->roles as $role) {
			if ('wholesale_customer' == $role) return true;
		}
	}
	return false;
}

function bbz_email_admin ($subject, $message) {
	//bbz_debug (array ('Subject'=>$subject, 'Message'=>$message), 'Email to Admin');
	$admin_email = get_option ('admin_email');
	wp_mail ($admin_email, $subject, $message);

}

?>