<?php
/**
 * Zoho Sales History Class Class
 *
 * Manages the bbz sales history data - NOT FULLY IMPLEMENTED - WORK IN PROGRESS
 *
 */
 // If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

 class bbz_sales_history {
 
	private $user_id;  // value will be false if guest
	private $table_name = $wpdb->prefix . "bbz_saleshistory";
	
	function __construct($arg_user_id='') {
		$this->user_id = empty ($arg_user_id) ? get_current_user_id () : $arg_user_id;
	}
	
/******
* install
*
* Create database if not already set up - see https://codex.wordpress.org/Creating_Tables_with_Plugins
******/
// Need to set up to call this if database table does not exist or needs structure update - or could create manually!

	public function install() {
		global $wpdb;
		
		

		$charset_collate = $wpdb->get_charset_collate();
		$table_name = $this->table_name;
		$sql = "CREATE TABLE $table_name (
		  id mediumint(9) NOT NULL AUTO_INCREMENT,
		  userid bigint(20),
		  productid bigint(20),
		  year varchar(4),
		  quantity int(11),
		  PRIMARY KEY  (id)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );

	
	
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
?>