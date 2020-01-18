<?php
/**
 * Creates the submenu page for the plugin.
 *
 * @package Custom_Admin_Settings
 */
 
 // If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

include_once ( dirname( __FILE__ ) . '/bbz-definitions.php');
include_once ( dirname( __FILE__ ) . '/bbz-zoho-connector-class.php');

	/*******
	*
	* Update products from Zoho
	*
	* Fetches product data from Zoho and uses it to update product pricing and availability.
	* 
	*******/
	function bbz_update_products () {
		$tax_class_map = array (
			'Standard Rate'	=> '',
			'Reduced Rate'	=> 'reduced-rate',
			'Zero Rate'		=> 'zero-rate'
		);

		// Buiid shipping map name=>id
		$shipping= new WC_shipping();
		$shipping_map = array();
		foreach ($shipping->get_shipping_classes() as $shipping_class) {
			$shipping_map [$shipping_class->name] = $shipping_class->term_id;
		}

		$zoho = new zoho_connector;
		$items = $zoho->get_items();
		if (is_array($items)) {
			// get list of product posts
			$args = array (
				'post_type' => 'product',	// only get product posts
				'numberposts' => -1,		// get all of them
			);
			$product_posts = get_posts ( $args);
			
			$update_count = 0;
			foreach ( $product_posts as $post ) {
				$product = wc_get_product ($post);
				$sku = $product->get_sku();//get_post_meta ($product->ID, '_sku', $single=true);	// sku is in meta data
				if ( '' !== $sku && isset ($items[$sku]) ) {	// have we got zoho data for this sku?
					$item = $items[$sku];
					//$items [ $sku ]['pid'] = $product->ID;

					$post_id = $post->ID;
					update_post_meta ($post_id, 'wholesale_customer_wholesale_price', $item['wsp']);
					update_post_meta ($post_id, 'wholesale_customer_have_wholesale_price', 'yes');
					update_post_meta ($post_id, BBZ_PM_ZOHO_ID, $item['zoho_id']);

					$product->set_price ($item['rrp']);  //set active price
					if (!empty ($item['orp']) && $item['orp'] >= $item['rrp']) { 
						// if an original price is set, (and greater than RRP) set RRP as sale price
						$product->set_regular_price ($item['orp']);
						$product->set_sale_price ($item['rrp']);
					} else {
						$product->set_regular_price ($item['rrp']);
					}
					if (!empty ($item['tax_class']) && isset ($tax_class_map[$item['tax_class']]) ) {
						$product->set_tax_class ($tax_class_map[$item['tax_class']]);
						$product->set_tax_status ('taxable');
					}
					if (!empty ($item['shipping_class']) && isset ($shipping_map[$item['shipping_class']]) ) {
						$product->set_shipping_class_id ($shipping_map[$item['shipping_class']]);
					}
					if ($item['status'] == 'active') {
						$product->set_stock ($item['stock']);
						$product->set_backorders ('notify');
						$product->set_catalog_visibility ('visible'); 
					} else {  //product is inactive (not available)
						$product->set_stock (0);
						$product->set_backorders ('no');
						$product->set_catalog_visibility ('search');  //only visible in searches
					}
					$product->save();
			
					$update_count += 1;
				}
			}	
			wc_update_product_lookup_tables();  // update cache
			wc_delete_product_transients();
			return $update_count;
//			echo '<pre>'; print_r ($items); echo '</pre>';
		} else {
			return false;
		}
	}




?>