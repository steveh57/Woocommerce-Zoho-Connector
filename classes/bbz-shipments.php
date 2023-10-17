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
	/****
	* load_packages
	*
	* Get package details from Zoho order and load to tracking module
	******/
	
	public function load_packages ($zoho_order) {
		$shipments = array();
		$shipments ['items_shipped'] = 0;
		// summary sales order has total 'quantity_shipped' but detaile SO has it for each line item
		foreach ($zoho_order['line_items'] as $line_item) {
			$shipments ['items_shipped'] += $line_item['quantity_shipped'];
		}
		
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
			$shipments ['packages'][] = array(
				'shipment_number' => $package['shipment_number'], // textual shipment number in zoho
				'package_id' => $package ['package_id'],		// internal zoho package id
				'package_number' => $package ['package_number'],// textual package number in zoho
				'shipment_id' => $package['shipment_id'],		// internal zoho shipment id
				'shipment_date' => $package ['shipment_date'],
				'carrier' => $carrier,
				'service' => $package ['service'],
				'tracking_number' => $tracking_number,
				'zoho_status' => $package['shipment_status']
				);
		}
		
		// If AST PRO plugin is Installed
		if ( !empty ($shipments ['packages']) && class_exists( 'WC_Advanced_Shipment_Tracking_Actions' )) {
			foreach ($shipments ['packages'] as $key => $shipment){
				// Check if this shipment has already been loaded: match on carrier and tracking number
				$tracking_exists = false;
				//bbz_debug (array($shipment, $this->supported_providers));
				if (empty ($shipment ['carrier_slug'])  && !empty($this->supported_providers[$shipment['carrier']])) {
					$shipment['carrier_slug'] = $this->supported_providers[$shipment['carrier']];
					$shipments ['packages'][$key] = $shipment; // update source array
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
		
		// save the shipments data in post meta
		if (!empty($shipments)) update_post_meta ($this->order_id, BBZ_PM_SHIPMENTS, $shipments);
		return $shipments;


	}
	
	/********
	* get_tracking
	*
	* Returns the tracking record for the current order
	******/
	
	public function get_tracking () {
		if ( class_exists( 'WC_Trackship_Actions' ) ) {
			$ts = WC_Trackship_Actions::get_instance();
			if (method_exists ($ts, 'get_shipment_rows')) {
				return $ts->get_shipment_rows($this->order_id);
			}
		}
		return false;
	}
	
	/*******
	* update_zoho_tracking
	*
	* @param qty_shipped Quantity of items shipped from zoho
	*
	* Updates zoho when a shipment is delivered.  Qty shipped figure is used to test whether we
	* need to check for new packages.
	******/
	
	public function update_zoho_tracking ($qty_shipped) {
	
		$trackings = $this->get_tracking();  // get tracking records
		//bbz_debug ($trackings, 'Trackings for order '.$this->order_id,false,true);
		if (!empty ($trackings)) {
			$shipments = get_post_meta ($this->order_id, BBZ_PM_SHIPMENTS, true);
			//bbz_debug ($shipments, 'Shipments records',false,true);
	
			// deal with legacy of shipments array not having been saved in post meta
			// also handle additional shipments (if quantity of items shipped has increased)
			//NB may also need to check for a change of status where items get cancelled.
			if (empty ($shipments) 
				|| (!empty ($shipments)&& $shipments ['items_shipped'] < $qty_shipped)) {
				
				$zoho_order_id = get_post_meta ($this->order_id, BBZ_PM_ZOHO_ORDER_ID, true);
				// get full salesorder details from zoho
				$zoho = new zoho_connector();
				$response = $zoho->get_salesorder($zoho_order_id);
				if (is_wp_error ($response)) {
					$response->add('bbz-shp-001', 'Zoho get_salesorder failed in update_shipments', array(
						'zoho_order_id' => $zoho_order_id,
						));
					return $response;
				}
				$zoho_order = $response;  // this is the full order with package details
				$shipments = $this->load_packages ($zoho_order);
			} 
			
			if (!empty ($shipments)) {
				//bbz_debug (array('Shipments'=>$shipments, 'Trackings'=>$trackings), 'Trackings and Shipments records',false,true);
				// match tracking records to shipments	
				foreach ($trackings as $tracking) {
					foreach ($shipments['packages'] as $i=>$shipment) {
					
						// Have we got a match, and do we need to update zoho?
						if ($tracking->shipping_provider == $shipment['carrier_slug'] 
								&& $tracking->tracking_number == $shipment['tracking_number']
								&& $tracking->shipment_status == 'delivered'
								&& $shipment['zoho_status'] !== 'delivered') {
							//bbz_debug (0,'Match found',false, true);
							
							//Match found, update the shipment status on zoho to 'Delivered'
							$zoho = new zoho_shipmentorders();
							$response = $zoho->update_shipment_status ($shipment['shipment_id'], 'delivered');
							if (is_wp_error ($response)) {
								$response->add('bbz-shp-002', 'Error in update_zoho_tracking', array(
									'shipment' => $shipment,
									'tracking' => $tracking));
								return $response;
							} 
							//bbz_debug ($response, 'update-shipment_status response', false, true);
							
							// update the shipments record in order meta
							$shipments['packages'][$i]['zoho_status'] = 'delivered';
							update_post_meta ($this->order_id, BBZ_PM_SHIPMENTS, $shipments);
							
							// Add the last delivery event in comments on zoho package
							$comment = $tracking->last_event_time . ' ' . $tracking->last_event;
							$response = $zoho->add_package_comment ($shipment['package_id'], $comment);
							if (is_wp_error ($response)) {
								$response->add('bbz-shp-003', 'Error in update_zoho_tracking', array(
									'shipment' => $shipment,
									'tracking' => $tracking));
								return $response;
							} 
							//bbz_debug ($response, 'add_package_comment response', false, true);
						}
					}
				}
				
			}
		}
		return $trackings;
	}

}
?>	