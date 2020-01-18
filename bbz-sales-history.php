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

include_once ( dirname( __FILE__ ) . '/bbz-definitions.php');

 
 	/*******
	*
	* Load sales history from Zoho
	*
	* Fetches sales history data from Zoho and loads it in user_meta for each linked user.
	* 
	*******/
	function bbz_load_sales_history () {
		$zoho = new zoho_connector;
		$sales_history = $zoho->get_sales_history();
		if (is_array($sales_history)) {
			// get list of product posts and build index of zoho_id to post_id
			$args = array (
				'post_type' => 'product',	// only get product posts
				'numberposts' => -1,		// get all of them
			);
			$product_posts = get_posts ( $args);
			$product_index = array ();
			foreach ( $product_posts as $post ) {
				$post_id = $post->ID;
				$zoho_id = get_post_meta ($post_id, 'zoho_item_id', $single=true);
				if (!empty ($zoho_id)) $product_index [$zoho_id] = $post_id;
			}
			// get list of users and build index of zoho id to user id
			$users = get_users();
			$user_index = array();
			foreach ($users as $user) {
				$user_id = $user->ID;
				$zoho_id = get_user_meta ($user_id, 'zoho_contact_id', $single=true);
				if (!empty ($zoho_id)) $user_index [$zoho_id] = $user_id;
			}
		
/*			echo 'Product Index: <pre>'; print_r ($product_index); echo '</pre>';
			echo 'User Index: <pre>'; print_r ($user_index); echo '</pre>';
			echo 'Sales History: <pre>'; print_r ($sales_history); echo '</pre>';
//			exit();
*/
			// Now update user meta with sales history where applicable
			$update_count = 0;
			foreach ($user_index as $zoho_cust_id => $user_id) {
				if (isset($sales_history[$zoho_cust_id])) {
					$product_list = array();
					foreach ($sales_history[$zoho_cust_id] as $zoho_pid => $sales_data) {
						if (isset ($product_index[$zoho_pid])) {
							$product_list[$product_index[$zoho_pid]] = $sales_data;
						}
					}
//debug					echo 'Product list for user'.$user_id.': <pre>'; print_r ($product_index); echo '</pre>';

					update_user_meta ($user_id, BBZ_UM_SALES_HISTORY, $product_list);
					$update_count += 1;
					
				}
			}
//debug			exit();
			return $update_count;
		}
		return false;
	}
	
/*****
* bbz_wwof_product_filter
* 
* Filter post processes the product list used in the wholesale order form.
* If product code '9999' is used in the list it is removed and the users
* products from the sales history are loaded instead.
****/


function bbz_wwof_product_filter ($product_args) {

	if (is_array ($product_args['post__in'])) {
/*
		$key = array_search ('9999', $product_args['post__in'],true);
		if (!$key==false) {  // found special code
			$product_list = $product_args['post__in'];
			unset ($product_list[$key]); // remove special code
*/
		if (in_array ('9999', $product_args['post__in'])) {
			$product_list = $product_args['post__in'];
			
			// now get sales history from user meta
			$user_id = get_current_user_id();
			$sales_history = get_user_meta ($user_id, BBZ_UM_SALES_HISTORY, $single=true);
			if (is_array($sales_history) ) {
				foreach ($sales_history as $post_id=>$sales_array) {
					$product_list[] = $post_id;
				}
			} else $product_list= array ();
			$product_args['post__in'] = $product_list;
		}
	}
	return $product_args;
}

add_filter ( 'wwof_product_args' , 'bbz_wwof_product_filter' , 10 , 2  );
		
?>