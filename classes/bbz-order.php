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
	
	private $bbz_addresses = array ();
	private $user_meta;
	private $order;
	
	
	function __construct ($order) {
		$this->order = wc_get_order( $order );
		if (empty($this->order) ) {
			return false;
		}
	}
	
	public function get_zoho_order_id () {
		return $this->order->get_meta ('zoho_order_id', true);
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
			return new WP_Error ('bbz-ord-100', 'Invalid order parameter passed to process_new_order');
		}
		
		// check that order hasn't already been sent - can be called twice if user refreshes page
		if (!$resubmit && !empty($this->order->get_meta ('zoho_order_id', true))) {
			return new WP_Error ('bbz-ord-101', 'Order has already been submitted to Zoho', array ('order'=>$this->order));
		}
		
		$options = new bbz_options ();
		
		// First identify the type of user
		$user_id = $this->order->get_user_id ();
		//bbz_debug ($user_id, "User ID", false);
		if (empty($user_id)) { //guest
			$zoho_cust_id = false;
		} else {
			//user registered, but may not have zoho account
			$user_meta = new bbz_usermeta ($user_id);
			$zoho_cust_id = $user_meta->get_zoho_id();  //null if no zoho account, treat as guest
			// FUTURE: create a new customer in zoho for registered user
		}
		//bbz_debug ($zoho_cust_id, "Zoho Cust ID", false);
		
		// Get the address id
		$bbz_addresses = new bbz_addresses ($user_id);
		$shipto = $this->order->get_address ('shipping');
		/****
		* May need to look at handling billing addresses:
		* if payment on account always use default zoho address
		* if paid by card/paypal use address from woo
		******/
		
		if (empty($zoho_cust_id) ){  
			// treat logged in user without zoho id as guest
			$zoho_cust_id = $options->get ('guestuserid');
			if (empty($zoho_cust_id)) {
				$error = new WP_Error ('bbz-ord-001', 'No guest customer linked');
				$this->notify_admin ("Failed to create Zoho order", $error);
				return $error; // no guest customer linked - can't process order
			}
			$payment_terms = array (
				'name' => 'Pay with order',
				'days' => '0'
			);
			//bbz_debug ($zoho_cust_id, "Zoho Cust ID", false);
			// create new address for guest customer in zoho
			$zoho_address_id = $bbz_addresses->get_zoho_address_id ($shipto, 'shipping', $zoho_cust_id); // specifying cust id overrides default
		} else {
			$payment_terms = $user_meta->get_payment_terms();		
			$zoho_address_id = $bbz_addresses->get_zoho_address_id ($shipto, 'shipping');
			if (is_wp_error ($zoho_address_id)) {
				// check in case the order's using the billing address
				$zoho_address_id = $bbz_addresses->get_zoho_address_id ($shipto, 'billing');
				if (is_wp_error ($zoho_address_id)) {
					// and if that failed, we'll create a new address, although should already have been created by thwma
					$zoho_address_id = $bbz_addresses->get_zoho_address_id ($shipto, 'shipping', $zoho_cust_id);
				}
			}
		}
		// if unable to get address id, can't create order.  Can we email admin?
		if (is_wp_error ($zoho_address_id)) {
			$zoho_address_id->add('bbz-ord-002', 'Unable to get Zoho address id', array (
				'shipto'=> $shipto,
				'bbz_addresses'=>$bbz_addresses));
			$this->notify_admin ("Failed to create Zoho order", $zoho_address_id);
			return $zoho_address_id;
		}
		
		// now create the sales order
		$response = $this->create_zoho_order ($zoho_cust_id, $zoho_address_id, $payment_terms);
		if (is_wp_error($response) ) {
			$response->add ('bbz-ord-003', 'Unable to create Zoho order', array(
				"Zoho Customer ID"=>$zoho_cust_id,
				"Zoho Address ID"=>$zoho_address_id));
			$this->notify_admin ("Failed to create Zoho order", $response);
			return $response;
		}
		$zoho_order = $response;
		$zoho_order_id = $zoho_order['salesorder_id'];
		//bbz_debug ($zoho_order, 'Order created', false);
		//update_post_meta ($this->order->get_order_number(), 'zoho_order_id', $zoho_order_id);
		
		$this->order->add_meta_data ('zoho_order_id', $zoho_order_id);
		$this->order->save();
		
		// if order is paid (excluding on account), create an invoice and payment record.
		$zoho = new zoho_connector;		
		if ($this->order->is_paid() && $this->order->get_payment_method() !== 'account') {
			$payment_details = "Paid ".$this->order->get_date_paid().' '.
				$this->order->get_payment_method_title().' '.$this->order->get_transaction_id();
			$zoho->salesorder_addcomment ($zoho_order_id, $payment_details);
			//bbz_debug ($result, 'Add Comment', false);
			$zoho->salesorder_confirm ($zoho_order_id);
			
			// create an invoice and payment record.
			$response = $this->create_zoho_invoice ($zoho_order);
			//bbz_debug ($zoho_invoice, 'Invoice created', false);
			if (is_wp_error ($response) ) {
				$response->add ('bbz-ord-004', 'Unable to create Zoho invoice', array(
					"Zoho Order"=>$zoho_order));
				$this->notify_admin ("Failed to create Zoho invoice for order", $response);
				return $response;
			}
			$zoho_invoice = $response;
			$response = $this->create_zoho_payment ($zoho_invoice);
			if (is_wp_error ($response) ) {
				$response->add ('bbz-ord-005', 'Unable to create Zoho payment', array(
					"Zoho invoice"=>$zoho_invoice));
				$this->notify_admin ("Failed to create Zoho payment for order", $response);
				return $response;
			}

				//bbz_debug ($zoho_payment, 'Payment created');

		} else {
			// if user is wholesale customer confirm anyway
			if (bbz_is_wholesale_customer ($user_id)) {
				$zoho->salesorder_confirm ($zoho_order_id);
			}
		}
		
		return $zoho_order;
	}
	
	/*****
	* create_zoho_order
	*
	* Creates the sales order in zoho
	*
	******/
	
	private function create_zoho_order ($zoho_cust_id, $zoho_address_id, $payment_terms) {
		$zoho_order = array();
		$zoho_order ['customer_id'] = $zoho_cust_id;
		$zoho_order ['shipping_address_id'] = $zoho_address_id;
		$zoho_order ['salesorder_number'] = ZOHO_SALESORDER_PREFIX.$this->order->get_order_number();
		$zoho_order ['reference_number'] = $this->order->get_shipping_last_name();
		$zoho_order ['custom_fields'][] = array (
				'customfield_id'	=> '1504573000002888095', // Order source
				'value'				=> 'Website',
			);
		$zoho_order ['shipping_charge'] = $this->order->get_shipping_total();
		$zoho_order ['discount'] = $this->order->get_discount_total();
		$zoho_order ['payment_terms_label'] = $payment_terms ['name'];
		$zoho_order ['payment_terms'] = $payment_terms ['days'];
		$zoho_order ['notes'] = $this->order->get_customer_note();
		$zoho_order ['delivery_method'] = $this->order->get_shipping_method();
		if ($zoho_order ['discount'] == 0) unset ($zoho_order ['discount']);
		if (empty ($zoho_order ['notes']) ) {
			unset ($zoho_order ['notes']);
			$zoho_order ['notes_default'] = true;
		}
		$zoho_order['terms_default'] = true;
		
		// Iterate Through Items
		foreach ( $this->order->get_items() as $item_id=>$item ) {
			$zoho_line = array();
			$zoho_line ['item_id'] = get_post_meta ($item->get_product_id(), BBZ_PM_ZOHO_ID, true);
			$zoho_line ['quantity'] = $item->get_quantity();
			$zoho_line ['rate'] = $item->get_subtotal() / $zoho_line ['quantity'];
			if ($zoho_line ['item_id'] !== false) {
				$zoho_order ['line_items'][] = $zoho_line;
			}
		}

		//bbz_debug ($zoho_order, 'Order array before sending', false);
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
			$this->order->add_meta_data ('zoho_invoice_id', $zoho_order['invoice_id']);
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
		if (stristr ($this->order->get_payment_method(), 'paypal')) {
			$zoho_payment ['payment_mode'] = 'Paypal';
			$zoho_payment ['account_id'] = ZOHO_PAYPAL_ACCOUNT_ID;
			$zoho_payment ['bank_charges'] = $this->order->get_meta ('_paypal_transaction_fee', true);
			$zoho_payment ['description'] = 'Paid by '.$this->order->get_meta ('Payer PayPal address', true);
		} elseif (stristr ($this->order->get_payment_method(), 'stripe')) {
			$zoho_payment ['payment_mode'] = 'Stripe';
			$zoho_payment ['account_id'] = ZOHO_STRIPE_ACCOUNT_ID;
		} else return false;
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
		// if shipped, change woo order status to completed
		if (empty($this->order) ) {
			$response = new WP_Error ('bbz-ord-200', 'Invalid order object for update_order_status');
			$this->notify_admin ("Failed to update status for order", $response);
			return $response;
		}
		$zoho_order_id = $this->order->get_meta ('zoho_order_id', true);
		
		// check that order already been sent
		if (empty($zoho_order_id)) {
			$response = new WP_Error ('bbz-ord-201', 'update_order_status Order not yet submitted to Zoho', array ('order'=>$this->order));
			$this->notify_admin ("Failed to update status for order", $response);
			return $response;
		}
		
		$options = new bbz_options ();
		
		// First identify the type of user
		$user_id = $this->order->get_user_id ();
		
		$zoho = new zoho_connector;
		$response = $zoho->get_salesorder ($zoho_order_id);
		if (is_wp_error ($response) ) {
			$response->add ('bbz-ord-202', 'update_order_status get_salesorder failed', array ('order'=>$this->order));
			$this->notify_admin ("Failed to update status for order", $response);
			return $response;
		}
		$shipments = array();
		if ($response ['shipped_status']=='shipped') {  // order completely shipped
			// collect shipment data and save to order - could be used in email to customer
			foreach ($response ['packages'] as $package) {
				$shipments [] = array (
					'package_number' => $package ['package_number'],
					'shipment_number' => $package['shipment_number'],
					'shipment_date' => $package ['shipment_date'],
					'carrier' => $package ['carrier'],
					'service' => $package ['service'],
					'tracking_number' => $package ['tracking_number'],
				);
			}
			if (!empty ($shipments)) $this->order->update_meta_data ('zoho_shipments', $shipments);
			
			//update order status - should trigger email to customer
			$this->order->set_status ('completed');
			$this->order->save();
		}
	
		return $response;
	}
	
	private function notify_admin ($message, $error) {
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
		
		

}
?>