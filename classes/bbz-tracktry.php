<?php
/**
 * Creates the bbz_tracking class used to obtain tracking data
 *
 */
const BBZ_TRACKTRY_APIKEY = "0dabf9e3-1336-4ba2-b0b9-538064ffbf1d";
const BBZ_TRACKTRY_HOST = 'api.tracktry.com';
const BBZ_TRACKTRY_BASE_URL = 'http://api.Tracktry.com/v1/';
const BBZ_TRACKTRY_TIMEOUT = 20;
const BBZ_TRACKTRY_MAX_BATCH = 5;  // Tracktry maximum is 40

 // If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

include_once ( BBZ_ASSETSPATH . '/track.class.php');  //include Tracktry.com provided PHP

class bbz_tracking {

	function __construct() {
		$this->apiKey = BBZ_TRACKTRY_APIKEY;
	}
	
	protected $valid_parameters = array (
		'tracking_number',
		'carrier_code',
		'title',
		'logistics_channel',
		'customer_name',
		'customer_email',
		'order_id',
		'customer_phone',
		'order_create_time',
		'destination_code',
		'tracking_ship_date',
		'tracking_postal_code',
		'lang',
		'tracking_destination_country',
		'comment');
	/****
	* _request
	*
	* Params:
	* 	$method			'GET', 'POST' etc
	*	$request_url	Request part of URL string
	*	$data			Passed as the body of the request
	*
	* This is the basic internal  call to the api
	* returns response without any processing
	****/	
	private function _request($method, $request_url, $data = array()) {
		$options = new bbz_options;
		
		//  Avoid making more than 10 calls per second
		/* $request_count = $options->get('request_count');
		if (!$request_count) $request_count = 0;  // in case not set
		if (time() - $options->get('last_request_time') <= 1) {
			if ($request_count >= 9) {
				sleep (2); // wait for 2 seconds before continuing
				$request_count = 0;
			}
		} else {
			$request_count = 0;
		}
		*/
		$data = json_encode ($data);
		$request_url = BBZ_TRACKTRY_BASE_URL.$request_url;
		$request_args = array(
			'method' => $method,
			'headers' => array (
				'Tracktry-Api-Key' => BBZ_TRACKTRY_APIKEY,
				'Content-Type' => 'application/json',
				'Host' => BBZ_TRACKTRY_HOST,
				'Content-Length' => strval(strlen ($data))
			),
			'body'	=> $data,
			'timeout' => BBZ_TRACKTRY_TIMEOUT,
		);
		/*
		$options->update ( array(
			'last_request'=>array ($request_url, $request_args),
			'last_request_time'=>time(), 
			'request_count'=>$request_count));
		*/
		bbz_debug (array ($request_url, $request_args), 'Calling wp_remote_request', false);
		return wp_remote_request ($request_url, $request_args);
	}
	
	// Create a single tracking item
	
	public function createTracking ($ship_info = array()) {
		$sendData   = array();
		foreach ($ship_info as $key=>$value) {
			if (in_array($key, $this->valid_parameters)) {
				$sendData[$key] = $value;
			}
		}
		
		// bbz_debug(array($requestUrl, 'POST', $sendData), 'Call _request', false);
        $response = $this->_request ('POST', 'trackings/post', $sendData);
		// bbz_debug ($response, '_request result', false);

		if (is_wp_error($response)) {
			$response->add('bbz-tt-001', 'createTracking failed', $response);
			return $response;
		} else {
			$tt_data = json_decode($response['body'], true);

			// code=200 = success
			if (!isset($tt_data['meta']['code']) || $tt_data['meta']['code'] !== 200 ) {
				return new WP_Error ('bbz-tt-002', 'createTracking failed', array(
				'returned body'=> $tt_data,
				'sendData'=> $sendData,
				'json'=>json_encode ($sendData)));
			} else {
				return $tt_data;
			}
		}
    }

	// Create multiple tracking items
	
	public function createMultipleTracking ($shipitems = array()) {
		$sendData   = array();
		$track_count = 0;
		foreach ($shipitems as $ship_info) {
			$item  = array();
			foreach ($ship_info as $key=>$value) {
				if (in_array($key, $this->valid_parameters)) {
					$item[$key] = $value;
				}
			}
			// check we have at least the tracking number and carrier code
			if (!empty ($item ['tracking_number']) && !empty ($item ['carrier_code'])) {
				$sendData[] = $item;
				$track_count += 1;
				if ($track_count >= BBZ_TRACKTRY_MAX_BATCH) break;  // limit max number in batch
			}
		}
		
		// bbz_debug(array($requestUrl, 'POST', $sendData), 'Call _request', false);
        $response = $this->_request ('POST', 'trackings/batch', $sendData);
		// bbz_debug ($response, '_request result', false);

		if (is_wp_error($response)) {
			$response->add('bbz-tt-011', 'createTracking failed', $response);
			return $response;
		} else {
			$tt_data = json_decode($response['body'], true);

			// code=200 = success
			if (!isset($tt_data['meta']['code']) || $tt_data['meta']['code'] !== 200 ) {
				return new WP_Error ('bbz-tt-012', 'Error returned from POST trackings/batch to Tracktry', array(
				'response'=>$tt_data,
				'sendData'=> $sendData));
			} else {
				return $tt_data;
			}
		}
    }	
	
	// Get Single Tracking record
	
	public function getSingleTracking ($carrier_code, $tracking_number) {
		
		// bbz_debug(array($requestUrl, 'POST', $sendData), 'Call _request', false);
        $response = $this->_request ('GET', "trackings/$carrier_code/$tracking_number");
		// bbz_debug ($response, '_request result', false);

		if (is_wp_error($response)) {
			$response->add('bbz-tt-021', 'getSingleTracking failed', $response);
			return $response;
		} else {
			$tt_data = json_decode($response['body'], true);

			// code=200 = success
			if (!isset($tt_data['meta']['code']) || $tt_data['meta']['code'] !== 200 ) {
				return new WP_Error ('bbz-tt-002', 'createTracking failed', array(
				'returned body'=> $tt_data,
				'sendData'=> $sendData,
				'json'=>json_encode ($sendData)));
			} else {
					
				return $tt_data;
			}
		}
    }
	
	
	
	
	/******
	*	Create
	*
	*	Create tracking records in Tracktry.com for each shipment record
	* 	Should really use CreateMultipleTracking, but doing one at time to start with
	*
	*	@param $shipments	array of shipment records returned by Zoho
	******/
	
	public function create ($shipments) {
		echo 'In bbz_tracking->create<br>';
		$carrier_codes = array(
			"Royal Mail" => 'royal-mail',
			"DX" => 'dxdelivery'
			);
		$country_map = array (
			'United Kingdom' => 'GB',
			'United Kingdom (UK)' => 'GB'
			);
		
		$badchars = array (' ', '-', '/');
		$tracktry_multi = array();
	
		foreach ($shipments as $shipment) {
			$tracktry_single = array();
			if (isset ($carrier_codes[$shipment['carrier']]) && !empty ($shipment ['tracking_number'])) { //only process supported carriers
				$tracktry_single['carrier_code'] = $carrier_codes[$shipment['carrier']];
				$tracktry_single['tracking_number'] = str_replace ($badchars, '', $shipment ['tracking_number']);
				$tracktry_single ['order_id'] = $shipment ['salesorder_number'].' | '.$shipment ['associated_packages'];
				$tracktry_single ['comment'] = $shipment ['shipment_id'];
				$tracktry_single ['tracking_ship_date'] = $shipment ['date'];
				if (!empty ($shipment['zip'])) {
					$tracktry_single ['tracking_postal_code'] = str_replace ($badchars, '', $shipment ['zip']); // remove spaces
				}
				if (!empty ($shipment['country'])) {
					if (isset ($country_map [$shipment['country']])) {
					$tracktry_single ['tracking_destination_country'] = $country_map [$shipment['country']];
					}
				} else $tracktry_single ['tracking_destination_country'] = 'GB';  //default to GB if blank
				$tracktry_multi[] = $tracktry_single;
/* Single item tracking
				// bbz_debug ( $tracktry_single, 'Calling createTracking', false);
				$response = $this->createTracking ($tracktry_single); 
				if (is_wp_error($response)) {
					$response->add('bbz-tt-011', 'createTracking failed', $tracktry_single);
					bbz_debug($response);
				}
				return $response;
*/
			}
		}
		if (!empty($tracktry_multi)) {
			$response = $this->createMultipleTracking ($tracktry_multi); 
			if (is_wp_error($response)) {
				$response->add('bbz-tt-101', 'createMultipleTracking failed', $tracktry_multi);
				bbz_debug($response);
			}
			return $response;
		}			
	}
	
	/******
	*	Get one or all active tracking records
	*
	*	@param $shipment	optional single shipment record
	*
	******/	
	
	public function get ($shipment='') {
		if (!empty($shipment)) {
			if (isset ($carrier_codes[$shipment['carrier']]) && !empty ($shipment ['tracking_number'])) { //only process supported carriers
				$carrier_code = $carrier_codes[$shipment['carrier']];
				$tracking_number = str_replace ($badchars, '', $shipment ['tracking_number']);
				$response = getSingleTracking ($carrier_code, $tracking_number);
				if (is_wp_error($response)) {
					$response->add('bbz-tt-111', 'tracking->get failed', $response);
					bbz_debug($response);
					return $response;
				} else {
					$shipment['status'] = $response['data']['status'];
					if (isset($response['data']['origin_info']['trackinfo'])) {
						$shipment['trackinfo'] = json_encode($response['data']['origin_info']['trackinfo']);
					}
					if (isset($response['data']['origin_info']['lastEvent'])) {
						$shipment['last_event'] = $response['data']['origin_info']['lastEvent'];
					}
					if (isset($response['data']['origin_info']['lastUpdateTime'])) {
						$shipment['last_event_time'] = $response['data']['origin_info']['lastUpdateTime'];
					}
					return $shipment;
				}
			}
		}
	}
	
	/******
	* Delete a shipment record from Tracktry
	*
	*	@param $shipment	single shipment record
	*******/
	
	public function delete ($shipment='') {
	
	
	}
	
	
}
?>