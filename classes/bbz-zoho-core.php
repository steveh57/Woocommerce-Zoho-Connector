<?php
/**
 * Creates the Zoho connector class
 *
 */
 
 // If this file is called directly, abort.
 
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}
 
 class zoho_core {

	public $connected = false;

	function __construct() {
		// check if connections to Zoho has been established
		$this->connected = $this->isconnected();
		return $this->connected;
	}
	
	/*****
	* isconnected()
	*
	* Checks whether we have an access token, and if it has expired, refreshes it
	* If we don't have a token, or it has expired, returns false.
	*****/
	
	public function isconnected () {
		$options = new bbz_options;
		// if we have an access token and it hasn't timed out, we should be ok	
		if ( $options->is_set(array('access_token','token_expires')) 
			&& time() <=  $options->get('token_expires')) return true; 
		//otherwise try to get a new token
		return $this->refresh_token ($options);
	}
	
	/*****
	* refresh_token()
	*
	* The access token is only valid for one hour.  After that we need to get a new one using the refresh token.
	* If we don't have a token, or it has expired, returns false.
	*****/
	private function refresh_token( $options) {
	
		$request_url = ZOHO_AUTH_URL.'token';
		$request_args = array(
			'body' => array ('grant_type' => 'refresh_token')
			);
		
		// copy parameters from $options
		$request_args ['body'] += $options->get (array('refresh_token','client_id','client_secret','redirect_uri'), 'all');
		if (empty($request_args ['body']) ) return new WP_Error ('bbz-zc-101', 'Zoho refresh token failed, options not set');
  
		// send request to zoho
		$response = wp_remote_post ($request_url, $request_args);
		if (!is_array ($response)) {
			if (isset($request_args['headers']['Authorization'])) {  // cut authtoken for security
				$request_args['headers']['Authorization'] = substr($request_args['headers']['Authorization'],0,20).' OBSCURED';
			}
			return new WP_Error ('bbz-zc-102', 'Zoho refresh token failed', array(
				'request_url'=>$request_url,
				'request_args'=> $request_args,
				'response'=> $response
				));
		} else {
			$content = json_decode ($response['body'], true);
			if (isset( $content['error'])) return new WP_Error ('bbz-zc-103', 'Zoho refresh token failed', array(
				'request_url'=>$request_url,
				'request_args'=> $request_args,
				'response'=> $response
				));
			
			if (isset ($content['expires_in'])) {
				// save time at which token expires (in seconds)
				$content['token_expires'] = time() + $content['expires_in'] - 10;
			}
			$options->update($content);	
		}
		return true;
	}
	
	/****
	* _request
	*
	* Params:
	* 	$method	'GET', 'POST' etc
	*	$zoho_api_url	Base API URL string
	*	$request		Specific request part of the URL, appended to the base URL
	*	$data			Passed as the body of the request
	*
	* This is the basic internal  call to the api
	* returns response without any processing
	****/
	private function _request($method, $zoho_api_url, $request, $data = array()) {
		if (! $this->isconnected() ) return new WP_Error ('bbz-zc-001', 'Zoho connection failed in get_books');
		$options = new bbz_options;
		
		//  Avoid making more than 10 calls per second
		$request_count = $options->get('request_count');
		if (!$request_count) $request_count = 0;  // in case not set
		if (time() - $options->get('last_request_time') <= 1) {
			if ($request_count >= 5) {
				sleep (1); // wait for 1 seconds before continuing
				$request_count = 0;
			}
			//sleep (1);
		} else {
			$request_count = 0;
		}
		
		$request_url = $zoho_api_url.$request;
		// For reasons that aren't entirely clear, PUT and POST need to have the data Json encoded, whereas GET needs an array.
		if ($method !== 'GET' && !empty ($data)) {
			$data = 'JSONString='.json_encode ($data);
		}
		$request_args = array(
			'method' => $method,
			'headers' => array (
				'Authorization' => 'Zoho-oauthtoken '.$options->get('access_token'),
//				'content-type' => 'application/json' 
			),
			'body'	=> $data,
			'timeout' => BBZ_ZOHO_TIMEOUT,
		);
		
		$options->update ( array(
			'last_request'=>array ($request_url, $request_args),
			'last_request_time'=>time(), 
			'request_count'=>$request_count));
			
		$response = wp_remote_request ($request_url, $request_args);
		
		if (!is_array($response)) {
			return new WP_Error ('bbz-zc-002', 'Zoho request failed', array(
				'response'=>$response));
		} else {
			$zoho_data = json_decode($response['body'], true);
			// shoulde return an array with ['code'] and ['message'] and ['some data']
			// analytics only returns a code if it fails
			// code=0 = success
			if (isset($request_args['headers']['Authorization'])) {  // cut authtoken for security
				$request_args['headers']['Authorization'] = substr($request_args['headers']['Authorization'],0,20).' OBSCURED';
			}
			if (isset($zoho_data['code']) && $zoho_data['code'] !== 0 ) {
				return new WP_Error ('bbz-zc-003', 'Error returned from request to Zoho_books', array(
					'request url'=>$request_url,
					'request args'=>$request_args,
					'response'=> $zoho_data
				));
			} elseif (empty($zoho_data)) {
				return new WP_Error ('bbz-zc-003A', 'No JSON data returned from request to Zoho_books', array(
					'request url'=>$request_url,
					'request args'=>$request_args,
					'response'=> $response
				));
			} else {
				return $zoho_data;
			}
		}
	}

	/****
	* get_books, post_books, put_books
	*
	* calls to zoho books api
	* returns the decoded data body or WP_error message
	****/
	
	public function get_books ($request, $filter=array()) {

		$response = $this->_request ('GET', ZOHO_BOOKS_API_URL, $request, $filter);
		if (is_wp_error ($response)) {
			$response->add('bbz-zc-004', 'Zoho get_books failed', 
				array('request'=>$request, 'filter'=>$filter));
		}
		return $response;
	}
	
	public function post_books ($request, $postdata=array()) {
		// returns either wp_error or data array
		$response =  $this->_request ('POST', ZOHO_BOOKS_API_URL, $request, $postdata);
		if (is_wp_error ($response)) {
			$response->add('bbz-zc-005', 'Zoho post_books failed', 
				array('request'=>$request, 'postdata'=>$postdata));
		}
		return $response;
	}
	
	public function put_books ($request, $postdata=array()) {
		$response = $this->_request ('PUT', ZOHO_BOOKS_API_URL, $request, $postdata);
		if (is_wp_error ($response)) {
			$response->add('bbz-zc-006', 'Zoho put_books failed', 
				array('request'=>$request, 'postdata'=>$postdata));
		}
		return $response;

	}
	
	public function delete_books ($request) {
		$response = $this->_request ('DELETE', ZOHO_BOOKS_API_URL, $request);
		if (is_wp_error ($response)) {
			$response->add('bbz-zc-007', 'Zoho delete_books failed', 
				array('request'=>$request));
		}
		return $response;
	}

	/****
	* get_analytics
	*
	* call to zoho analtics api
	* returns response without any processing
	****/
	
	public function get_analytics ($table_name, $filter=array()) {
		if (! $this->isconnected() ) return false;
		
		$request = $table_name.'?ZOHO_ACTION=EXPORT&ZOHO_OUTPUT_FORMAT=JSON&ZOHO_ERROR_FORMAT=JSON&ZOHO_API_VERSION=1.0'.
			'&ZOHO_VALID_JSON=true&KEY_VALUE_FORMAT=true';

		return $this->_request ('GET', ZOHO_ANALYTICS_API_URL, $request, $filter);
	}
}
?>