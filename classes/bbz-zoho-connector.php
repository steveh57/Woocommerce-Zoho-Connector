<?php
/**
 * Creates the Zoho connector class
 *
 */http://localhost/bbtest/wp-admin/admin-post.php
 
 // If this file is called directly, abort.
 
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}
 
 class zoho_connector extends zoho_core {
 
	public function __construct()
    {
        // call Core constructor
        parent::__construct();
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
			'availability'	=> 'cf_inactive_reason_unformatted',
			'length'	=> 'length',
			'width'		=> 'width',
			'height'	=> 'height',
			'weight'	=> 'weight',
			'weight_unit' => 'weight_unit',	// might be g or kg
			'dimension_unit' => 'dimension_unit', //usually cm
			'product_type'	=> 'product_type',	//goods or services
			'item_type'	=> 'cf_item_type_unformatted',
			'author'	=> 'cf_author_unformatted',
			'release_date' => 'cf_release_date_unformatted',
			'trade_discount' => 'cf_trade_discount_unformatted'			
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
		if (is_wp_error ($response)) {
			$response->add('bbz-zcon-003', 'Zoho get_customer_emails failed');
			return $response;
		} else {
			if ( is_array ($response) && is_array ($response['data'])) {
				$email_list = array();
				foreach ($response['data'] as $zoho_contact) {
					if (! empty ($zoho_contact['email'])  && empty ($email_list[$zoho_contact['email']] ) 
						&& is_numeric ($zoho_contact['customer_id'] )) {
						$email_list[] = $zoho_contact;
					}
				}

			}
			return $email_list;				

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
	}
	
/*****
* get_id_from_email
*
* Uses the email list to validate a given email
* Returns the zoho id or false
*****/
	public function get_id_from_email ($email='') {
		if (empty($email) ) return false;
		
	
		$email_list = $this->get_customer_emails ();
		
		if ( is_wp_error($email_list)) return false;
		
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
		
		// Zoho returns data in pages of 200 by default
		$more_pages = true;
		$next_page = 1;
		$name_list = array();

		
		while ($more_pages) {
			$filter = array (
				'page'			=> $next_page,
				'contact_type'	=> 'customer',
			);
			// fetch item data from Zoho
			$zoho_data = $this->get_books ('contacts', $filter);
			if (is_wp_error($zoho_data)) return $zoho_data;
			
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
			$zoho_data->add ('bbz-zcon-004', 'get_contact_address failed', $zoho_data);
			return $zoho_data;
		}
		
		if (isset ($zoho_data['addresses'])) {
			return $zoho_data['addresses'];
		} else {
			return new WP_Error ('bbz-zcon-005', 'get_contact_address failed', $zoho_data);
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
			$zoho_data->add ('bbz-zcon-007', 'get_contact_by_id failed');
			return $zoho_data;
		}
		
		if (isset ($zoho_data['contact'])) {
			return $zoho_data['contact']; 
		} else {
			return new WP_Error ('bbz-zcon-008', 'get_contact_by_id failed', $zoho_data);
		}

	}
/*****
* create_contact
*
* creates a new contact
* $contact is an array formatted for zoho containing the contact details.
* 
* Returns the created salesorder array from zoho or wp_error
****/	

	public function create_contact ($contact) {
	
		if (! is_array($contact) ) return false;
		
		$response = $this->post_books ('contacts', $contact);
		if (is_wp_error ($response)) {
			$response->add('bbz-zcon-009', 'Zoho create_contact failed', array(
				'contact'=>$contact));
			return $response;
		}
		//success!
		return $response ['contact'];
	}	
/*********
* get_sales_history
*
* Returns the output of the Zoho Analytics query 'BBZ Sales History' as an
* array of rows, where each row is an associative array of '<column name>'=>value
*
********/

	public function get_sales_history () {
	
		$response = $this->get_analytics ('BBZ Sales History');
		if (is_wp_error ($response)) {
			$response->add('bbz-zcon-010', 'Zoho get_sales_history failed');
			return $response;
		} elseif ( is_array ($response) && is_array ($response['data'])) {
			return $response['data'];
		} else {
			return new WP_Error ('bbz-zcon-011', 'Zoho get_sales_history failed', array(
				'response'=>$response));
		}
	}
	
/*****
* add_address
*
* adds a new address for specified customer.
* fields must be in correct format for zoho
****/	

	public function add_address ($contact_id, $address) {

		$response = $this->post_books ('contacts/'.$contact_id.'/address', $address);
		if (is_wp_error ($response)) {
			$response->add('bbz-zcon-020', 'Zoho add_address failed', array(
				'contact_id'=>$contact_id,
				'address'=>$address));
			return $response;
		}
		//bbz_debug ($zoho_data, 'zoho add address');
		if (isset ($response['address_info'])) {
			return $response['address_info'];
		} else {
			return new WP_Error ('bbz-zcon-021', 'Zoho add_address failed', array(
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

	public function update_address ($contact_id, $address, $address_id) {
		//bbz_debug (array ($contact_id, $address_id, $address), 'In zoho update_address', false);
	
		if ($contact_id == '') return false;
		$url = 'contacts/'.$contact_id.'/address/'.$address_id;
		$response = $this->put_books ($url, $address);
		if (is_wp_error ($response)) {
			$response->add('bbz-zcon-022', 'Zoho update_address failed', array(
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
			return new WP_Error ('bbz-zcon-023', 'Zoho update_address failed', array(
				'contact_id'=>$contact_id,
				'address'=>$address,
				'address_id'=>$address_id,
				'response'=>$response));
		}	
	
	}	

/*****
* delete_address
*
* deletes an address for specified customer, but does not remove it from the database
* fields must be in correct format for zoho
****/	

	public function delete_address ($contact_id, $address_id) {

		$response = $this->delete_books ('contacts/'.$contact_id.'/address/'.$address_id);
		if (is_wp_error ($response)) {
			$response->add('bbz-zcon-120', 'Zoho delete_address failed', array(
				'contact_id'=>$contact_id,
				'address'=>$address_id));
			return $response;
		}
		//bbz_debug ($zoho_data, 'zoho add address');
		return true;
	
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

	public function create_salesorder ($order, $confirm=true) {
	
		if (! is_array($order) ) return false;
		
		$request = 'salesorders';
		if (isset ($order['salesorder_number'])) $request .= '?ignore_auto_number_generation=true'; 
		
		
		$response = $this->post_books ($request, $order);
		if (is_wp_error ($response)) {
			$response->add('bbz-zcon-024', 'Zoho create_salesorder failed', array(
				'order'=>$order));
			return $response;
		}
		if (!$confirm) return $response;
		
		if (isset ($response['salesorder']['salesorder_id'])) {
			// now confirm the salesorder
			$confirmed = $this->salesorder_confirm ($response['salesorder']['salesorder_id']);
			if (is_wp_error ($confirmed) ) {
				$confirmed->add ('bbz-zcon-025', 'Zoho create_salesorder  confirm failed', array(
					'order'=>$order,
					'salesorder'=>$response));
				return $confirmed;
			}
			//success!
			return $response ['salesorder'];
		} else {
			return new WP_Error ('bbz-zcon-026', 'Zoho create_salesorder failed', array(
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
			$response->add('bbz-zcon-027', 'Zoho salesorder_confirm failed', array(
				'order_id'=>$order_id));
		}
		return $response;
	}
	
	/****
	* salesorder_substatus
	*
	* Change sales order sub-status
	*****/
	public function salesorder_substatus ($order_id, $substatus) {

		$request = 'salesorders/'.$order_id.'/substatus/'.$substatus;
		$response = $this->post_books ($request);
		if (is_wp_error ($response)) {
			$response->add('bbz-zcon-027A', 'Zoho salesorder_substatus failed', array(
				'order_id'=>$order_id,
				'substatus'=>$substatus));
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
			$response->add('bbz-zcon-028', 'Zoho salesorder_addcomment failed', array(
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

	public function create_invoice ($invoice, $action='', $contact_id='') {

		$request = 'invoices';
		if (isset ($invoice['invoice_number'])) $request .= '?ignore_auto_number_generation=true'; 
		
		$response = $this->post_books ($request, $invoice);
		if (is_wp_error ($response)) {
			$response->add('bbz-zcon-029', 'Zoho create_invoice failed', array(
				'invoice'=>$invoice));
				return $response;
		}
		
		//bbz_debug ($zoho_data, 'create_sales_order result', false);
		
		if (isset ($response['invoice']['invoice_id'])) {
			//now confirm the invoice
			if ($action == 'confirm') {
				$confirmed = $this->invoice_confirm ($response['invoice']['invoice_id']);
				if (is_wp_error ($confirmed) ) {
					$confirmed->add ('bbz-zcon-030', 'Zoho create_invoice confirm failed', array(
						'invoice'=>$invoice,
						'zoho data'=>$response));
					return $confirmed;
				}
			} /*elseif ($action == 'email') {
				$confirmed = $this->invoice_email ($response['invoice']['invoice_id'], $contact_id);
				if (is_wp_error ($confirmed) ) {
					$confirmed->add ('bbz-zcon-030', 'Zoho create_invoice confirm failed', array(
						'invoice'=>$invoice,
						'zoho data'=>$response));
					return $confirmed;
				}
			} */
			// success!
			return $response['invoice'];
		} else {
			return new WP_Error ('bbz-zcon-031', 'Zoho create_invoice failed', array(
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
			$response->add('bbz-zcon-032', 'Zoho invoice_confirm failed', array(
				'invoice_id'=>$invoice_id));
		}
		return $response;
	}
	
	/****
	* invoice_email - not working!!
	*
	* Email invoice to contact
	*
	*****

	public function invoice_email ($invoice_id, $contact_id) {

		if (! $this->isconnected() ) return false;
		
		$request = "invoices/$invoice_id/email";
		$response = $this->post_books ($request);
		if (is_wp_error ($response)) {
			$response->add('bbz-zcon-036', 'Zoho invoice_email failed', array(
				'invoice_id'=>$invoice_id));
		}
		return $response;
	}
	*/
	
	/****
	* create_payment
	*
	* Record a payment
	****/
	public function create_payment ($payment) {

		$request = 'customerpayments';
		
		$response = $this->post_books ($request, $payment);
		if (is_wp_error ($response)) {
			$response->add('bbz-zcon-033', 'Zoho create_payment failed', array(
				'payment'=>$payment));
			return $response;
		}
		
		//bbz_debug (array($payment, $zoho_data), 'create_payment result', false);
		if (isset ($response['payment'])) {
			return $response['payment'];
		} else {
			return new WP_Error ('bbz-zcon-034', 'Zoho create_payment failed', array(
				'payment'=>$payment,
				'response'=>$response));
		}
	}
	
	/*****
	* get_salesorder/get_salesorders
	*
	* @param $zoho_orderid 	Zoho order id to fetch.
	* @param $filter		Key=>value pairs passed to zoho request.
	*
	* If $zoho_orderid is not specified, the call will return an array of sales orders
	* selected according to the filter parameters.
	*
	* If a specific order id is given the call will return a comprehensive array for the sales
	* order including all line items, shipment packages and other related details.
	* 
	* Returns an array of shipment order summary:
	
	*
	*****/
	public function get_salesorder ( $zoho_orderid='', $filter=array()) {
		
		$request = 'salesorders'. (empty ($zoho_orderid) ? '' : '/'. $zoho_orderid);
		
		$response = $this->get_books ($request, $filter);
		if (is_wp_error ($response)) {
			$response->add('bbz-zcon-035', 'Zoho get_salesorder failed', array(
				'zoho order id'=>$zoho_orderid,
				'filter' => $filter));
			return $response;
		}
		if (isset ($response['salesorder'])) {
			return $response ['salesorder'];
		} elseif (isset ($response['salesorders'])) {
			return $response ['salesorders'];
		} else {
			return new WP_Error ('bbz-zcon-036', 'Zoho get_salesorder failed', array(
				'zoho order id'=>$zoho_orderid,
				'filter' => $filter,
				'response'=>$response));
		}
	}
	
	public function get_salesorders ($filter) {
		return $this->get_salesorder ('', $filter);
	}
	
/*********
* get_available_stock
*
* Returns the output of the Zoho Analytics query 'BBZ Available Stock' coverted to an associative array of 
*	'SKU' => 'quantity'
* Note that the stock quantities only get updated once a day, around midnight and are calculated as
* 	Accounting stock (i.e. stock invoiced, billed, credited or adjusted, allowing for warehouse transfers)
*	+ Unbilled stock received
*	- Orders not yet invoiced
*
********/

	public function get_available_stock () {
	
		$response = $this->get_analytics ('BBZ Available Stock');
		if (is_wp_error ($response)) {
			$response->add('bbz-zcon-040', 'Zoho get_available_stock failed');
			return $response;
		} elseif ( is_array ($response) && is_array ($response['data'])) {
			$stock = array();
			foreach ($response['data'] as $row) {
				$stock[$row['SKU']] = $row['Qty'];
			}
			return $stock;
		} else {
			return new WP_Error ('bbz-zcon-011', 'Zoho get_sales_history failed', array(
				'response'=>$response));
		}
	}
	
	
} //class
?>