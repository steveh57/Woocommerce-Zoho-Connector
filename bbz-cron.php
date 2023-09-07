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
if (BBZ_RUNCRONS) {  // Cron jobs only run on live site (see bbz-definitions)
	if ( ! wp_next_scheduled( 'bbz_hourly_cron' ) ) {
		wp_schedule_event( time(), 'hourly', 'bbz_hourly_cron' );
	}
	if ( ! wp_next_scheduled( 'bbz_daily_cron' ) ) {
		wp_schedule_event( strtotime ('01:00 tomorrow'), 'daily', 'bbz_daily_cron' );
	}
}

/******
* Order processing
* Called as a cron job to process any new orders to be sent to zoho and check if any are completed
* Note wc_get_orders defaults to a limit set by 'posts per page' setting - usually 10.  Could by 
* overridden by adding 'limit'=>xx to arg array - xx=-1 for unlimited.
* Also because we select by status, and status may be changed by the order processing function,
* the paging can return some odd results, missing out some orders as it runs.  However this will get
* resolved over time with multiple runs.
*
*****/

add_action ('bbz_hourly_cron', 'bbz_process_orders');
add_action ('bbz_process_next_page', 'bbz_process_orders', 10, 2);

define ('SEVEN_DAYS', 7*24*3600);
define ('NINETY_DAYS', 90*24*3600);
define ('ONE_YEAR', 365*24*3600);

function bbz_process_orders ($resubmit=false, $page=1) {
	bbz_debug ($page, 'Processing page #');
	$limit = 10;
	$results = wc_get_orders (array (
		'status'=>array('processing', 'completed', 'partial-shipped'),	// completed used for status shipped
		'type'=>'shop_order',
		'date_created'=> '>'.(time()-NINETY_DAYS), // ignore anything older than 90 days (90x24x3600)
		//'paginate' => true,
		'limit' => $limit,
		'paged' => $page));
	//bbz_debug ($results, 'Orders to process', false);
	
	$order_ids = array();
	foreach ($results as $order) $order_ids[] = $order->get_id();
	
	foreach ($order_ids as $order_id) {
		$bbz_order = new bbz_order ($order_id);
		bbz_debug($order_id, 'Processing order #');
		$response = true;
		if ( $bbz_order->is_on_zoho() ) {
			// check if order completed yet
			$response = $bbz_order->update_order_status();
		} else {
			//order not yet submitted to zoho
//			if ($bbz_order->get_date_created()->getTimestamp() > time() - SEVEN_DAYS) {
				// don't process any old orders, avoid weird results!
				$response = $bbz_order->process_new_order($resubmit);
//			}
		}
		if (is_wp_error ($response) ) {
			$response->add ('bbz-cron-001', 'bbz_process_orders failed for order', $order_id);
			bbz_email_admin ("Failed to process order", $response);
			//continue processing remaining orders
		}
	}
	if (count($order_ids) == $limit) {
	//if we got a full page of orders, resubmit order processing to continue
		wp_schedule_single_event (time() + 60, 'bbz_process_next_page', array (false, $page+1));
	}
}

add_action ('bbz_hourly_cron', 'bbz_hourly_product_update');
function bbz_hourly_product_update () {
	$products = new bbz_products;
	$result = $products->update_all();
	// Don't bother to report errors on hourly update, too many emails
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
/*	This resulted in too many calls to zoho.  Now done immediately before checkout page
	$result = bbz_update_payment_terms ('all');
	if (is_wp_error($result)) {
		bbz_email_admin ('Daily User Update failed', $result);
		return;
	}
	*/
	//bbz_email_admin ('Daily user updates completed');
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