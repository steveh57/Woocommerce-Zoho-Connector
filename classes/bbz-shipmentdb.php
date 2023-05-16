<?php
/**
 * Shipment order database access
 *
 * 
 ****/ 
 
 // If this file is called directly, abort.
 if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

define ('BBZ_SHIPMENTDB_NAME', 'bbz_shipmentorders');
define ('BBZ_SHIPMENTDB_VERSION', '0.1');

class bbz_shipmentdb {

	private $table_name, $db, $fieldlist;

	function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . BBZ_SHIPMENTDB_NAME;
		$this->db = $wpdb;
		$this->fieldlist = array (
			'shipment_id', 'shipment_number', 'salesorder_id', 'salesorder_number', 'associated_packages', 
			'customer_name', 'status', 'tracking_number', 'date', 'carrier', 'trackinfo', 'last_event', 'last_event_time' );
	}

	public function install() {  //not tested

		$charset_collate = $this->db->get_charset_collate();

		$sql = "CREATE TABLE $this->table_name (".
		//	id mediumint(9) NOT NULL AUTO_INCREMENT,
		//	time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		//	name tinytext NOT NULL,
		//	text text NOT NULL,
		//	url varchar(55) DEFAULT '' NOT NULL,
		//	PRIMARY KEY  (id)
		") $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		add_option( 'bbz_db_version', BBZ_SHIPMENTDB_VERSION );
	}

	public function insert($data) {
	
		$result = $this->db->insert( $this->table_name, $data);
		//echo 'Result '.$result.'<br>';
		
		if (empty($result) || $result==0 ) {
			$result = new WP_Error ('bbz-sdb-001', 'DB Insert failed', array(
				'table_name'=>$this->table_name,
				'data'=>$data
				));
		}
		return $result;  //count of rows added or wp_error
	}
	
	public function replace($data) {
	
		$result = $this->db->replace ( $this->table_name, $data);
		//echo 'Result '.$result.'<br>';
		
		if (empty($result) || $result==0 ) {
			$result = new WP_Error ('bbz-sdb-002', 'DB Replace failed', array(
				'table_name'=>$this->table_name,
				'data'=>$data
				));
		}
		return $result;  //count of rows added or wp_error
	}
	
	public function getall_ids () {
		$query = "SELECT shipment_id FROM $this->table_name";
		
		return $this->db->get_col($query);
		
	}
	
	// process a zoho formatted shipment record and add to the database
	
	public function insert_zoho_shipment ($shipment) {
	
		$data = array();
		
		foreach ($this->fieldlist as $fieldname) {
			if (isset($shipment[$fieldname])) $data[$fieldname] = $shipment[$fieldname];
		}
		return $this->insert ($data);
	}
		
	public function get_active_shipments () {
		$query = "SELECT * FROM $this->table_name WHERE status <> 'delivered'";
		
		return $this->db->get_results($query);
	}
	
	public function replace_shipment ($shipment) {
		
		$data = array();
		
		foreach ($this->fieldlist as $fieldname) {
			if (isset($shipment[$fieldname])) $data[$fieldname] = $shipment[$fieldname];
		}
		return $this->replace ($data);
	}
	
		
		

 
 
 }
 ?>
 