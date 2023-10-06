<?php
/* 	bbz-shipments
*	
*	Update zoho shipments with delivery status
*/

 // If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class bbz_shipments {
	private $supported_providers = array();
	private $order_id;
	private $carrier_map = array (  // Carrier name used by tracking => array of partial names that may be used in zoho
				'Royal Mail' =>  array ('RM', 'Royal mail'),
				'DX Delivery' => array ('DX', 'dx', 'Dx'),
				);
	private $tracked_items = array();

	function __construct ($order_id) {
		$this->order_id = $order_id;
		// If AST PRO plugin is Installed, build array of supported carriers
		if ( class_exists( 'WC_Advanced_Shipment_Tracking_Actions' ) ) {
			$ast = WC_Advanced_Shipment_Tracking_Actions::get_instance();
			if (method_exists ($ast, 'get_providers')) {
				foreach ($ast->get_providers() as $slug=>$provider) {
					$this->supported_providers [$provider['provider_name']] = $slug;
				}
			}
			// get list of items already tracked in woo for this order
			if (method_exists ($ast, 'get_tracking_items')) {
				$this->tracked_items = $ast->get_tracking_items ($this->order_id);
			}
		}
	}
	
	function load_packages ($zoho_order) {
		$shipments = array();
		// collect shipment data from salesorder
		foreach ($zoho_order ['packages'] as $package) {
			// this is a new shipment record
			$carrier = $package ['carrier'];
			// clean up carrier field
			foreach ($this->carrier_map as $clean_carrier=>$alias_list) {
				foreach ($alias_list as $alias) {
					if (str_contains ($carrier, $alias)) {
						$carrier = $clean_carrier;
						break 2;
					}
				}
			}
			// clean up tracking number field
			$tracking_number = str_replace (array (' ', '-'), '', $package ['tracking_number']);
			
			//  add shipment to array for local storage
			$shipments [$package['shipment_number']] = array (
				'package_id' => $package ['package_id'],
				'package_number' => $package ['package_number'],
				'shipment_id' => $package['shipment_id'],
				'shipment_date' => $package ['shipment_date'],
				'carrier' => $carrier,
				'service' => $package ['service'],
				'tracking_number' => $tracking_number,
				'shipment_date' => $package ['shipment_date'],
				);
		}

		// If AST PRO plugin is Installed
		if ( !empty ($shipments) && class_exists( 'WC_Advanced_Shipment_Tracking_Actions' )) {
			foreach ($shipments as $key => $shipment){
				// Check if this shipment has already been loaded: match on carrier and tracking number
				$tracking_exists = false;
				//bbz_debug (array($shipment, $this->supported_providers));
				if (empty ($shipment ['carrier_slug'])  && !empty($this->supported_providers[$shipment['carrier']])) {
					$shipment['carrier_slug'] = $this->supported_providers[$shipment['carrier']];
					$shipments [$key] = $shipment; // update source array
				}
				foreach ($this->tracked_items as $tracking) {
					if ($tracking['tracking_provider'] == $shipment ['carrier_slug']
						&& $tracking['tracking_number'] == $shipment ['tracking_number']) {
						$tracking_exists = true;
						break;
					}
				}
				
				// if this is new, create a new tracking record

				if (false === $tracking_exists 
						&& function_exists( 'ast_insert_tracking_number')  
						&& !empty($shipment['carrier_slug']) ) {
					//bbz_debug('Calling insert tracking');
					ast_insert_tracking_number( 
						$this->order_id, 
						$shipment ['tracking_number'],
						$shipment ['carrier'], 
						$shipment ['shipment_date'],
						in_array($zoho_order['shipped_status'], array('shipped', 'fulfilled')) ? 1 : 2); //status 1 = shipped, 2 = partial
				}
			}
		}
				
	}


}
?>	