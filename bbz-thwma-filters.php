<?php
/**
 * Multiple Address handling functions
 *
 * These functions integrate with the Themehigh WooCommerce Multiple Address (thwma) plugin
 *
 */
 
 // If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}
/*****
* There's no suitable hooks in thwma so we hook into update_user_metadata
* and pick up any changes to the saved addresses
*
******/

add_filter ('update_user_metadata', 'bbz_thwma_address_filter', 10, 4);

function bbz_thwma_address_filter ($check, $user_id, $meta_key, $thwma_addresses) {
	if (! ($meta_key === "thwma_custom_address")) return;
	
	$bbz_addresses = new bbz_addresses ($user_id);
	$bbz_addresses->update_from_thwma ($thwma_addresses);
	// now let thwma carry on...
}

?>