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

/*****
* Set up hourly and daily cron jobs
*
******/

if ( ! wp_next_scheduled( 'bbz_hourly_cron' ) ) {
    wp_schedule_event( time(), 'hourly', 'bbz_hourly_cron' );
}
if ( ! wp_next_scheduled( 'bbz_daily_cron' ) ) {
    wp_schedule_event( strtotime ('01:00 tomorrow'), 'daily', 'bbz_daily_cron' );
}

/******
* Order processing
* Called as a cron job to process any new orders to be sent to zoho and check if any are completed
*****/

add_action ('bbz_hourly_cron', 'bbz_process_orders');
function bbz_process_orders ($resubmit=false) {
	$orders = wc_get_orders (array ('status'=>'processing'));
	foreach ($orders as $order) {
		$bbz_order = new bbz_order ($order);
		if ( empty($bbz_order->get_zoho_order_id())) {
			//order not yet submitted to zoho
			$bbz_order->process_new_order($resubmit);
		} else {
			// check if order completed yet
			$bbz_order->update_order_status();
		}
	}
}

/*****
*  Daily database updates
*
*****/
// some error handling to notify admin if it fails would be a good idea...

add_action ('bbz_daily_cron', 'bbz_daily_user_update');
add_action ('bbz_daily_cron', 'bbz_daily_product_update');  // function in bbz_utils
add_action ('bbz_daily_cron', 'bbz_update_cross_sells');  // update reciprocal cross sells (in bbz-utils)

function bbz_daily_user_update () {
	$result = bbz_load_sales_history ('all');
	if (is_wp_error($result)) {
		bbz_email_admin ('Daily User Update failed', $result);
		return;
	}
/*	$result = bbz_update_payment_terms ('all');
	if (is_wp_error($result)) {
		bbz_email_admin ('Daily User Update failed', $result);
		return;
	}
	*/
	bbz_email_admin ('Daily user updates completed');
}

function bbz_daily_product_update () {
	$products = new bbz_products;
	$result = $products->update_all();
	if (is_wp_error($result)) {
		bbz_email_admin ('Daily Product Update failed', $result);
	} else {
		$result['missing-products'] = $products->get_missing_items();
		bbz_email_admin ('Daily product updates completed', $result);
	}
}




?>