<?php
/**
 * Order handling
 * Called when order is complete to post order in Zoho
 */
 // If this file is called directly, abort.
 
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

add_action( 'woocommerce_payment_complete', 'bbz_order_processing');

function bbz_order_processing( $order_id ){	


	$order = new bbz_order;
	$order->process_new_order ($order_id);
	
}
