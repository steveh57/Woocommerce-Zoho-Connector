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
*
*****/

add_action ('bbz_hourly_cron', 'bbz_process_orders');
add_action ('bbz_process_next_orders', 'bbz_process_orders', 10, 3);

define ('SEVEN_DAYS', 7*24*3600);
define ('NINETY_DAYS', 90*24*3600);
define ('ONE_YEAR', 365*24*3600);
define ('ZOHO_CALLSPERPAGE', 10);

/******
* bbz_process_orders
*
* Called as a cron job to process open woo orders.
* For new orders, an order is created in Zoho.  If there is already a Zoho order, then zoho is called to
* see if there is any update to process (i.e. if it has been shipped).
* This may be called without parameters to start the process, then recalled via the next_page cron to continue
* processing after a short break.  This is so as not to hit the zoho api calls limit.
*
* Parameters:
* $resubmit	Used to force creation of a zoho order even if we already have an id. Can be called from bbz Actions menu
*			and is useful to fix cases where orders haven't been created correctly.
* $order_ids	List of order ids created on the initial call
*****/

function bbz_process_orders ($resubmit=false, $order_ids=array()) {
	if (empty($order_ids)) {
	// build list of active woo orders
		$results = wc_get_orders (array (
			'status'=>array('processing', 'completed', 'partial-shipped'),	// 'completed' is used for status shipped
			'type'=>'shop_order',
			'date_created'=> '>'.(time()-NINETY_DAYS), // ignore anything older than 90 days
			//'paginate' => true,
			'limit' => -1, //$limit,
			//'paged' => $page
			));
		//bbz_debug ($results, 'Orders to process', false);
		foreach ($results as $order) $order_ids[] = $order->get_id();
	}
	
	// now process each order in turn.  If it's not on Zoho, we create the order.
	// If it is on Zoho, we check if it's shipped to update the status.
	$limit = min( ZOHO_CALLSPERPAGE, count ($order_ids));
	for ($i = 0; $i < $limit; $i++) {
		if (!empty($order_ids[$i])) {
			$order_id = $order_ids[$i];
			$bbz_order = new bbz_order ($order_id);
			bbz_debug($order_id, 'Processing order #');
			$response = true;
			if ( $bbz_order->is_on_zoho() ) {
				// check if order completed yet
				$response = $bbz_order->update_order_status();
			} else {
				//order not yet submitted to zoho
				//if ($bbz_order->get_date_created()->getTimestamp() > time() - SEVEN_DAYS) {// don't process any old orders, avoid weird results!
					$response = $bbz_order->process_new_order($resubmit);
			}
		}
		if (is_wp_error ($response) ) {
			$response->add ('bbz-cron-001', 'bbz_process_orders failed for order', $order_id);
			bbz_email_admin ("Failed to process order", $response);
			//continue processing remaining orders
		}
	}
	$order_ids = array_slice ($order_ids, $i);
	if (!empty ($order_ids)) {
	//if we haven't processed all the orders, resubmit order processing to continue
		wp_schedule_single_event (time() + 60, 'bbz_process_next_orders', array (false, $order_ids));
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


/******
* bbz_get_zoho_orders
*
* Fetches orders from zoho that were created from outside of woo and creates a dummy woo order
* to allow tracking.
******/

add_action ('bbz_hourly_cron', 'bbz_get_zoho_orders');
add_action ('bbz_process_next_zoho_orders', 'bbz_get_zoho_orders', 10, 2);


function bbz_get_zoho_orders ($zoho_order_list = array()) {

	$zoho = new zoho_connector();
	
	// build the list of orders on first call
	if (empty ($zoho_order_list) ) {
		$zoho_order_list=array();
		// First get a list of shipped zoho salesorders
		$filter = array (
//			'shipped_status' => array ('shipped', 'partially_shipped'),  // zoho can also return blank shipped_status
			'status' => 'open'	// avoids drafts
		);
		$response = $zoho->get_salesorders ($filter);
		if (is_wp_error ($response)) {
			$response->add('bbz-cron-002', 'Zoho get_salesorders failed', array(
				'filter' => $filter));
			bbz_email_admin ('Cron job error in bbz_get_zoho_orders', $response);
			return $response;
		}
		$salesorders = $response;
		
		//get list of zoho orders already linked from woo
		global $wpdb;
		$wpdb->flush();  // make sure we run the query afresh as we might have added more order_ids
		$existing_zoho_orders =  $wpdb->get_col ("SELECT `meta_value` FROM `{$wpdb->prefix}postmeta` WHERE `meta_key` = 'zoho_order_id';");

		foreach ($salesorders as $zoho_order) {
			$zoho_id = strval($zoho_order['salesorder_id']);
			// add to the list if zoho order is NOT in woo
			if (!in_array($zoho_id, $existing_zoho_orders)) $zoho_order_list[] = $zoho_id;
		}
	}
	// we should now have a complete list of the orders we have to process 
	
	$limit = min( ZOHO_CALLSPERPAGE, count ($zoho_order_list));
	for ($i = 0; $i < $limit; $i++) {
	
	// loop through zoho sales orders (paged to limit number of zoho calls per batch)
	//for ($i = ($page-1)*(int)$limit; $i < $page*(int)$limit; $i++) {
		if (isset ($zoho_order_list[$i])) {
			$zoho_order_id = $zoho_order_list[$i];
			
			
			// zoho order doesn't exist in woo - create dummy order to track shipment.
			// get full salesorder details from zoho
			$response = $zoho->get_salesorder($zoho_order_id);
			if (is_wp_error ($response)) {
				$response->add('bbz-zsp-002', 'Zoho get_salesorder failed in update_shipments', array(
					'zoho_order_id' => $zoho_order_id,
					));
				return $response;
			}
			$zoho_order = $response;  // this is the full order with package details
			
			// now create woo order
			$bbz_order = new bbz_order_from_zoho ($zoho_order);
			
			// get order shipping details
			$response = true;
			$response = $bbz_order->update_order_status();

			bbz_debug ( array ('zoho_order'=>$zoho_order['salesorder_number'], 'woo_order'=>$bbz_order->get_woo_order_id()), 'Order created');

		}
	}
	// remove the orders we've processed
	$zoho_order_list = array_slice ($zoho_order_list, $i);

	//if we haven't processed all the orders, resubmit order processing to continue
	if (!empty($zoho_order_list)) {
		wp_schedule_single_event (time() + 60, 'bbz_process_next_zoho_orders', array ($zoho_order_list));
	}

}




?>