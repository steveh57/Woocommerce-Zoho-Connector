<?php
/*****
 * Wholesale Order Form Filter
 *
 * These functions allow the wholesale order form filter to be modified 
 * 
 * If the wholesale order form is called with product code 9999
 * we replace it with selected product codes
 *   category 'My Products' ...
 * 
 *****/
 
// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

	
/*****
* bbz_wwof_product_filter
* 
* Filter post processes the product list used in the wholesale order form.
* If product code '9999' is used in the list it is removed and the users
* products from the sales history are loaded instead.
*	9991	Products on sales - uses wc_get_product_ids_on_sale
*	9992	Featured products - wc_get_featured_product_ids
*	9999	User's previous purchases
****/


function bbz_wwof_product_filter ($product_args) {

	if (is_array ($product_args['post__in'])) {
/*
		$key = array_search ('9999', $product_args['post__in'],true);
		if (!$key==false) {  // found special code
			$product_list = $product_args['post__in'];
			unset ($product_list[$key]); // remove special code
*/
		$wwof_product_list = $product_args['post__in'];
		$new_product_list = array();
		if (in_array ('9999', $wwof_product_list)) {			
			
			// now get sales history from user meta
			$user_meta = new bbz_usermeta;
			$sales_history = $user_meta->get_sales_history();
			if (is_array($sales_history) ) {
				foreach ($sales_history as $post_id=>$sales_array) {
					$new_product_list[] = $post_id;
				}
			}
			
		}
		if (in_array ('9991', $wwof_product_list)) {	
			$new_product_list = array_merge ($product_list, wc_get_product_ids_on_sale());
		}
		if (in_array ('9992', $wwof_product_list)) {	
			$new_product_list = array_merge ($product_list, wc_get_featured_product_ids());
		}

		if (!empty($new_product_list)) $product_args['post__in'] = $new_product_list;

	}
	return $product_args;
}

add_filter ( 'wwof_product_args' , 'bbz_wwof_product_filter' , 10 , 2  );
		
?>