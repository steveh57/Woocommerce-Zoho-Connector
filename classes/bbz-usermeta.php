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
	public function update_woo_address ($type, $address) {
		foreach ($address as $key=>$value) {
			if (empty($value)) {
				delete_user_meta ($this->user_id, $key);
			} else {
				update_user_meta( $this->user_id, $key, $value );
			}
		}
	}
	
	// get all bbz addresses in structured array
	public function get_bbz_addresses () {
		return get_user_meta ($this->user_id, BBZ_UM_ADDRESSES, true);
	}
	// update a specific bbz address
	// if bbz_addresses doesn't exist in user meta it is created.
	// returns updated bbz addresses array
	public function update_bbz_address ($type, $key, $address) {
		$bbz_addresses = $this->get_bbz_addresses();
		$bbz_addresses = is_array ($bbz_addresses) ? $bbz_addresses : array();
		$bbz_addresses [$type][$key] = $address;
		update_user_meta ($this->user_id, BBZ_UM_ADDRESSES, $bbz_addresses);
		return $bbz_addresses;
	}
	
	/*****
	* Load payment terms
	*
	* Load payment terms from zoho_contact to usermeta
	*****/
	public function load_payment_terms ($zoho_contact) {
		if ( empty ( $zoho_contact ['payment_terms_label']))  return false;
		$available_credit = 99999;
		if ( !empty ($zoho_contact['credit_limit'])) {
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
		// $bbz-product_index is a global defined in bbz_definitions
		if (empty ($bbz_product_index)) {  // load index from product meta if necessary
			// get list of product posts and build index of zoho_id to post_id
			$args = array (
				'post_type' => 'product',	// only get product posts
				'numberposts' => -1,		// get all of them
			);
			$product_posts = get_posts ( $args);

			foreach ( $product_posts as $post ) {
				$zoho_id = get_post_meta ($post->ID, BBZ_PM_ZOHO_ID, $single=true);
				if (!empty ($zoho_id)) $bbz_product_index [$zoho_id] = $post->ID;
			}
		}
		return $bbz_product_index;
	}

	public function load_sales_history ($sales_history) {
		$product_list = array();
		
		//load product index if not already set
		$product_index = $this->get_product_index();

		// now translate zoho product ids to woo ids
		$product_list = array();
		foreach ($sales_history as $zoho_pid => $sales_data) {
			if (isset ($product_index[$zoho_pid])) {
				$product_list[$product_index[$zoho_pid]] = $sales_data;
			}
		}
		update_user_meta ($this->user_id, BBZ_UM_SALES_HISTORY, $product_list);
		return $product_list;
	}
	
	public function get_sales_history () {
		return get_user_meta ($this->user_id, BBZ_UM_SALES_HISTORY, true);
	}
	
}
?>