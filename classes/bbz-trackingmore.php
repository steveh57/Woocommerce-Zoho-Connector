<?php
/**
 * Creates the trackingmore class
 *
 * Used for connection to the trackingmore.com api for shipment tracking
 *
 */
 
 // If this file is called directly, abort.
 
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}
 
 class trackingmore {

	function __construct() {
		
		// Nothing to do here at the moment
		
		
	}
	
	/****
	* _request
	*
	* This is the basic internal get call to the api
	* returns response without any processing
	*
	* $method = GET, POST etc
	* $request = URL suffix (e.g. 'couriers/all'
	* $params = body content as an array of key/value pairs
	****/
	
	private function _request($method, $request, $params = array()) {
		$options = get_option( BBZ_OPTION_NAME );
		$request_url = BBZ_TM_URL.$request;
		$request_args = array(
			'method' => $method,
			'headers' => array ('tracking-api-key' => BBZ_TM_APIKEY	),
			'timeout' => BBZ_TM_TIMEOUT,
		);
		if (!empty ($params)) {
			$request_args ['body'] = 'JSONString='.json_encode ($params);
		}
		$options ['last_request'] = $request_url;
		update_option (BBZ_OPTION_NAME, $options);
		
		return wp_remote_request ($request_url, $request_args);
	}
	
	public function create_tracking ($courier, $tracking_number, $postcode, $country) {
		$body = array (
			'courier_code' => $courier,
			'tracking_number' => $tracking_number,
			'tracking_postal_code' => $postcode,
			'tracking_destination_country' => $country
		);
		$response = $this->_request ('POST', 'trackings/create', $body);
		
		if (!is_array($response)) {
			return new WP_Error ('bbz-tm-101', 'Trackingmore create tracking failed', array(
				'request'=>$body,
				'response'=>$response));
		} else {
			$tm_data = json_decode($response['body'], true);
			// shoulde return an array with ['meta']['code'] and ['message'], and ['data']
			// code=200 = success
			if (!isset($tm_data['meta']['code']) || $tm_data['meta']['code'] !== 200 ) {
				return new WP_Error ('bbz-tm-102', 'Error returned from create_tracking', array(
					'request'=>$body,
					'response'=>$tm_data));
			} else {
				return $tm_data;
			}
		}
	}
	
	public function get_results () {
		
		$response = $this->_request ('GET', 'trackings/get');
		
		if (!is_array($response)) {
			return new WP_Error ('bbz-tm-103', 'Trackingmore get_results failed', array(
				'response'=>$response));
		} else {
			$tm_data = json_decode($response['body'], true);
			// shoulde return an array with ['meta']['code'] and ['message'], and ['data']
			// code=200 = success
			if (!isset($tm_data['meta']['code']) || $tm_data['meta']['code'] !== 200 ) {
				return new WP_Error ('bbz-tm-104', 'Error returned from get_results', array(
					'response'=>$tm_data));
			} else {
				return $tm_data;
			}
		}
	}	
	
} //class
?>