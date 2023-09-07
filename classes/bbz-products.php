<?php
/******
 * bbz_products Class
 * container for product update functions
 *
 ******/
 
// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class bbz_products {
	private $tax_class_map = array (
		'Standard Rate'	=> '',
		'Reduced Rate'	=> 'reduced-rate',
		'Zero Rate'		=> 'zero-rate',
		'Outside Scope'	=> 'outside-scope',
	);
	
	private $shipping_map = array();
	
	private $items, $product_posts;

	
	
	function __construct() {
		// get zoho item data
		$zoho = new zoho_connector;
		$result = $zoho->get_items();
		if (is_wp_error ($result)) {
			$result->add ('bbz-prod-00', 'get_items failed in bbz_products construct' );
			return $result;
		} else $this->items = $result;
		
		// get list of product posts
		$args = array (
			'post_type' => 'product',	// only get product posts
			'numberposts' => -1,		// get all of them
		);
		$this->product_posts = get_posts ( $args);

		// Buiid shipping map name=>id
		$shipping= new WC_shipping();
		foreach ($shipping->get_shipping_classes() as $shipping_class) {
			$this->shipping_map [$shipping_class->name] = $shipping_class->term_id;
		}
	}
	/*******
	*
	* Update products from Zoho
	*
	* Fetches product data from Zoho and uses it to update product pricing and availability,
	* tax class and shipping class if specified in zoho.
	*
	* Note that this overrides various product settings in woocommerce with data from Zoho
	* - Price
	* - Sale Price (if ORP set in Zoho)
	* - Wholesale price
	* - Tax Class
	* - Tax Status
	* - Shipping class (if set in Zoho)
	* - Stock level
	* - Backorder setting
	* - Catalog visibility
	* - Wholesale visibility
	* 
	*******/
	public function update_all () {
		$update_count = 0;
		foreach ( $this->product_posts as $post ) {  // for each woo product
			$product = wc_get_product ($post);
			if ($product->is_type( 'variable' )) {
				$children = $product->get_children();
				foreach ($children as $key => $child_id) 
				{ 
					$product = wc_get_product ($child_id);
					$result = $this->update_single ($product);
					if (!empty ($result)) $warnings[$child_id] = $result;
					$update_count += 1; 
				}
			} else {
				$result = $this->update_single ($product);
				if (!empty ($result)) $warnings[$product->get_id()] = $result;
				$update_count += 1; 
			}
		}
		wc_update_product_lookup_tables();  // update cache
		wc_delete_product_transients();
		$warnings['update-count']=$update_count;
		return $warnings;
	}

	function update_single ($product) {
		
		$warnings = array();
		$post_id = $product->get_id();
		$sku = $product->get_sku();
		if ( !empty($sku) && isset ($this->items[$sku]) ) {	// have we got zoho data for this sku?
			$item = $this->items[$sku];
			$availability = empty ($item['availability']) ? 'available' : str_replace(' ', '-', strtolower ($item['availability']));
			update_post_meta ($post_id, BBZ_PM_INACTIVE_REASON, $item['availability']);
			update_post_meta ($post_id, BBZ_PM_AVAILABILITY, $availability); //cleaned up version - lowercase with hyphens

			//$items [ $sku ]['pid'] = $product->ID;
			if ($item['status'] == 'active') {

				update_post_meta ($post_id, 'wholesale_customer_wholesale_price', $item['wsp']);
				update_post_meta ($post_id, 'wholesale_customer_have_wholesale_price', 'yes');
				update_post_meta ($post_id, BBZ_PM_ZOHO_ID, $item['zoho_id']);
				if ($item['wholesale_only'] === 'Yes'  ) {
					update_post_meta ($post_id, 'wwpp_product_wholesale_visibility_filter', 'wholesale_customer');
				} else {
					update_post_meta ($post_id, 'wwpp_product_wholesale_visibility_filter', 'all');
				}			

				$product->set_price ($item['rrp']);  //set active price
				if (!empty ($item['orp']) && $item['orp'] > $item['rrp']) { 
					// if an original price is set, (and greater than RRP) set RRP as sale price
					$product->set_regular_price ($item['orp']);
					$product->set_sale_price ($item['rrp']);
					
				} else {
					$product->set_regular_price ($item['rrp']);
					$product->set_sale_price ('');  //make sure sale price is cleared
				}
				// Load discount string in post meta
				update_post_meta ($post_id, BBZ_PM_WHOLESALE_DISCOUNT, number_format(100*(1-($item['wsp']/$item['rrp'])),1).'%');
				
				if (!empty ($item['tax_class']) && isset ($this->tax_class_map[$item['tax_class']]) ) {
					$product->set_tax_class ($this->tax_class_map[$item['tax_class']]);
					$product->set_tax_status ('taxable');
				} else $warnings [] = 'missing-tax-class';
				

				if ($item['product_type'] !== 'goods' ) {
					// services always in stock
					$product->set_manage_stock (false) ;  // disable stock management
					$product->set_stock_status ('instock');
					
				} else {	
				
					// active products of type 'goods'				
					if (!empty ($item['shipping_class']) && isset ($this->shipping_map[$item['shipping_class']]) ) {
						$product->set_shipping_class_id ($this->shipping_map[$item['shipping_class']]);
					} else $warnings [] = 'missing-shipping-class';
					
					// load dimension and weight data
				
					if ( !empty ($item['dimension_unit']) && $item['dimension_unit'] == 'cm'
						&& !empty($item['length']) && !empty($item['width'])) {
						$product->set_length($item['length']);
						$product->set_width($item['width']);
						if (!empty($item['height'])) $product->set_height($item['height']);
						//create dimension string for display in product data
						update_post_meta ($post_id, BBZ_PM_DIMENSION_STRING, $item['length'].'cm x '.$item['width'].'cm');
					} else $warnings[] = 'missing-dimensions';

					if (!empty ($item['weight_unit']) &&  !empty($item['weight'])) { //could be g or kg
						if ($item['weight_unit'] == 'g') $product->set_weight ($item['weight']/1000);
						elseif ($item['weight_unit'] == 'kg') $product->set_weight ($item['weight']);
					} else $warnings [] = 'missing-weight';
					
					
					// Are backorders allowed (wholesale only)?
					if ( in_array ($availability, BBZ_AVAIL_SOON)) {
					// Only allow backorders for availability types that will be available soon
						$product->set_backorders ('notify');
					} else {
						$product->set_backorders ('no');
					}
					
					// Deal with stock and availability
					$product->set_manage_stock (true) ;  // Ensure stock management enabled
					$product->set_stock_quantity ($item['stock']);
					if ($product->get_low_stock_amount() == 0) {
						$product->set_low_stock_amount(3);  //set warning level to 3 if not set
					}

					if ( $item['stock'] <= 0) {		// Do we have physical stock?
						// No stock available
					
						if ('pre-order' == $availability ) {
							// set pre-order items to be in stock
							//$product->set_manage_stock (false) ;  // disable stock management
							//$product->set_stock_status ('outofstock');
							$product->set_backorders ('yes');
						} else {
							// Restrict out of stock items to wholesale
							update_post_meta ($post_id, 'wwpp_product_wholesale_visibility_filter', 'wholesale_customer');
			
							$product->set_catalog_visibility ('visible'); 
						}
					}
				}
			
			} else {  //product is inactive on zoho (not available)
				$product->set_stock_quantity (0);
				$product->set_backorders ('no');
				$product->set_catalog_visibility ('search');  //only visible in searches to wholesale customers
				
				update_post_meta ($post_id, 'wwpp_product_wholesale_visibility_filter', 'wholesale_customer');

			} 

		} else {  //product is not listed on zoho (not available)
				$product->set_stock_quantity (0);
				$product->set_backorders ('no');
				$product->set_catalog_visibility ('search');  //only visible in searches to wholesale customers
				update_post_meta ($post_id, 'wwpp_product_wholesale_visibility_filter', 'wholesale_customer');
				$warnings [] = 'no-zoho-item-data';
		}
		
		$product->save();
		if (!empty ($warnings)) {
			$warnings [] = "Title: " . $product->get_title();
			$warnings [] = "SKU: $sku";
		return $warnings;
		}
	}
	/*****
	* GET MISSING ITEMS
	*
	* get list of items not present in woocommerce
	* returns array isbn => name
	*****/
	public function get_missing_items() {
	
		if (is_array($this->items)) {
			$index = array();
			//build index of sku => product_id
			foreach ( $this->product_posts as $post ) {
				$sku = get_post_meta ($post->ID, '_sku', $single=true);	// sku is in meta data
				if ( !('' == $sku) ) {
					$index [$sku] = $post->ID;
				}
			}
			// if item sku from zoho is not in product list
			foreach ($this->items as $sku=>$item) {
				if (!isset ($index[$sku]) && $item['status']=='active'  && $item['availability'] !== 'Special Order') {
					$results [$sku] = $item['name'];
				}
			}
			return $results;
		} else {
			return false;
		}
	}	
	
}
?>