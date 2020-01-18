<?php
/**
 * Creates the Zoho connector class
 *
 */
 // If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}
 
include_once ( dirname( __FILE__ ) . '/bbz-definitions.php');

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
	* get_books
	*
	* call to zoho books api
	* returns response without any processing
	****/
	
	public function get_books ($request, $filter=array()) {
		if (! $this->isconnected() ) return false;
		
		return $this->_get_data (ZOHO_BOOKS_API_URL, $request, $filter);
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
			'isbn' 		=> 'isbn',			//isbn used as sku
			'zoho_id'	=> 'item_id',		// zoho item id
			'status'	=> 'status',		// status active or inactive
			'name'		=> 'name',			// name of product
			'rrp'		=> 'cf_rrp_unformatted',	// Recommended retail price
			'orp'		=> 'cf_orp_unformatted',	// original retail price
			'wsp'		=> 'rate',					// wholesale selling price
			'stock'		=> 'available_stock',		// current stock level
			'tax_class'	=> 'tax_name',			// tax class
			'shipping_class'	=>	'cf_shipping_class_unformatted',	// shipping class code
			
		);
		if (! $this->isconnected() ) return false;
		
		// Zoho returns data in pages of 200 by default
		$more_pages = true;
		$next_page = 1;
		$items = array ();
		
		while ($more_pages) {
			$filter = array ('page'=>$next_page);
			// fetch item data from Zoho
			$response = $this->get_books ('items', $filter);
			if (!is_array($response)) break;
			$zoho_data = json_decode($response['body'], true);
			if (isset ($zoho_data['page_context'])) {
				$more_pages = $zoho_data['page_context']['has_more_page'];
				$next_page = $zoho_data['page_context']['page'] + 1;
			} else {
				$more_pages = false;
			}
			if (!isset ($zoho_data['items'])) break;
			
			foreach ($zoho_data['items'] as $zoho_item) {
				$sku = $zoho_item['isbn'];
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
	* GET MISSING ITEMS
	*
	* get list of items not present in woocommerce
	* returns array isbn => name
	*****/
	public function get_missing_items() {
		$items = $this->get_items();
		if (is_array($items)) {
			// get list of product posts
			$args = array (
				'post_type' => 'product',	// only get product posts
				'numberposts' => -1,		// get all of them
			);
			$products = get_posts ( $args);
			$index = array();
			
			foreach ( $products as $product ) {
				$sku = get_post_meta ($product->ID, '_sku', $single=true);	// sku is in meta data
				if ( !('' == $sku) ) {
					$index [$sku] = $product->ID;
				}
			}
			
			foreach ($items as $item) {
				if (!isset ($index[$item['isbn']]) && $item['status']=='active') {
					$results [$item['isbn']] = $item['name'];
				}
			}
			return $results;
		} else {
			return false;
		}
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
* returns an an array of valid customer email addresses
****/
	
	public function get_customer_emails () {
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
		return $email_list;				
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
		$address_map = array ( // zoho_field_name => internal field name
			"address_id" => 'address_id',
            "attention" => 'attention',
            "address" => 'address1',
            "street2" =>	'address2',
            "city" => 'city',
            "state" => 'state',
            "zip" => 'postcode',
            "country" => 'country',
            "phone"=> 'phone',
		);

		if (! $this->isconnected() ) return false;
		
		if ($contact_id == '') return false;
		
//		$filter = array ('email'=>$email);
//		$request = 'contacts/'.$contact_id.'/address';
		$response = $this->get_books ('contacts/'.$contact_id.'/address');
		if (!is_array($response)) return false;
		
		$zoho_data = json_decode($response['body'], true);
		if (isset ($zoho_data['addresses'])) {
			foreach ($zoho_data['addresses'] as $address_id => $zoho_address) {
				foreach ($field_map as $int_field_name => $zoho_field_name) {
					if (isset ($zoho_address[$zoho_field_name])) {
						$result [$address_id][$int_field_name] = $zoho_address[$zoho_field_name];
					} else {
						$result [$address_id][$int_field_name] = ''; // default to empty string
					}
				}
			}
			return $result;
		} else {
			return false;
		}	
	
	}
/*****
* get_contact_by_email
* get_contact_by_id
*
* returns an an array of customer data with matching email
* Example data returned:
*		Array
*		(
*			[email] => steve@unilake.co.uk
*			[zoho_id] => 1504573000000078466
*			[status] => active
*			[company_name] => Unilake Ltd (Mace Coltishall)
*			[first_name] => Steve
*			[last_name] => Haines
*			[phone] => 01603 731234
*			[payment_terms] => Net 30
*			[billing address] => Array
*						(
*							[address_id] => 1504573000000078469
*							[attention] => Billing
*							[address1] => 99 Boxham Road
*							[address2] => Catishall
*							[city] => Norwich
*							[county] => Norfolk
*							[postcode] => NRxx 9xx
*							[country] => 
*							[phone] => 
*						)
*
*			[shipping_address] => Array
*						(
*							[address_id] => 1504573000000078471
*							[attention] => Shipping
*							[address1] => 99 Boxham Road
*							[address2] => Catishall
*							[city] => Norwich
*							[county] => Norfolk
*							[postcode] => NRxx 9xx
*							[country] => 
*							[phone] => 
*						)
*		)
*
****/
	
	public function get_contact_by_email ($email='') {
		if (empty($email) ) return false;
		
		$filter = array ('email'=>$email);
		if (! $this->isconnected() ) return false;
		
		$response = $this->get_books ('contacts', $filter);  // get contacts with matching email
		
		if (!is_array($response)) return false;
		
		$zoho_data = json_decode($response['body'], true);
		if (isset ($zoho_data['contacts'])) {
			$zoho_id = $zoho_data['contacts'][0]['contact_id'];
			// now get full contact record
			return $this->get_contact_by_id ($zoho_data['contacts'][0]['contact_id']);
		} else {
			return false;
		}

	}
	
	public function get_contact_by_id ($zoho_id='') {
		if (empty($zoho_id)) return false;
	
		$contact_map = array ( //  zoho_field_name => internal_field_name
			'email' 		=> 'email',			// email
			'contact_id'	=> 'zoho_id',		// zoho item id
			'status'	=> 'status',		// status active or inactive
			'company_name'		=> 'company',
			'first_name'		=> 'first_name',
			'last_name'		=> 'last_name',
			'phone'		=> 'phone',
			'payment_terms_label'	=> 'payment_terms',
		);
		$address_map = array ( // zoho_field_name => internal field name
			"address_id" => 'address_id',
            "attention" => 'attention',
            "address" => 'address1',
            "street2" =>	'address2',
            "city" => 'city',
            "state" => 'state',
            "zip" => 'postcode',
            "country" => 'country',
            "phone"=> 'phone',
		);

		if (! $this->isconnected() ) return false;

		$response = $this->get_books ('contacts/'.$zoho_id);
		
		if (!is_array($response)) return false;
		
		$zoho_data = json_decode($response['body'], true);
		if (isset ($zoho_data['contact'])) {
			$zoho_contact = $zoho_data['contact']; 
			foreach ($contact_map as $zoho_field_name => $int_field_name) {
				if (isset ($zoho_contact[$zoho_field_name])) {
					$result [$int_field_name] = $zoho_contact[$zoho_field_name];
				} else {
					$result [$int_field_name] = ''; // default to empty string
				}
			}
			// now get addresses
			if (isset ($zoho_contact ['billing_address'])){
				foreach ($address_map as $zoho_field_name => $int_field_name) {
					if (isset ($zoho_contact ['billing_address'][$zoho_field_name])) {
						$result ['billing_address'][$int_field_name] = $zoho_contact['billing_address'][$zoho_field_name];
					} else {
						$result ['billing_address'][$int_field_name] = ''; // default to empty string
					}
				}
			}
			if (isset ($zoho_contact ['shipping_address'])) {
				foreach ($address_map as $zoho_field_name => $int_field_name) {
					if (isset ($zoho_contact ['shipping_address'][$zoho_field_name])) {
						$result ['shipping_address'][$int_field_name] = $zoho_contact['shipping_address'][$zoho_field_name];
					} else {
						$result ['shipping_address'][$int_field_name] = ''; // default to empty string
					}
				}
			}

			return $result;
		} else {
			return false;
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

} //class
?>