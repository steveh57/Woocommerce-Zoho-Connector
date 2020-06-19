<?php
/**
 * Creates the Zoho connector class
 *
 */
 // If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}
 
 class zoho_connector {

	public $connected = false;

	function __construct() {
		
		// check if connections to Zoho has been established
		$this->connected = $this->isconnected();
		
	}
	
	/*****
	* isconnected()
	*
	* Checks whether we have an access token, and if it has expired, refreshes it
	* If we don't have a token, or it has expired, returns false.
	*****/
	
	public function isconnected () {
		$options = get_option ( OPTION_NAME );
		
		// we have an access token and it hasn't timed out, we should be ok	
		if ( isset ( $options ['access_token']) && isset ( $options ['token_expires']) 
			&& time() <=  $options ['token_expires']) return true; 
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
			'body' => array (
				'grant_type' => 'refresh_token'
			)
		);
		// copy parameters from $options
		foreach ( array('refresh_token','client_id','client_secret','redirect_uri') as $name) {
			if (! isset ( $options [ $name ])) return false;
			$request_args ['body'][$name] = $options [ $name ];
		}
		// send request to zoho
		// TODO look at error handling
		$response = wp_remote_post ($request_url, $request_args);
		if (!is_array ($response)) {
			return false;
		} else {
			$content = json_decode ($response['body'], true);
			foreach ($content as $key => $value) {
				$options [$key] = $value;
			}
			if (isset ($content['expires_in'])) {
				// save time at which token expires (in seconds)
				$options ['token_expires'] = time() + $content['expires_in'] - 10;
			}
			update_option(OPTION_NAME, $options);
			if (isset( $content['error'])) return false;			
		}
		return true;
	}
	
	/****
	* _get_data
	*
	* This is the basic internal get call to the api
	* returns response without any processing
	****/
	private function _get_data($zoho_api_url, $request, $filter = array()) {
		$options = get_option( OPTION_NAME );
		$request_url = $zoho_api_url.$request;
		$request_args = array(
			'headers' => array (
				'Authorization' => 'Zoho-oauthtoken '.$options ['access_token']
			),
			'body'	=> $filter
		);
		$options ['last_request'] = $request_url;
		update_option (OPTION_NAME, $options);
		
		return wp_remote_get ($request_url, $request_args);
	}
	
	/****
	* _post_data
	*
	* This is the basic internal post call to the api
	* returns response without any processing
	****/
	private function _post_data($zoho_api_url, $request, $postdata = array()) {
		$options = get_option( OPTION_NAME );
		$request_url = $zoho_api_url.$request;
		$request_args = array(
			'headers' => array ('Authorization' => 'Zoho-oauthtoken '.$options ['access_token']),
			'body' => '',
			);
		if (!empty ($postdata)) {
			$request_args ['body'] = 'JSONString='.json_encode ($postdata);
		}
		$options ['last_request'] = $request_url;
		update_option (OPTION_NAME, $options);
		
		return wp_remote_post ($request_url, $request_args);
	}
	/****
	* _put_data
	*
	* This is the basic internal PUT call to the api
	* returns response without any processing
	****/
	private function _put_data($zoho_api_url, $request, $postdata = array()) {
		$options = get_option( OPTION_NAME );
		$request_url = $zoho_api_url.$request;
		$request_args = array(
			'method' => 'PUT',
			'headers' => array (
				'Authorization' => 'Zoho-oauthtoken '.$options ['access_token']
			),
			'body'	=> 'JSONString='.json_encode ($postdata),
		);
		$options ['last_request'] = $request_url;
		update_option (OPTION_NAME, $options);
		
		return wp_remote_request ($request_url, $request_args);
	}
	/****
	* get_books, post_books, put_books
	*
	* calls to zoho books api
	* returns response without any processing
	****/
	
	public function get_books ($request, $filter=array()) {
		if (! $this->isconnected() ) return new WP_Error ('bbz-zc-101', 'Zoho connection failed in get_books');
		$response = $this->_get_data (ZOHO_BOOKS_API_URL, $request, $filter);
		if (!is_array($response)) {
			return new WP_Error ('bbz-zc-102', 'Zoho get_data failed', array(
				'response'=>$response));
		} else {
			$zoho_data = json_decode($response['body'], true);
			// shoulde return an array with ['code'] and ['message'] and ['some data']
			// code=0 = success
			if (!isset($zoho_data['code']) || $zoho_data['code'] !== 0 ) {
				return new WP_Error ('bbz-zc-103', 'Zoho get_data failed', $zoho_data);
			} else {
				return $zoho_data;
			}
		}

	}
	
	public function post_books ($request, $postdata=array()) {
		// returns either wp_error or data array
		if (! $this->isconnected() ) return new WP_Error ('bbz-zc-001', 'Zoho connection failed in post_books');
		$response =  $this->_post_data (ZOHO_BOOKS_API_URL, $request, $postdata);
		if (!is_array($response)) {
			return new WP_Error ('bbz-zc-002', 'Zoho post_data failed', array(
				'response'=>$response));
		} else {
			$zoho_data = json_decode($response['body'], true);
			// shoulde return an array with ['code'] and ['message'] and ['some data']
			// code=0 = success
			if (!isset($zoho_data['code']) || $zoho_data['code'] !== 0 ) {
				return new WP_Error ('bbz-zc-003', 'Zoho post_data failed', $zoho_data);
			} else {
				return $zoho_data;
			}
		}
	}
	
	public function put_books ($request, $postdata=array()) {
		if (! $this->isconnected() )
			return new WP_Error ('bbz-zc-004', 'Zoho connection failed in put_books');
		$response = $this->_put_data (ZOHO_BOOKS_API_URL, $request, $postdata);
		if (!is_array($response)) {
			return new WP_Error ('bbz-zc-005', 'Zoho put_data failed', array(
				'response'=>$response));
		} else {
			$zoho_data = json_decode($response['body'], true);
			if (!isset($zoho_data['code']) || $zoho_data['code'] !== 0 ) {
				return new WP_Error ('bbz-zc-006', 'Zoho put_data failed', $zoho_data);
			} else {
				return $zoho_data;
			}
		}
		
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

		return $this->_get_data (ZOHO_ANALYTICS_API_URL, $request, $filter);
	}
	/*****
	* get_items
	*
	* returns an an array of
	* 'sku' => array (
	*		isbn		// usually same as sku
	*		zoho_id		// zoho item id
	*		status		// active or inactive
	*		name
	*		rrp
	*		wsp			// wholesale price
	*		stock		// available stock
	****/
	
	public function get_items () {
		$field_map = array ( // internal_field_name => zoho_field_name
			//'sku'		=> 'sku',			// sku
			'isbn' 		=> 'isbn',			// isbn field usually same as sku
			'zoho_id'	=> 'item_id',		// zoho item id
			'status'	=> 'status',		// status active or inactive
			'name'		=> 'name',			// name of product
			'rrp'		=> 'cf_rrp_unformatted',	// Recommended retail price
			'orp'		=> 'cf_orp_unformatted',	// original retail price
			'wsp'		=> 'rate',					// wholesale selling price
			'stock'		=> 'actual_available_stock',		// current stock level
			'tax_class'	=> 'tax_name',			// tax class
			'shipping_class'	=>	'cf_shipping_class_unformatted',	// shipping class code
			'wholesale_only'	=> 'cf_wholesale_only_unformatted',	// Yes or No or blank
			'inactive_reason'	=> 'cf_inactive_reason_unformatted',
			
		);
		if (! $this->isconnected() ) return false;
		
		// Zoho returns data in pages of 200 by default
		$more_pages = true;
		$next_page = 1;
		$items = array ();
		
		while ($more_pages) {
			$filter = array ('page'=>$next_page);
			// fetch item data from Zoho
			$zoho_data = $this->get_books ('items', $filter);
			if (is_wp_error($zoho_data)) break;
			if (isset ($zoho_data['page_context'])) {
				$more_pages = $zoho_data['page_context']['has_more_page'];
				$next_page = $zoho_data['page_context']['page'] + 1;
			} else {
				$more_pages = false;
			}
			if (!isset ($zoho_data['items'])) break;
			
			foreach ($zoho_data['items'] as $zoho_item) {
				$sku = $zoho_item['sku'];
				foreach ($field_map as $int_field_name => $zoho_field_name) {
					if (isset ($zoho_item[$zoho_field_name])) {
						$items [$sku][$int_field_name] = $zoho_item[$zoho_field_name];
					} else {
						$items [$sku][$int_field_name] = ''; // default to empty string
					}
				}
			}
		}
		return $items;				
	}
	/*****
	* get_customers
	*
	* returns an an array of
	* 'contact_id' => array (
	*	email
	*	company_name
	*	first_name
	*	last_name
	*	phone
	****/
	
	public function get_customers () {
		$field_map = array ( // internal_field_name => zoho_field_name
			'email' 		=> 'email',			// email
			'zoho_id'	=> 'contact_id',		// zoho item id
			'status'	=> 'status',		// status active or inactive
			'company_name'		=> 'company_name',
			'first_name'		=> 'first_name',
			'last_name'		=> 'last_name',
			'phone'		=> 'phone',
		);
		if (! $this->isconnected() ) return false;
		
		// Zoho returns data in pages of 200 by default
		$more_pages = true;
		$next_page = 1;
		$contacts = array ();
		
		while ($more_pages) {
			$filter = array (
				'page'			=> $next_page,
				'contact_type'	=> 'customer',
			);
			// fetch item data from Zoho
			$zoho_data = $this->get_books ('contacts', $filter);
			if (is_wp_error($zoho_data)) break;
			if (isset ($zoho_data['page_context'])) {
				$more_pages = $zoho_data['page_context']['has_more_page'];
				$next_page = $zoho_data['page_context']['page'] + 1;
			} else {
				$more_pages = false;
			}
			if (!isset ($zoho_data['contacts'])) break;
			
			foreach ($zoho_data['contacts'] as $zoho_contact) {
				$cid = $zoho_contact['contact_id'];
				foreach ($field_map as $int_field_name => $zoho_field_name) {
					if (isset ($zoho_contact[$zoho_field_name])) {
						$contacts [$cid][$int_field_name] = $zoho_contact[$zoho_field_name];
					} else {
						$contacts [$cid][$int_field_name] = ''; // default to empty string
					}
				}
			}
		}
		return $contacts;				
	}

/*****
* get_customer_emails
*
* returns an an array of valid customer email records
* each record has fields email, customer_id, customer_name
****/
	
	public function get_customer_emails () {
		if (! $this->isconnected() ) return false;
		
		$response = $this->get_analytics ('BBZ Email Map');
		if (is_array($response)) {
			$body = json_decode ($response['body'], true);
			if ( is_array ($body) && is_array ($body['data'])) {
				$email_list = array();
				foreach ($body['data'] as $zoho_contact) {
					if (! empty ($zoho_contact['email'])  && empty ($email_list[$zoho_contact['email']] ) 
						&& is_numeric ($zoho_contact['customer_id'] )) {
						$email_list[] = $zoho_contact;
					}
				}

			}
		}

/* Get from Zoho Books version - only gets principal email		
		// Zoho returns data in pages of 200 by default
		$more_pages = true;
		$next_page = 1;
		$email_list = array ();
		
		while ($more_pages) {
			$filter = array (
				'page'			=> $next_page,
				'contact_type'	=> 'customer',
			);
			// fetch item data from Zoho
			$response = $this->get_books ('contacts', $filter);
			if (!is_array($response)) break;
			$zoho_data = json_decode($response['body'], true);
			if (isset ($zoho_data['page_context'])) {
				$more_pages = $zoho_data['page_context']['has_more_page'];
				$next_page = $zoho_data['page_context']['page'] + 1;
			} else {
				$more_pages = false;
			}
			if (!isset ($zoho_data['contacts'])) break;
			
			foreach ($zoho_data['contacts'] as $zoho_contact) {
				$email = $zoho_contact['email'];
				if (! $email == '') $email_list[$zoho_contact ['contact_id']] = $email;
			}
		}
*/
		return $email_list;				
	}
	
/*****
* get_id_from_email
*
* Uses the email list to validate a given email
* Returns the zoho id or false
*****/
	public function get_id_from_email ($email='') {
		if (empty($email) ) return false;
		
		if (! $this->isconnected() ) return false;
		
		$email_list = $this->get_customer_emails ();
		
		if ( !is_array($email_list)) return false;
		
		// search for email address
		$customer_id = false;
		$email = strtolower ($email);  // force lower case for comparison
		foreach ($email_list as $record) {
			if (strtolower($record['email']) == $email) {
				return $record['customer_id'];  //found it
			}
		}
		return false;
	}

/*****
* get_customer_names
*
* returns an an array of valid customer names and ids
****/
	
	public function get_customer_names () {
		if (! $this->isconnected() ) return false;
		
		// Zoho returns data in pages of 200 by default
		$more_pages = true;
		$next_page = 1;
		$email_list = array ();
		
		while ($more_pages) {
			$filter = array (
				'page'			=> $next_page,
				'contact_type'	=> 'customer',
			);
			// fetch item data from Zoho
			$zoho_data = $this->get_books ('contacts', $filter);
			if (is_wp_error($zoho_data)) break;
			if (isset ($zoho_data['page_context'])) {
				$more_pages = $zoho_data['page_context']['has_more_page'];
				$next_page = $zoho_data['page_context']['page'] + 1;
			} else {
				$more_pages = false;
			}
			if (!isset ($zoho_data['contacts'])) break;
			
			foreach ($zoho_data['contacts'] as $zoho_contact) {
				$name = $zoho_contact['contact_name'];
				if (! $name == '') $name_list[$zoho_contact ['contact_id']] = $name;
			}
		}
		return $name_list;				
	}

/*****
* get_contact_address
*
* returns an an array of customer addresses for specified customer id
* probably redundant as now getting addresses in get_contact_by_id
****/	

	public function get_contact_address ($contact_id='') {

		if ($contact_id == '') return false;
		
//		$filter = array ('email'=>$email);
//		$request = 'contacts/'.$contact_id.'/address';
		$zoho_data = $this->get_books ('contacts/'.$contact_id.'/address');
		if (is_wp_error($zoho_data)) {
			$zoho_data->add ('bbz-zc-110', 'get_contact_address failed', $zoho_data);
			return $zoho_data;
		}
		
		if (isset ($zoho_data['addresses'])) {
			return $zoho_data['addresses'];
		} else {
			return new WP_Error ('bbz-zc-111', 'get_contact_address failed', $zoho_data);
		}	
	
	}
/*****
* get_contact_by_email
* get_contact_by_id
*
* returns an an array of customer data from zoho
* 
*
****/
	
	public function get_contact_by_email ($email='') {
		//validate and get contoact id
		$customer_id = $this->get_id_from_email ($email);
		
		// now get full contact record
		return $this->get_contact_by_id ($customer_id);
	}
	
	public function get_contact_by_id ($zoho_id='') {
		if (empty($zoho_id)) return false;
	
		$zoho_data = $this->get_books ('contacts/'.$zoho_id);
		if (is_wp_error($zoho_data)) {
			$zoho_data->add ('bbz-zc-112', 'get_contact_by_id failed', $zoho_data);
			return $zoho_data;
		}
		
		if (isset ($zoho_data['contact'])) {
			return $zoho_data['contact']; 
		} else {
			return new WP_Error ('bbz-zc-111', 'get_contact_by_id failed', $zoho_data);
		}

	}
/*********
* get_sales_history
*
* Returns an array of sales data for each customer
* Format 
* array( 
*		zoho_customer_id => array (
*			zoho_product_id => array (
*				'2018'	=> n, //unit sales in 2018
*				'2019'	=> n, //unit sales in 2019
*				'2020'	=> n  //unit sales in 2020
*			),
*			...
*		)
*		...
*	)
* Note that the values for customer and product ids are from Zoho and still need to be mapped
* to Wordpress/Woo post and user ids.
********/

	public function get_sales_history () {
		$response = $this->get_analytics ('BBZ Sales History');
		if (is_array($response)) {
			$body = json_decode ($response['body'], true);
			if ( is_array ($body) && is_array ($body['data'])) {
				$years = array ('2018','2019','2020');  // could automate this to last 3 years?
				$results = array();
				foreach ($body['data'] as $row) {
					if (!empty ($row['Customer ID']) && !empty ($row['Item ID']) && is_numeric ($row['Customer ID'])){
						$cust_id = $row['Customer ID'];
						$item_id = $row['Item ID'];
						foreach ($years as $year) {
							$results[$cust_id][$item_id][$year] = 
								empty ($row[$year]) ? 0 : 0 + $row[$year]; // force numeric value
						}
					}
				}
				return $results;
			}
		}
		return false;
	}
/*****
* add_address
*
* adds a new address for specified customer.
* fields must be in correct format for zoho
****/	

	public function add_address ($contact_id='', $address) {

		$response = $this->post_books ('contacts/'.$contact_id.'/address', $address);
		if (is_wp_error ($response)) {
			$response->add('bbz-zc-020', 'Zoho add_address failed', array(
				'contact_id'=>$contact_id,
				'address'=>$address));
			return $response;
		}
		//bbz_debug ($zoho_data, 'zoho add address');
		if (isset ($response['address_info'])) {
			return $response['address_info'];
		} else {
			return new WP_Error ('bbz-zc-021', 'Zoho add_address failed', array(
				'contact_id'=>$contact_id,
				'address'=>$address,
				'response'=>$response));
		}	
	
	}
	
/*****
* update_address
*
* updates an existing address for specified customer.
* fields must be in correct format for zoho and zoho address id must be specified
****/	

	public function update_address ($contact_id='', $address, $address_id) {
		//bbz_debug (array ($contact_id, $address_id, $address), 'In zoho update_address', false);
		if (! $this->isconnected() ) return false;
		
		if ($contact_id == '') return false;
		$url = 'contacts/'.$contact_id.'/address/'.$address_id;
		$response = $this->put_books ($url, $address);
		if (is_wp_error ($response)) {
			$response->add('bbz-zc-022', 'Zoho update_address failed', array(
				'contact_id'=>$contact_id,
				'address'=>$address,
				'address_id'=>$address_id));
			return $response;
		}
		//bbz_debug ($zoho_data, 'zoho add address');
		if (isset ($response['address_info'])) {
			//success
			return $response['address_info'];
		} else {
			return new WP_Error ('bbz-zc-023', 'Zoho update_address failed', array(
				'contact_id'=>$contact_id,
				'address'=>$address,
				'address_id'=>$address_id,
				'response'=>$response));
		}	
	
	}	

/*****
* create_sales_order
*
* creates a new sales order
* $order is an array formatted for zoho containing the order.
* $confirm confirms the order automatically.  If false order is left as draft.
* 
* Returns the created salesorder array from zoho or wp_error
****/	

	public function create_salesorder ($order, $confirm=false) {
	
		if (! is_array($order) ) return false;
		
		$request = 'salesorders';
		if (isset ($order['salesorder_number'])) $request .= '?ignore_auto_number_generation=true'; 
		
		
		$response = $this->post_books ($request, $order);
		if (is_wp_error ($response)) {
			$response->add('bbz-zc-024', 'Zoho create_salesorder failed', array(
				'order'=>$order));
			return $response;
		}
		
		if (isset ($response['salesorder']['salesorder_id'])) {
			// now confirm the salesorder
			$confirmed = $this->salesorder_confirm ($response['salesorder']['salesorder_id']);
			if (is_wp_error ($confirmed) ) {
				$confirmed->add ('bbz-zc-025', 'Zoho create_salesorder  confirm failed', array(
					'order'=>$order,
					'salesorder'=>$response));
				return $confirmed;
			}
			//success!
			return $response ['salesorder'];
		} else {
			return new WP_Error ('bbz-zc-026', 'Zoho create_salesorder failed', array(
				'order'=>$order,
				'response'=>$response));
		}	
	}	
	
	/****
	* salesorder_confirm
	*
	* Change sales order status to confirmed
	*****/
	public function salesorder_confirm ($order_id) {

		$request = 'salesorders/'.$order_id.'/status/confirmed';
		$response = $this->post_books ($request);
		if (is_wp_error ($response)) {
			$response->add('bbz-zc-027', 'Zoho salesorder_confirm failed', array(
				'order_id'=>$order_id));
		}
		return $response;
	}
	
	/****
	* salesorder_addcomment
	*
	* Adds a comment in the sales order history
	*****/
	
	public function salesorder_addcomment ($order_id, $comment) {

		$content = array('description' => $comment);
		$request = 'salesorders/'.$order_id.'/comments';
		//bbz_debug (array($request, $comment), 'Add Comment', false);
		$response = $this->post_books ($request, $content);
		// bbz_debug ($response, 'Confirmed?');
		if (is_wp_error ($response)) {
			$response->add('bbz-zc-028', 'Zoho salesorder_addcomment failed', array(
				'order_id'=>$order_id,
				'comment'=>$comment));
		}
		return $response;

	}

/*****
* create_invoice
*
* creates a new invoice
* $invoice is an array formatted for zoho containing the invoice.
* $confirm confirms the invoice automatically.  If false, invoice is left as draft.

* Returns the zoho invoice array or wp_error
****/	

	public function create_invoice ($invoice, $confirm=false) {

		$request = 'invoices';
		if (isset ($invoice['invoice_number'])) $request .= '?ignore_auto_number_generation=true'; 
		
		$response = $this->post_books ($request, $invoice);
		if (is_wp_error ($response)) {
			$response->add('bbz-zc-029', 'Zoho create_invoice failed', array(
				'invoice'=>$invoice));
		}
		
		//bbz_debug ($zoho_data, 'create_sales_order result', false);
		
		if (isset ($response['invoice']['invoice_id'])) {
			//now confirm the invoice
			$confirmed = $this->invoice_confirm ($response['invoice']['invoice_id']);
			if (is_wp_error ($confirmed) ) {
				$confirmed->add ('bbz-zc-030', 'Zoho create_invoice confirm failed', array(
					'invoice'=>$invoice,
					'zoho data'=>$response));
				return $confirmed;
			}
			// success!
			return $response['invoice'];
		} else {
			return new WP_Error ('bbz-zc-031', 'Zoho create_invoice failed', array(
				'invoice'=>$invoice,
				'response'=>$response));
		}

	}
	/****
	* invoice_confirm
	*
	* Change invoice status to sent
	*****/
	public function invoice_confirm ($invoice_id) {

		if (! $this->isconnected() ) return false;
		
		$request = 'invoices/'.$invoice_id.'/status/sent';
		$response = $this->post_books ($request);
		if (is_wp_error ($response)) {
			$response->add('bbz-zc-032', 'Zoho invoice_confirm failed', array(
				'invoice_id'=>$invoice_id));
		}
		return $response;
	}
	
	/****
	* create_payment
	*
	* Record a payment
	****/
	public function create_payment ($payment) {

		$request = 'customerpayments';
		
		$response = $this->post_books ($request, $payment);
		if (is_wp_error ($response)) {
			$response->add('bbz-zc-033', 'Zoho create_payment failed', array(
				'payment'=>$payment));
			return $response;
		}
		
		//bbz_debug (array($payment, $zoho_data), 'create_payment result', false);
		if (isset ($response['payment'])) {
			return $response['payment'];
		} else {
			return new WP_Error ('bbz-zc-034', 'Zoho create_payment failed', array(
				'payment'=>$payment,
				'response'=>$response));
		}
	}

	
} //class
?>