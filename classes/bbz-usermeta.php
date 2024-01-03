<?php
/**
 * Zoho Usermeta Class
 *
 * Manages the bbz user meta data saved in user meta data
 *
 */
 // If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

 class bbz_usermeta {
	private $user_id;  // value will be false if guest
	
	function __construct($arg_user_id='') {
		$this->user_id = empty ($arg_user_id) ? get_current_user_id () : $arg_user_id;
	}

/*****
* load_zoho_contact
*
* Load data from zoho contact record into user meta address fields
*****/
	/****
	* load_zoho_id
	*
	* parameter is either the id string or an array (e.g. contact) containing 'zoho_id'
	*****/

	public function load_zoho_id ($zoho_contact) {
		if (is_array ($zoho_contact) && isset ($zoho_contact ['contact_id'])) {
			$zoho_id = $zoho_contact ['contact_id'];
		} elseif (is_string ($zoho_contact)) {
			$zoho_id = $zoho_contact;
		} else {
			return false;
		}
		update_user_meta( $this->user_id, BBZ_UM_ZOHO_ID, $zoho_id );
		return $zoho_id;

	}
	
	public function get_user_id () {
		return $this->user_id;
	}
	
	public function get_zoho_id () {
		 return get_user_meta ($this->user_id, BBZ_UM_ZOHO_ID, true);
	}
	
	// update the default woo billing or shipping address
	// $type shipping or billing
	// $address array of field names and values, field names without shipping/billing prefix
	public function update_woo_address ($type, $address) {
		foreach ($address as $key=>$value) {
			if (empty($value)) {
				delete_user_meta ($this->user_id, $type.'_'.$key);
			} else {
				update_user_meta( $this->user_id, $type.'_'.$key, $value );
			}
		}
	}
	// get woo billing or shipping address from usermeta
	public function get_woo_address ($type) {
		$woo_address = array();
		foreach (bbz_addresses::get_woo_address_fields($type) as $woo_field_name=>$user_meta_key) {
			// returns array of woo_field_name=>user_meta_key
			// e.g. 'city'=>'shipping_city'
			$woo_address[$woo_field_name] = get_user_meta ($this->user_id, $user_meta_key, true);
		}
		return $woo_address;
	}
			
		
	
	// get zoho address id for billing or shipping address
	public function get_zoho_address_id ($type) {
		return get_user_meta ($this->user_id, $type.'_zoho_id', true);
	}
	
	// update zoho address id for billing or shipping address
	public function update_zoho_address_id ($type, $address_id) {
		return update_user_meta ($this->user_id, $type.'_zoho_id', $address_id);
	}
	
	// get shipping postcode for user
	public function get_shipping_code () {
		return get_user_meta ($this->user_id, 'shipping_postcode', true);
	}

	
	/*****
	* Load payment terms
	*
	* Load payment terms from zoho_contact to usermeta
	* Notes
	* - zoho credit limit zero => no credit limit set
	* - zoho payment terms field uses negative numbers to indicate special terms:
	*		0 = due on receipt
	*		-2 = due end of the month
	*		-3 = paymnet due end of next month
	
	*****/
	public function load_payment_terms ($zoho_contact) {
		if ( empty ( $zoho_contact ['payment_terms_label']))  return false;
		$available_credit = 99999;
		if ( !empty ($zoho_contact['credit_limit']) && ($zoho_contact['credit_limit'] > 0) ) {
			$available_credit = $zoho_contact['credit_limit'];
			if ( !empty ($zoho_contact ['outstanding_receivable_amount'])) {
				$available_credit -= $zoho_contact ['outstanding_receivable_amount'];
			}
		}

		$terms ['name'] = $zoho_contact ['payment_terms_label'];
		$terms ['days'] = $zoho_contact ['payment_terms'];
		$terms ['available_credit'] = $available_credit;
		update_user_meta( $this->user_id, BBZ_UM_PAYMENT_TERMS, $terms );
		return $terms;
	}
	
	public function get_payment_terms () {
		return get_user_meta ($this->user_id, BBZ_UM_PAYMENT_TERMS, true);
	}
	
	/*****
	* Sales History
	*
	* Load from zoho data
	* sales history input is an array of zoho item id and sales volume pairs
	*****/

	private function get_product_index () {
		// wordpress will cache this so it doesn't keep calling the database
		global $wpdb;
		$sql = "SELECT meta_value, post_id FROM {$wpdb->prefix}postmeta WHERE meta_key = '".BBZ_PM_ZOHO_ID."';";
		$product_index =  $wpdb->get_results ($sql, OBJECT_K);
		//bbz_debug ( $product_index, 'zoho_product_index', true);
		
		return $product_index;
	}

	public function load_sales_history ($sales_history) {
		if (!empty($sales_history)) {
			update_user_meta ($this->user_id, BBZ_UM_SALES_HISTORY, $sales_history);
		}
	}
	
	public function load_previous_products ($previous_products) {
		if (!empty($previous_products)) {
			update_user_meta ($this->user_id, BBZ_UM_PREVIOUS_PRODUCTS, implode (',', $previous_products));
		}
	}
	
	public function clear_sales_history () {
		delete_user_meta ($this->user_id, BBZ_UM_SALES_HISTORY);
		update_user_meta ($this->user_id, BBZ_UM_PREVIOUS_PRODUCTS, '1'); // if left blank, query returns all products
	}

	
	public function get_sales_history ($product_id) {
		$sales_history =  get_user_meta ($this->user_id, BBZ_UM_SALES_HISTORY, true);
		if (isset($sales_history[$product_id])) {
			return $sales_history[$product_id];
		} else return false;
	}
	
}
