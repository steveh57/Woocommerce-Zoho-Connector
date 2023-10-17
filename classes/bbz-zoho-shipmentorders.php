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
 
	private $valid_update_fields = array
		(
            'shipment_number',
			'date',
			'delivery_method',
            //'salesorder_number',
            //'associated_packages',
            //'is_multipiece_shipment',
            //'customer_name',
            //'status',
			//'shipment_status',
            'shipment_sub_status',
			//'detailed_status',
			//'status_message',
            //'tracking_number',
            //'shipping_charge',
            //'carrier',
			//'tracking_carrier_code',
			//'service',
            //'is_carrier_shipment',
            //'is_tracked_shipment',
            //'is_aggregator_shipment',
            //'is_amazon_fba_shipment',
        );
 
	public function __construct() {
        // call Core constructor
        parent::__construct();
    }
	/*****
	* get_packages
	*
	* @param $filter		Key=>value pairs passed to zoho request.
	*
	* To use a status filter, use $filter = array('filter_by' => 'Status.Shipped')
	*    (valid status values: All|NotShipped|Shipped|Delivered
	*
	* Returns an array of all shipment orders matching the filter:
	*
	*******/
	
	public function get_packages ($filter=array(), $limit=2000) {
	
		// Zoho returns data in pages of 200 by default
		$more_pages = true;
		$next_page = 1;
		$packages = array ();
		
		while ($more_pages && count($packages) <= $limit) {
			$filter ['page'] = $next_page;

			$request = 'packages';
			$response = $this->get_books ($request, $filter);
			if (is_wp_error ($response)) {
				$response->add('bbz-zso-001', 'Zoho get_packages failed', array(
					'filter' => $filter));
				return $response;
			}
			
			if (isset ($response['page_context'])) {
				$more_pages = $response['page_context']['has_more_page'];
				$next_page = $response['page_context']['page'] + 1;
			} else {
				$more_pages = false;
			}
				
			if (isset ($response['packages'])) {
				$packages = array_merge ($packages, $response ['packages']);
			} else {
				return new WP_Error ('bbz-zso-002', 'Zoho get_packages failed', array(
					'filter' => $filter,
					'response'=>$response));
			}
		}
		return $packages;
	}
	
	/*****
	* get_packages_by_status
	*
	* @param $status Can be All|NotShipped|Shipped|Delivered
	*
	* Get summary info for shipments.
	*****/
	public function get_packages_by_status ($status) {
		return $this->get_packages (array('filter_by' => "Status.$status"));
	}	
	
	/*****
	* get_package_by_id
	*
	* @param $package_id Zoho internal package id
	*
	* Get detailed info for a package.
	*****/
	public function get_package_by_id ($package_id) {

		$request = 'packages/'. $package_id;
		
		$response = $this->get_books ($request);
		if (is_wp_error ($response)) {
			$response->add('bbz-zso-003', 'Zoho get_package_by_id failed', array(
				'package id' => $package_id));
			return $response;
		}
		if (isset ($response['package'])) {
			return $response ['package'];
		} else {
			return new WP_Error ('bbz-zso-004', 'Zoho get_package_by_id failed', array(
				'package id' => $package_id,
				'response'=>$response));
		}
	}	
	
	public function add_package_comment ($package_id, $comment) {
		$request = 'packages/'. $package_id . '/comments';
		$data = array ('description'=> $comment);
		$response = $this->post_books ($request, $data);
		if (is_wp_error ($response)) {
			$response->add('bbz-zso-005', 'Zoho add_package_comment failed', array(
				'package id' => $package_id));
			return $response;
		}
		if (isset ($response['comment'])) {
			return $response ['comment'];
		} else {
			return new WP_Error ('bbz-zso-006', 'Zoho add_package_comment failed', array(
				'package id' => $package_id,
				'comment' => $comment,
				'response'=>$response));
		}
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
				$response->add('bbz-zso-010', 'Zoho get_shipmentorders failed', array(
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
				return new WP_Error ('bbz-zso-011', 'Zoho get_shipmentorders failed', array(
					'filter' => $filter,
					'response'=>$response));
			}
		}
		return $shipments;
	}
	
	/*****
	* get_shipmentorders_by_status
	*
	* @param $status Can be All|NotShipped|Shipped|Delivered
	*
	* Get summary info for shipments.
	*****/
	public function get_shipmentorders_by_status ($status) {
		return $this->get_shipmentorders(array('filter_by' => "Status.$status"));
	}	


	/*****
	* get_shipmentorder_by_id
	*
	* @param $shipment_id
	*
	* Get detailed info for a shipment.
	*****/
	public function get_shipmentorder_by_id ($shipment_id) {

		$request = 'shipmentorders/'. $shipment_id;
		
		$response = $this->get_books ($request);
		if (is_wp_error ($response)) {
			$response->add('bbz-zso-012', 'Zoho get_shipmentorder failed', array(
				'shipment id' => $shipment_id));
			return $response;
		}
		if (isset ($response['shipmentorder'])) {
			return $response ['shipmentorder'];
		} else {
			return new WP_Error ('bbz-zso-013', 'Zoho get_shipmentorders failed', array(
				'shipment id' => $shipment_id,
				'response'=>$response));
		}
	}	

	/*****
	* update_status
	*
	* @param $shipment_id Zoho internal shipment id
	* @param $status new value for status
	*
	* Update the primary status.  Valid values include 'shipped', 'delivered'
	*****/
	public function update_shipment_status ($shipment_id, $status) {
	
		$request = 'shipmentorders/'. $shipment_id .'/status/' . $status ;
		$response = $this->post_books ($request);
		if (is_wp_error ($response)) {
			$response->add('bbz-zso-014', 'Zoho get_shipmentorder failed', array(
				'shipment id' => $shipment_id));
			return $response;
		}
		return $response;
	}

	/*****
	* update_shipment
	*
	* @param $shipment array containing shipment record
	*
	* Sends an updated shipment record back to Zoho.  
	* This is not documented by Zoho, but received the following in response to email request:
	*
	* You need to pass the below request URL along with the request JSONString to update a Shipment Order.
	* 
	* Request URL:
	* https://www.zohoapis.eu/books/v3/shipmentorders/shipmentorder_id?
	*	package_ids=package_id&salesorder_id=salesorder_id&organization_id=organization_id
	* 
	* Request JSONString:
	* {
	*     "shipment_number": "shipment_number", (required)
	*     "date": "2023-08-22",(required)
	*     "delivery_method": "delivery_method",(required)
	*     "tracking_number": "tracking_number",
	*     "shipping_charge": "shipping_charge",
 	*    "exchange_rate": "exchange_rate",
	*     "tracking_link": "tracking_link"
	* }
	* but we don't get the package id in the shipment record!
	*****/
	public function update_shipmentorder ($shipment) {
	
		if (!is_array ($shipment) || !isset($shipment['shipment_id'],$shipment['package_id'], $shipment['salesorder_id'])) {
			return new WP_Error ('bbz-zso-015', 'Invalid shipment record in Zoho update_shipment', 
					array('shipment record'=>$shipment));
		}
		$request = 'shipmentorders/'. $shipment['shipment_id'] .'?' . 'package_ids=' .
			$shipment['package_id'] . '&' . 'salesorder_id=' . $shipment['salesorder_id'];
		$data = array();
		// filter any fields that cannot be updated
		foreach ($shipment as $fieldname=>$fieldval) {
			if (in_array ($fieldname, $this->valid_update_fields)) {
				$data[$fieldname] = $fieldval;
			}
		}
		$response = $this->put_books ($request, $data);
		if (is_wp_error ($response)) {
			$response->add('bbz-zso-016', 'Zoho update_shipment failed', array(
				'shipment data' => $data));
			return $response;
		}
		return $response;
	}		
	
} //class
?>