
// block paypal payment for wholsale user
// not using the option built into wholesale pricing as this overrides the decision on whether to allow
<?php
/* 	bbz-cron
*	This file includes hourly and daily cron jobs
*	To ensure these run, a cron job needs to be set up on the server.
*	In cpanel add the command wget https://bitternbooks.co.uk/wp-cron.php to run at a few minutes past the hour.
*/


 // If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// payment on account.
//add_filter( 'woocommerce_available_payment_gateways' , 'bbz_filter_available_payment_gateways' , 100 , 1 );
function bbz_filter_available_payment_gateways( $available_gateways ) {

	if ( current_user_can( 'manage_options' ) || !bbz_is_wholesale_customer() )
		return $available_gateways;
	
	// for wholesale customer, block paypal as an option
	$filtered_gateways  = array();
	foreach ( $available_gateways as $gateway )
		if ( !strstr($gateway->get_title(), 'paypal') ) 
			$filtered_gateways[ $gateway->id ] = $gateway;

	if ( !empty( $filtered_gateways ) ) {

		WC()->payment_gateways()->set_current_gateway( $filtered_gateways );
		
		return $filtered_gateways;

	} else
		return $available_gateways;
}



function bbz_get_payment_terms ($user_id) {
	$user_meta = new bbz_usermeta ($user_id);
	$zoho_cust_id = $user_meta->get_zoho_id();
	if (!empty ($zoho_cust_id)) {
		$zoho = new zoho_connector;
		$zoho_contact = $zoho->get_contact_by_id ( $zoho_cust_id);
		if (is_wp_error ($zoho_contact)) {
			$zoho_contact->add ('bbz-ut-006', 'In bbz_get_payment_terms', array (
				'user_id'=>$user_id,
				'zoho_id'=>$zoho_cust_id,
				'Updates completed' => $update_count) );
			return $zoho_contact;
		} else {
			if ( empty ($zoho_contact ['payment_terms_label']))  return false;
			$available_credit = 99999;
			if ( !empty ($zoho_contact['credit_limit']) && ($zoho_contact['credit_limit'] > 0) ) {
				$available_credit = $zoho_contact['credit_limit'];
				if ( !empty ($zoho_contact ['outstanding_receivable_amount'])) {
					$available_credit -= $zoho_contact ['outstanding_receivable_amount'];
				}
			}
		}
		$terms ['name'] = $zoho_contact ['payment_terms_label'];
		$terms ['days'] = $zoho_contact ['payment_terms'];
		$terms ['available_credit'] = $available_credit;
		return $terms;
	}
}

// Info message before payment options
// insert a message about credit availability
add_action ('woocommerce_review_order_before_payment', 'bbz_action_before_payment');
function bbz_action_before_payment () {
	if (bbz_is_wholesale_customer()) {
		$user_meta = new bbz_usermeta ();
		$terms = $user_meta->get_payment_terms ();
		//$terms=bbz_update_payment_terms(get_current_user_id());
		if (!empty ($terms ['name'] ) && isset($terms['available_credit']) && $terms['available_credit'] > 0 ) {
			echo 'Payment on account is available.  Terms: ', $terms['name'];
			if (isset($terms['available_credit']) && $terms['available_credit'] > 0) {
				echo 'Available credit: ', $terms['available_credit'];
			}
		} else {
			echo 'Sorry, payment on account is not available.  Please contact Bittern Books to arrange credit facilities.';
			echo 'Terms: ', $terms['name'];
			if (isset($terms['available_credit']) && $terms['available_credit'] > 0) {
				echo 'Available credit: ', $terms['available_credit'];
			}
		}
	}
}

?>