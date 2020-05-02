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
	$zoho_id = $zoho->get_id_from_email ($newuser_email);

	if (!$zoho_id === false) {
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

add_action ('wwlc_action_after_approve_user', 'bbz_after_approve_user');
function bbz_after_approve_user ($user) { //$user is wp user object

	// reset auto approve
	update_option( 'wwlc_general_auto_approve_new_leads', 'no' );
	
	return bbz_link_user ($user->ID);
}

?>