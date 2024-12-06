<?php
/**
 * bbz_order Class
 *
 * Used to process a new order and submit to Zoho
 * Zoho order format - the only required field is the customer id
 *
  "customer_id": "460000000017138",
    "contact_persons": [
        "460000000870911",
        "460000000870915"
    ],
    "date": "2014-07-28",
    "custom_fields": [
        {
            "customfield_id": "460000000639129",
            "value": "Normal"
        }
    ],
    "place_of_supply": "TN",
    "salesperson_id": "460000000000097",
    "merchant_id": "460000000000597",
    "gst_treatment": "business_gst",
    "gst_no": "22AAAAA0000A1Z5",
    "is_inclusive_tax": false,
    "line_items": [
        {
            "item_order": 0,
            "item_id": "460000000017088",
            "rate": 120,
            "name": "Hard Drive",
            "description": "500GB, USB 2.0 interface 1400 rpm, protective hard case.",
            "quantity": 40,
            "product_type": "goods",
            "hsn_or_sac": 80540,
            "warehouse_id": "460000000041001",
            "warehouse_name": "Walmart",
            "tax_id": "460000000017094",
            "tags": [
                {
                    "tag_id": 462000000009070,
                    "tag_option_id": 462000000002670
                }
            ],
            "unit": "Nos",
            "item_custom_fields": [
                {
                    "customfield_id": "460000000639129",
                    "value": "Normal"
                }
            ],
            "tax_treatment_code": "uae_others",
            "project_id": 90300000087378
        }
    ],
    "billing_address_id": 460000000032174,
    "tax_treatment": "vat_registered",
    "salesorder_number": "SO-00001",
    "reference_number": "REF-001",
    "is_update_customer": false,
    "exchange_rate": 1.233,
    "salesperson_name": "John Roberts",
    "tax_id": "460000000017094",
    "shipping_charge": 2,
    "adjustment": 0.2,
    "delivery_method": "Air",
    "is_discount_before_tax": true,
    "discount_type": "entity_level",
    "adjustment_description": "Adjustment",
    "template_id": "460000000021001",
    "documents": [
        "document_id",
        "file_name"
    ],
    "zcrm_potential_id": "460000000033001"
}"'
 
 *
 *****/
 // If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class bbz_order {
	
//	private $bbz_addresses = array ();
	protected $user_meta;
	protected $user_id;
	protected $order;
	protected $order_id;
	protected $options;
	protected $guest=false;
	protected $zoho_cust_id;
	protected $on_zoho=false;
	protected $zoho_order=array();
	protected $zoho_order_id='';
	
	function __construct ($order) {
		$this->order = wc_get_order( $order );
		
		// Check this is a shop order and not a refund
		if (!method_exists ($this->order, 'get_type') || $this->order->get_type() !== 'shop_order') {
			$this->order = NULL;
			return false;
		}
		$this->order_id = $this->order->get_id();
		$this->zoho_order_id = $this->order->get_meta (BBZ_PM_ZOHO_ORDER_ID);
		$this->on_zoho = !empty ($this->zoho_order_id);
		//bbz_debug ('on_zoho testing '.BBZ_PM_ZOHO_ORDER_ID, $this->on_zoho?'true':'false');
		$this->options = new bbz_options;
		// Three situations:
		// 1. Guest user - user not logged in, or web user not linked to zoho
		// 2. Registered unlinked user  - depending on setting of BBZ_AUTO_CREATE_CONTACT
		//    will either be treated as a guest or we create a new contact in zoho
		// 3. Registered linked user
		$this->user_id = $this->order->get_user_id ();
		if (!empty($this->user_id)) { 
			$this->user_meta = new bbz_usermeta ($this->user_id);
			$this->zoho_cust_id = $this->user_meta->get_zoho_id();
		}
		// Check if we are treating this order as a guest order or linking it to a user.
		if (empty($this->user_id) OR //User not logged in or
			// no zoho id and we're not auto creating new zoho contacts
			(BBZ_AUTO_CREATE_CONTACT === false AND empty($this->zoho_cust_id))) {
			$this->guest = true;
			$this->zoho_cust_id = $this->options->get (BBZ_OP_GUESTID);;
		}
		
	}
	
	public function is_on_zoho() {
		return $this->on_zoho;
	}
	
	public function get_zoho_order_id () {
		if (empty($this->order) || false == $this->on_zoho ) {
			return false;
		}
		return $this->zoho_order_id;
	}
	
	public function get_woo_order_id () {
		if (empty($this->order) ) {
			return false;
		}
		return $this->order_id;
	}
	
	public function get_date_created () {
		if (empty($this->order) || false == $this->on_zoho ) {
			return false;
		}
		return $this->order->get_date_created();
	}
		
	
	/******
	* process_new_order
	*
	* @param bool $resubmit Send order even if it already has a zoho id
	* @return mixed zoho order array or wc_error object
	*
	* Takes a woocommerce order and submits it to zoho.
	* If the order has been paid, an invoice and payment record are also created.
	******/
	
	public function process_new_order ($resubmit=false) {
		if (empty($this->order) ) {
			return new WP_Error ('bbz-ord-001', 'Invalid order parameter passed to process_new_order');
		}
		
		// check that order hasn't already been sent - can be called twice if user refreshes page
		if ($this->on_zoho  && !$resubmit) {
			return new WP_Error ('bbz-ord-002', 'Order has already been submitted to Zoho', array ('order'=>$this->order));
		}
		

		// Get the address id
		$bbz_addresses = new bbz_addresses ($this->user_id);
		$shipto = $this->order->get_address ('shipping');
		$shipto['email'] = $this->order->get_billing_email();
		$shipto['phone'] = $this->order->get_billing_phone();
		
		/****
		* May need to look at handling billing addresses:
		* if payment on account always use default zoho address
		* if paid by card/paypal use address from woo
		******/
		
		// Guest setting determined in construct
		if ($this->guest) {
			if (empty($this->zoho_cust_id)) {
				$error = new WP_Error ('bbz-ord-003', 'No guest customer linked');
				//$this->notify_admin ("Failed to create Zoho order", $error);
				return $error; // no guest customer linked - can't process order
			}
			$payment_terms = array (
				'name' => 'Pay with order',
				'days' => '0'
			);
			//bbz_debug ($this->zoho_cust_id, "Zoho Cust ID", false);
		} else { // logged in user
			if (empty($this->zoho_cust_id)) {
				// No Zoho contact linked, so create new contact
				$result = $this->create_zoho_contact();
				if (is_wp_error ($result)) {
					$result->add ('bbz-ord-004', 'Process_order failed', array (
						'order'=>$this->order) );
					//$this->notify_admin ("Failed to create Zoho order", $result);
					return $result;
				}
				$this->zoho_cust_id = $result ['contact_id'];
			}
			$payment_terms = $this->user_meta->get_payment_terms();
		}
		
		// now try and get zoho address id - new address record is created if necessary
		$zoho_address_id = $bbz_addresses->get_zoho_address_id ($shipto, 'shipping', $this->zoho_cust_id, $this->guest);
		
		// if unable to get address id, can't create order.  Can we email admin?
		if (is_wp_error ($zoho_address_id)) {
			$zoho_address_id->add('bbz-ord-005', 'Unable to get Zoho address id', array (
				'shipto'=> $shipto,
				'bbz_addresses'=>$bbz_addresses));
			//$this->notify_admin ("Failed to create Zoho order", $zoho_address_id);
			return $zoho_address_id;
		}
		
		// now create the sales order
		$response = $this->create_zoho_order ($zoho_address_id, $payment_terms);
		if (is_wp_error($response) ) {
			$response->add ('bbz-ord-006', 'Unable to create Zoho order', array(
				"Zoho Customer ID"=>$this->zoho_cust_id,
				"Zoho Address ID"=>$zoho_address_id));
			//$this->notify_admin ("Failed to create Zoho order", $response);
			return $response;
		}
		$this->zoho_order = $response;
		$this->zoho_order_id = $this->zoho_order['salesorder_id'];
		//bbz_debug ($zoho_order, 'Order created', false);
		//update_post_meta ($this->order->get_order_number(), 'zoho_order_id', $this->zoho_order_id);
		
		$this->order->update_meta_data (BBZ_PM_ZOHO_ORDER_ID, $this->zoho_order_id);  //use update just in case there's an existing order_id
		$this->on_zoho = true;
		$this->order->save();
		
		// if order is paid (excluding on account), create an invoice and payment record.
		$zoho = new zoho_connector;		
		if ($this->order->is_paid() && $this->order->get_payment_method() !== BBZ_PAYMENT_METHOD_ACCOUNT) {
			$payment_details = "Paid ".$this->order->get_date_paid().' '.
				$this->order->get_payment_method_title().' '.$this->order->get_transaction_id();
			$zoho->salesorder_addcomment ($this->zoho_order_id, $payment_details);
			//bbz_debug ($result, 'Add Comment', false);
			$zoho->salesorder_confirm ($this->zoho_order_id);
			
			// create an invoice and payment record.
			$response = $this->create_zoho_invoice ($this->zoho_order);
			//bbz_debug ($zoho_invoice, 'Invoice created', false);
			if (is_wp_error ($response) ) {
				$response->add ('bbz-ord-007', 'Unable to create Zoho invoice', array(
					"Zoho Order"=>$this->zoho_order));
				//$this->notify_admin ("Failed to create Zoho invoice for order", $response);
				return $response;
			}
			$zoho_invoice = $response;
			$response = $this->create_zoho_payment ($zoho_invoice);
			if (is_wp_error ($response) ) {
				$response->add ('bbz-ord-008', 'Unable to create Zoho payment', array(
					"Zoho invoice"=>$zoho_invoice));
				//$this->notify_admin ("Failed to create Zoho payment for order", $response);
				return $response;
			}
		
			// If customer is using the guest account, delete the address from the account to clean up
			if ($this->guest) {
				$response = $bbz_addresses->delete_address ($this->zoho_cust_id, $zoho_address_id);
				if (is_wp_error ($response) ) {
					$response->add ('bbz-ord-009', 'Unable to delete Zoho guest address',
						array (	'order'=>$this->order->get_order_number())	);
					//$this->notify_admin ("Failed to delete Zoho guest address", $response);
					return $response;
				}
			}
				//bbz_debug ($zoho_payment, 'Payment created');

		} else {  // order not paid
			// if user is wholesale customer confirm anyway
			if (bbz_is_wholesale_customer ($this->user_id)) {
				$zoho->salesorder_confirm ($this->zoho_order_id);
			}
		}
		
		return $this->zoho_order;
	}

	/*****
	* create_zoho_contact
	*
	* Creates a zoho contact for a retail customer
	*
	******/
	private function create_zoho_contact () {
	
		$bbz_addresses = new bbz_addresses();
		
		// Build contact array
		$contact = array();
		$billing = $this->order->get_address('billing');
		$shipping = $this->order->get_address('shipping');
		$contact['contact_name'] = $billing['first_name'].' '.$billing['last_name'];
		$contact['contact_type'] = 'customer';
		$contact['customer_sub_type'] = 'individual';
		$contact['payment_terms'] = '0';
		$contact['payment_terms_label'] = 'Pay with order';
		$contact['billing_address'] = $bbz_addresses->woo_to_zoho ($billing);
		$contact['shipping_address'] = $bbz_addresses->woo_to_zoho ($shipping);
		$contact['contact_persons'] = array(array(
			'first_name' => $billing['first_name'],
			'last_name' => $billing['last_name'],
			'email' => $billing['email'],
			'phone'=> $billing['phone'],
			'is_primary_contact' => 'true',
			));

		//bbz_debug ($contact, 'Contact array before sending', false);
		$zoho = new zoho_connector;
		$result = $zoho->create_contact ($contact);
		if (is_wp_error($result)) {
			$result->add ('bbz-ord-010', 'bbz_order->create_contact failed', array (
				'user_id'=>$this->user_id
				) );
			return $result;
		}

		// save zoho address and contact ids in usermeta
		$this->user_meta->load_zoho_id ($result['contact_id']);
		$this->user_meta->update_zoho_address_id ('billing', $result['billing_address']['address_id']);
		$this->user_meta->update_zoho_address_id ('shipping', $result['shipping_address']['address_id']);
		$this->user_meta->load_payment_terms ($result); 
		return $result;
	}

	/*****
	* create_zoho_order
	*
	* Creates the sales order in zoho
	*
	* TODO: & ampersand in reference field gets rejected by Zoho
	*
	******/
	
	private function create_zoho_order ($zoho_address_id, $payment_terms) {
		$zoho_order = array();
		$zoho_order ['customer_id'] = $this->zoho_cust_id;
		$zoho_order ['shipping_address_id'] = $zoho_address_id;
		$zoho_order ['salesorder_number'] = ZOHO_SALESORDER_PREFIX.$this->order->get_order_number();
		if (!$this->order->is_paid() && $this->order->get_payment_method() !== 'account') {
			$zoho_order ['reference_number'] = "HOLD - NOT PAID";
		} else {			
			$zoho_order ['reference_number'] = $this->order->get_shipping_last_name();
		}
		$zoho_order ['custom_fields'][] = array (
				'customfield_id'	=> '1504573000002888095', // Order source
				'value'				=> 'Website',
			);
		$zoho_order ['shipping_charge'] = $this->order->get_shipping_total();
		$discount = $this->order->get_discount_total();
		if ($discount > 0) {
			$zoho_order ['discount'] = strval($discount);
			$zoho_order ['discount_type'] = 'entity_level';
		}
		$zoho_order ['payment_terms_label'] = $payment_terms ['name'];
		$zoho_order ['payment_terms'] = $payment_terms ['days'];
		$notes = $this->order->get_customer_note();
		if (empty ($notes)) {
			$zoho_order ['notes_default'] = true;
		} else {
			$zoho_order ['notes'] = bbz_zoho_clean ($notes);
			// alert order processing to presence of customer notes
			$zoho_order ['reference_number'] .= ' SEE NOTES';
		}
		$zoho_order ['delivery_method'] = $this->order->get_shipping_method();
		// Flag priority orders with PRIORITY in the ref field
		if (false !== stristr($zoho_order ['delivery_method'], 'priority')) {
			$zoho_order ['reference_number'] = 'PRIORITY '.$zoho_order ['reference_number'];
		} elseif (false !== stristr($zoho_order ['delivery_method'], '1st')) {
			$zoho_order ['reference_number'] = 'RM24 '.$zoho_order ['reference_number'];
		}
		$zoho_order['terms_default'] = true;
		
		// Iterate Through Items
		foreach ( $this->order->get_items() as $item_id=>$item ) {
			$zoho_line = array();
			// Do we need the product id or the variation id?
			$variation_id = $item->get_variation_id();
			$post_id = $variation_id == 0 ? $item->get_product_id() : $variation_id;
			$zoho_line ['item_id'] = get_post_meta ($post_id, BBZ_PM_ZOHO_ID, true);
			
			$zoho_line ['quantity'] = $item->get_quantity();
			$zoho_line ['rate'] = $item->get_subtotal() / $zoho_line ['quantity'];
			if ($zoho_line ['item_id'] !== false) {
				$zoho_order ['line_items'][] = $zoho_line;
			}
		}

		//bbz_debug ($zoho_order, 'Order array before sending');
		$zoho = new zoho_connector;
		return $zoho->create_salesorder ($zoho_order);

	}
	
	private function create_zoho_invoice ($zoho_order) {
		$salesorder_fields_to_copy = array(
			'customer_id',
			'shipping_address_id',
			'billing_address_id',
			//'reference_number',
			'vat_treatment',
			'contact_persons',
			'payment_terms',
			'payment_terms_label',
			'discount',
			'is_discount_before_tax',
			'discount_type',
			'is_inclusive_tax',
			'exchange_rate',
			'salesperson_name',
			'shipping_charge',
			'adjustment',
			'adjustment_description',
		);
		$lineitems_fields_to_copy = array (
			'item_id',
			'rate',
			'quantity',
			'discount',
			'tax_id',
		);

		$zoho_invoice = array();
		foreach ($salesorder_fields_to_copy as $field_name) {
			if (! empty ($zoho_order [$field_name])) {
				$zoho_invoice [$field_name] = $zoho_order [$field_name];
			}
		}
		$zoho_invoice ['reference_number'] = $zoho_order ['salesorder_number'];
		foreach ($zoho_order ['line_items'] as $line_no=>$line_item) {
			foreach ($lineitems_fields_to_copy as $field_name) {
				$zoho_invoice ['line_items'][$line_no][$field_name] = $line_item[$field_name];
			}
			$zoho_invoice ['line_items'][$line_no]['salesorder_item_id']= $line_item['line_item_id'];
		}
		// Check total matches
		$adjustment = $this->order->get_total() - $zoho_order ['total'];
		if ( $adjustment >= 0.5 ) {  // account for any rounding errors
			$zoho_invoice ['adjustment'] = $adjustment;
			$zoho_invoice ['adjustment_description'] = 'Rounding adjustment';
		};
		

		//bbz_debug ($zoho_invoice, 'Invoice array before sending', false);
		$zoho = new zoho_connector;
		$zoho_invoice = $zoho->create_invoice ($zoho_invoice, $confirm=true);
		if (!is_wp_error ($zoho_invoice)) {
			$this->order->add_meta_data ('zoho_invoice_id', $zoho_invoice['invoice_id']);
		}
		return $zoho_invoice;

	}
	
	private function create_zoho_payment ($zoho_invoice) {
		$zoho_payment = array();
		$zoho_payment ['customer_id'] = $zoho_invoice ['customer_id'];
		$zoho_payment ['amount'] = $this->order->get_total();
		$zoho_payment ['date'] = substr ($this->order->get_date_paid(), 0, 10);
		$zoho_payment ['invoices'][] = array(
			'invoice_id' => $zoho_invoice ['invoice_id'],
			'amount_applied' => $this->order->get_total()
		);

		if (stristr ($this->order->get_payment_method(), BBZ_PAYMENT_METHOD_PAYPAL)) {
			$zoho_payment ['payment_mode'] = 'Paypal';
			$zoho_payment ['account_id'] = ZOHO_PAYPAL_ACCOUNT_ID;
			$zoho_payment ['bank_charges'] = $this->order->get_meta ('_paypal_transaction_fee', true);
			$zoho_payment ['description'] = 'Paid by '.$this->order->get_meta ('Payer PayPal address', true);
		} elseif (stristr ($this->order->get_payment_method(), BBZ_PAYMENT_METHOD_STRIPE)) {
			$zoho_payment ['payment_mode'] = 'Stripe';
			$zoho_payment ['account_id'] = ZOHO_STRIPE_ACCOUNT_ID;
		} else return new WP_Error ('bbz-ord-021', 'Unrecognised payment method', array ('order'=>$this->order));
		
		$zoho_payment ['reference_number'] = $zoho_invoice ['reference_number'];

		//bbz_debug ($zoho_payment, 'Payment array before sending', false);
		
		$zoho = new zoho_connector;
		$zoho_payment = $zoho->create_payment ($zoho_payment);
		if (!is_wp_error ($zoho_payment)) {
			$this->order->add_meta_data ('zoho_payment_id', $zoho_payment ['payment_id']);
		}
		return $zoho_payment;
		
	}
	
	public function update_order_status () {
		// get zoho status for order

		if (empty($this->order) ) {
			$response = new WP_Error ('bbz-ord-200', 'Invalid order object for update_order_status');
			//$this->notify_admin ("Failed to update status for order", $response);
			return $response;
		}
//		$zoho_order_id = $this->get_zoho_order_id();
		
		// check that order already been sent
		if (empty($this->zoho_order_id)) {
			$response = new WP_Error ('bbz-ord-201', 'update_order_status Order not yet submitted to Zoho', array ('order'=>$this->order));
			//$this->notify_admin ("Failed to update status for order", $response);
			return $response;
		}
		
		// get salesorder record from zoho (we may already have it)
		if (empty($this->zoho_order)) {
			$zoho = new zoho_connector;
			$response = $zoho->get_salesorder ($this->zoho_order_id);
			if (is_wp_error ($response) ) {
				$response->add ('bbz-ord-202', 'update_order_status get_salesorder failed', array ('order'=>$this->order));
				return $response;
			}
			$this->zoho_order = $response;
		}
		
		// if zoho has marked the order as delivered, update the status here and do nothing else
		// this deals with old orders, orders delivered manually and orders that haven't been tracked
		bbz_debug ($this->order_id, 'Status ' . $this->zoho_order ['shipped_status']);
		if ('fulfilled' == $this->zoho_order ['shipped_status']) {
			$this->order->update_status ('delivered', 'Update from Zoho:');
			
		} elseif (in_array($this->zoho_order ['shipped_status'], array('shipped', 'partially_shipped'))) {  // order shipped
		
			$bbz_shipments = new bbz_shipments ($this->order_id);
			$bbz_shipments->load_packages($this->zoho_order);
		
			$this->order->save();
		}
	
		return true;
	}
	
	/*private function notify_admin ($message, $error) {
		$subject = $message ." #".$this->order->get_order_number();
		$message .= " Error details follow:\n";
		if (is_wp_error ($error) ) {
			$codes = $error->get_error_codes();
			foreach ($codes as $error_code) {
				$message .= 'Error: '.$error_code.' -> '.$error->get_error_message ($error_code)."\n";
				$message .= 'Error data: <pre>'.print_r ($error->get_error_data ($error_code), true)."</pre>\n";
			}
		}	
		$message .= "\nOrder Data\n<pre>".print_r ($this->order->get_data(), true).'</pre>';
		bbz_email_admin ($subject, $message);
	}
	*/	
		

}
/******
* CLASS bbz_order_from_zoho
*
* Called with a zoho salesorder as the parameter and used to create a dummy woo order
* in order to track shipments.
*
*******/

class bbz_order_from_zoho extends bbz_order {

	function __construct ($zoho_order, $wp_user_id = null) {
	
		$this->zoho_order = $zoho_order;
		$this->zoho_order_id = $zoho_order['salesorder_id'];
		
		$this->order = wc_create_order();
		//$this->order->add_order_note('Creating order for Zoho order '.$zoho_order['salesorder_number']);
		
		$address = bbz_addresses::zoho_address_to_woo( $zoho_order['shipping_address']);
		$this->order->set_address( $address, 'shipping' );
		//$address = bbz_addresses::zoho_address_to_woo( $zoho_order['billing_address']);
		if (!empty($wp_user_id)) {
			$this->order->set_customer_id($wp_user_id);
			$user = get_userdata($wp_user_id);
			$customer_name = $user->data->display_name;
		} else {
			$customer_name = $zoho_order['customer_name'];
		}
			
		$split_name = str_word_count($customer_name,1); //splits name into words
		if (!empty($split_name[0])) {
			$address['first_name'] = $split_name[0];
			if (!empty($split_name[1])) {
				$address['last_name'] = $split_name[1];
			}
		}
		$this->order->set_address( $address, 'billing' );
		$this->order->set_date_created ( $zoho_order ['date']);
		$this->order->set_total( $zoho_order['total'] );
		$this->order->set_status( 'wc-processing', 'Order created from Zoho' );  // this will get updated later by order_update
		$this->order->set_transaction_id ('ZOHO-'.$this->zoho_order_id);  //used to identify this as a zoho created order and block emails
		$this->order->update_meta_data (BBZ_PM_ZOHO_ORDER_ID, $this->zoho_order_id);
		$this->order->save ();
		// set up the local variables to be compatible
		$this->order_id = $this->order->get_id();
		$this->on_zoho = true;
		$this->guest = true;
		$this->options = new bbz_options;
		$this->zoho_cust_id = $this->options->get (BBZ_OP_GUESTID);;
	
	}

}