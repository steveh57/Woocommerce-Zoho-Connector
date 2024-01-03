<?php
/**
 * Zoho Sales History Class
 *
 * Used to manage the loading of sales history data from Zoho into user meta data
 *
 */
 // If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

 class bbz_sales_history {
 
	private $sales_history;
	
	// product_index provides mapping from zoho product ids to woo product ids
	// the zoho id is stored in product meta data for each product
	
	private $product_index;
	
	private function load_product_index () {
		if ( empty($this->product_index)) {
			global $wpdb;
			$sql = "SELECT meta_value, post_id FROM {$wpdb->prefix}postmeta WHERE meta_key = '".BBZ_PM_ZOHO_ID."';";
			$this->product_index =  $wpdb->get_results ($sql, OBJECT_K);
			//bbz_debug ( $this->product_index, 'zoho_product_index', true);
		}
	}

	
	function __construct() {
		$this->sales_history = array();
	}
	
	public function load (){
		$zoho = new zoho_connector;
		$response = $zoho->get_sales_history();
		if (is_wp_error($response)) {
			$response->add('bbz-sh-001', 'bbz_load_sales_history failed');
			return $response;
		} else {
			$this->load_product_index();
			
			// response is an array of rows of data from the Analytics query
			foreach ($response as $row) {
				if (!empty ($row['Customer ID']) && !empty ($row['Item ID']) && is_numeric ($row['Customer ID'])){
					$cust_id = $row['Customer ID'];
					if (isset ($this->product_index[$row['Item ID']])) {
						$product_id = $this->product_index[$row['Item ID']]->post_id;
						$postcode = $row['Shipping Code'];
						$item_years = array();
						$item_total = 0;
						foreach ($row as $year=>$value) {
							if (is_numeric($year) && !empty ($row[$year])) { // if the key is numeric, assume it's a year
								$item_years[$year] = 0 + $row[$year];
								$item_total += $item_years[$year];
							}
						}
						// only save the entry if totals are non-zero
						if ($item_total > 0) $this->sales_history[$cust_id][$postcode][$product_id] = $item_years;
					}
				}
			}
			return $this->sales_history;
		}
	}
	
	public function load_meta($user_id) {
		$user_meta = new bbz_usermeta ($user_id);
		$zoho_cust_id = $user_meta->get_zoho_id();
		$postcode = $user_meta->get_shipping_code();
		
		if (!empty ($zoho_cust_id) && isset($this->sales_history[$zoho_cust_id]) && isset($this->sales_history[$zoho_cust_id][$postcode])) {
			$user_meta->load_sales_history ($this->sales_history[$zoho_cust_id][$postcode]);
		
		
			// now load previous products - a simple array of product ids previously purchased, used for selection query
			$previous_products = array(); 
			foreach ($this->sales_history[$zoho_cust_id][$postcode] as $product_id => $sales_data) {
				if (!empty($sales_data)) {
					$previous_products[] = $product_id;
				}
			}
			$user_meta->load_previous_products ($previous_products); //converts array to csv string on load
		} else {
			$user_meta->clear_sales_history();
		}
	}
	
	// shortcode handler to output sales history text for current product
	
	public static function shortcode ($atts) {
		global $product;
		$result = '';
		
		if (!empty($product)) {
			$usermeta = new bbz_usermeta();
			$sales_history = $usermeta->get_sales_history($product->get_id());
			if (!empty($sales_history) ) {
				
				foreach ($sales_history as $year=>$qty) {
					if ($qty > 0) {
						$result .= "<tr><td>$year</td><td>$qty</td></tr>";
					}
				}
			}
			if (!empty($result)) {
				$result = "<table><tr><td>Year</td><td>Purchased</td></tr>$result</table>";
			}
		};
		return $result;
	}
}