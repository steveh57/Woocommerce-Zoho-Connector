<?php
/**
 * User approval functions
 *
 * These functions integrate with the WooCommerce Wholesale Lead Capture plugin
 *
 * @package Custom_Admin_Settings
 */
 
 // If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

include_once ( dirname( __FILE__ ) . '/bbz-definitions.php');
include_once ( dirname( __FILE__ ) . '/bbz-zoho-connector-class.php');

/*****
* bbz_approve_user
*
* Function called from wwlc when a new user completes the registration form.
* Note Wordpress user has not actually been created yet, so can't save any data at this point
* Can't find a way to set the auto approval specifically for this user (user data by reference doesn't work)
* so change the main auto approval setting option prior to the user being created.
****/

add_action ('wwlc_action_before_create_user' , 'bbz_approve_user');

function bbz_approve_user (&$user_data) {
	//  $user_data is the wwlc data returned from reg form
	// get the email address new user has entered on the form
	$newuser_email = trim($user_data['user_email']);
	$approve = false;
	
	// now get a list of valid customer emails from zoho
	$zoho = new zoho_connector;
	$zoho_data = $zoho->get_contact_by_email ($newuser_email);

	if (is_array ($zoho_data) && count ($zoho_data) > 0) {
		// Match found so enable auto approve
		$approve = true;
	}
	// no match found so drop through into manual approval process
	update_option( 'wwlc_general_auto_approve_new_leads', $approve ? 'yes' : 'no' );

}
/*****
*	Disable auto login when auto approved
*
* Want to ensure that email is verified by entering password provided before
* allowing login.
******/

add_filter ('wwlc_login_user_when_auto_approve', 'bbz_auto_login', 9999);

function bbz_auto_login () {
	return false;
}

/******
* bbz_link_user
*
* Called from wwlc once user has been approved.
* Gets user data from zoho and loads it into the user meta record
* if zoho id is specified, use that, otherwise find a zoho user with matching email
*****/

add_action ('wwlc_action_after_approve_user', 'bbz_link_user');

function bbz_link_user ($user, $zoho_id='') {  //$user is wp user object

	$address_map = array(  // map zoho address fields to user metadata fields
		'billing_address'	=> 	array ( // billing address: zoho field => user metadata field
					'firstname'		=>	'billing_first_name',
					'lastname'		=>	'billing_last_name',
					'company'		=>	'billing_company',
					'address1'		=> 	'billing_address_1',
					'address2'		=>	'billing_address_2',
					'city'			=>	'billing_city',
					'postcode'		=>	'billing_postcode',
					'state'			=>	'billing_state',
					'country'		=>	'billing_country',
					'phone'			=>	'billing_phone'
				),
		'shipping_address'	=>	array ( // shipping address: zoho field => user metadata field
					'firstname'		=>	'shipping_first_name',
					'lastname'		=>	'shipping_last_name',
					'company'		=>	'shipping_company',
					'address1'		=> 	'shipping_address_1',
					'address2'		=>	'shipping_address_2',
					'city'			=>	'shipping_city',
					'postcode'		=>	'shipping_postcode',
					'state'			=>	'shipping_state',
					'country'		=>	'shipping_country',
					'phone'			=>	'shipping_phone'
				)
		);
	// reset auto approve
	update_option( 'wwlc_general_auto_approve_new_leads', 'no' );
		
	// first get zohocontact details by email address
	$zoho = new zoho_connector;
	if (empty ($zoho_id) ) {
		$zoho_contact = $zoho->get_contact_by_email ($user->data->user_email);
	} else {
		$zoho_contact = $zoho->get_contact_by_id ( $zoho_id);
	}
	$user_id = $user->ID;

	if (is_array ($zoho_contact) ) {
	// Match found
		if (empty ($zoho_id)) update_user_meta( $user_id, BBZ_UM_ZOHO_ID, $zoho_contact ['zoho_id'] );

		foreach ($address_map as $key => $field_map) {
			$zoho_address = $zoho_contact [$key];
			$zoho_address['company'] = $zoho_contact ['company'];
			foreach ($field_map as $zoho_field => $usermeta_field) {
				if ( empty ( $zoho_address[$zoho_field])) {
					// no data for this field from zoho
					switch ($zoho_field) {
						// special handling for first and last name for billing and shipping
						// zoho just has one field 'attention' so,
						// if set, we enter first 'Attention:, last zoho attention field
						// if empty we use the user's first and last name
						case 'firstname':
							update_user_meta( $user_id, $usermeta_field, 
								empty ($zoho_address['attention']) ? $user->first_name : 'Attention:');
							break;
						case 'lastname';
							update_user_meta( $user_id, $usermeta_field, 
								empty ($zoho_address['attention']) ? $user->last_name :  $zoho_address['attention'] );
							break;
						case 'phone':  // if shipping or billing phone blank, use main contact phone number
							update_user_meta( $user_id, $usermeta_field, $zoho_contact['phone']);
							break;
						default:
							delete_user_meta( $user_id, $usermeta_field );
					}
				} else {
					update_user_meta( $user_id, $usermeta_field, $zoho_address[$zoho_field] );
				}
			}
		}
		
		if (! empty ( $zoho_contact ['payment_terms_name'])) {
			$terms ['name'] = $zoho_contact ['payment_terms_name'];
			$terms ['days'] = $zoho_contact ['payment_terms_days'];
			update_user_meta( $user_id, BBZ_UM_PAYMENT_TERMS, $terms );
		};
		// if zoho payment terms are < 30 days then don't allow account payment
		//if (! empty ( $zoho_contact ['payment_terms_days']) && $zoho_contact ['payment_terms_days'] < 30 )  {
		//	update_user_meta( $user_id, 'wwlc_custom_set_role', 'wholesale_prepay' );
		//};

		
		// Now load sales history from zoho
		bbz_load_sales_history ($user);
		
		return true;
	}
	return false;

}
?>