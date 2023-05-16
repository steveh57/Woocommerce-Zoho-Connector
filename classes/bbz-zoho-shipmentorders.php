<?php
/**
 * Creates the Zoho Shipment Orders class
 *
 * Used to access shipment orders from Zoho API
 ****/ 
 
 // If this file is called directly, abort.
 if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}
 
 class zoho_shipmentorders extends zoho_core {
 
	public function __construct() {
        // call Core constructor
        parent::__construct();
    }
 
 	/*****
	* get_shipmentorders
	*
	* @param $filter		Key=>value pairs passed to zoho request.
	*
	* To use a status filter, use $filter = array('filter_by' => 'Status.Shipped')
	*    (or 'Status.Delivered')
	*
	* Returns an array of all shipment orders matching the filter:
	*
	* Array
        (
            [shipment_id] => 1504573000025983775
            [shipment_number] => SHP-05150
            [salesorder_id] => 1504573000025846031
            [salesorder_number] => WO-32864
            [associated_packages] => PKG-05167
            [is_multipiece_shipment] => 
            [customer_name] => Website Sales
            [status] => shipped
            [shipment_sub_status] => undefined
            [tracking_number] => 32-059 479 2000-43E 5D1 362
            [shipping_charge] => 0
            [date] => 2023-01-03
            [created_time] => 1672759095347
            [created_time_formatted] => 2023-01-03T15:18:15+0000
            [carrier] => Royal Mail
            [label_format] => PNG
            [last_modified_time] => 1672759095347
            [last_modified_time_formatted] => 2023-01-03T15:18:15+0000
            [is_carrier_shipment] => 
            [is_tracked_shipment] => 
            [is_aggregator_shipment] => 
            [is_amazon_fba_shipment] => 
        )

	*
	*****/
	
	public function get_shipmentorders ($filter=array(), $limit=2000) {
	
		// Zoho returns data in pages of 200 by default
		$more_pages = true;
		$next_page = 1;
		$shipments = array ();
		
		while ($more_pages && count($shipments) <= $limit) {
			$filter ['page'] = $next_page;

			$request = 'shipmentorders';
			$response = $this->get_books ($request, $filter);
			if (is_wp_error ($response)) {
				$response->add('bbz-zc-037', 'Zoho get_shipmentorders failed', array(
					'filter' => $filter));
				return $response;
			}
			
			if (isset ($response['page_context'])) {
				$more_pages = $response['page_context']['has_more_page'];
				$next_page = $response['page_context']['page'] + 1;
			} else {
				$more_pages = false;
			}
				
			if (isset ($response['shipmentorders'])) {
				$shipments = array_merge ($shipments, $response ['shipmentorders']);
			} else {
				return new WP_Error ('bbz-zc-038', 'Zoho get_shipmentorders failed', array(
					'filter' => $filter,
					'response'=>$response));
			}
		}
		return $shipments;
	}

	/*****
	* get_shipmentorder_by_id
	*
	* @param $shipment_id
	*
	* Get detailed info for a shipment.
	*****/
	public function get_shipmentorder_by_id ($shipment_id) {

		$request = 'shipmentorders'. '/'. $shipment_id;;
		
		$response = $this->get_books ($request);
		if (is_wp_error ($response)) {
			$response->add('bbz-zc-039', 'Zoho get_shipmentorder failed', array(
				'shipment id' => $shipment_id));
			return $response;
		}
		if (isset ($response['shipmentorder'])) {
			return $response ['shipmentorder'];
		} else {
			return new WP_Error ('bbz-zc-040', 'Zoho get_shipmentorders failed', array(
				'shipment id' => $shipment_id,
				'response'=>$response));
		}
	}	

	
} //class
?>