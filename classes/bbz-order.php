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
	
	
	function __construct () {
	}
	
	public function process_new_order ($order_id) {
		$order = new WC_Order( $order_id );	
		$options = new bbz_options ();
		
		// First identify the type of user
		$user_id = $order->get_user_id ();
		//bbz_debug ($user_id, "User ID", false);
		if (empty($user_id)) { //guest
			$zoho_cust_id = false;
			$payment_terms = array (
				'name' => 'Pay with order',
				'days' => '0'
			);
		
		} else {
			$user_meta = new bbz_usermeta ($user_id);
			$zoho_cust_id = $user_meta->get_zoho_id();
			$payment_terms = $user_meta->get_payment_terms();
			
			//$user_type = 'registered';  //but may not have a zoho id if no zoho account
		}
		//bbz_debug ($zoho_cust_id, "Zoho Cust ID", false);
		
		// Get the address id
		$bbz_addresses = new bbz_addresses ($user_id);
		$shipto = $order->get_address ('shipping');
		if ($zoho_cust_id === false) {  
			// logged in user without zoho id - treat as guest
			$zoho_cust_id = $options->get ('guestuserid');
			//bbz_debug ($zoho_cust_id, "Zoho Cust ID", false);

			if ($zoho_cust_id === false) {
				$this->notify_admin ('No guest customer linked', $order);
				return false; // no guest customer linked - can't process order
			}
			//$user_type = 'guest';
			$zoho_address_id = $bbz_addresses->get_zoho_address_id ($shipto, 'shipping', $zoho_cust_id); // specifying cust id overrides default
		} else {
			$zoho_address_id = $bbz_addresses->get_zoho_address_id ($shipto, 'shipping');
			if (empty ($zoho_address_id)) {
				// check in case the order's using the billing address
				$zoho_address_id = $bbz_addresses->get_zoho_address_id ($shipto, 'billing');
				if (empty ($zoho_address_id)) {
					// and if that failed, we'll create a new address
					$zoho_address_id = $bbz_addresses->get_zoho_address_id ($shipto, 'shipping', $zoho_cust_id);
				}
			}
		}
		// if unable to get address id, can't create order.  Can we email admin?
		if (empty ($zoho_address_id)) {
			$message = 'Unable to get Zoho address id\nShip To: '.print_r($shipto,true).
				'\nbbz_addresses '.print_r($bbz_addresses, true);
			$this->notify_admin ($message, $order);
			return false;
		}
		
		$zoho_order = $this->create_zoho_order ($zoho_cust_id, $zoho_address_id, $payment_terms, $order);
		if (!is_array($zoho_order) ) {
			$message = 'Unable to create Zoho order\n'.
				'Zoho Customer ID '.$zoho_cust_id.'\n'.
				'Zoho Address ID '.$zoho_address_id.'\n';
			$this->notify_admin ($message, $order);
			return false;
		}
		
		$zoho_order_id = $zoho_order['salesorder_id'];
		//bbz_debug ($zoho_order, 'Order created', false);
		
		// if order is paid (excluding on account), create an invoice and payment record.
		$zoho = new zoho_connector;		
		if ($order->is_paid() && $order->get_payment_method() !== 'account') {
			$payment_details = "Paid ".$order->get_date_paid().' '.
				$order->get_payment_method_title().' '.$order->get_transaction_id();
			$zoho->salesorder_addcomment ($zoho_order_id, $payment_details);
			//bbz_debug ($result, 'Add Comment', false);
			$zoho->salesorder_confirm ($zoho_order_id);
			
			// create an invoice and payment record.
			$zoho_invoice = $this->create_zoho_invoice ($zoho_order, $order);
			//bbz_debug ($zoho_invoice, 'Invoice created', false);
			
			if (is_array($zoho_invoice)) {
				$zoho_payment = $this->create_zoho_payment ($zoho_invoice, $order);
				//bbz_debug ($zoho_payment, 'Payment created');
			}
		} else {
			// if user is wholesale customer confirm anyway
			if (bbz_is_wholesale_customer ($user_id)) {
				$zoho->salesorder_confirm ($zoho_order_id);
			}
		}
		
		return $zoho_order;
	}
	
	private function create_zoho_order ($zoho_cust_id, $zoho_address_id, $payment_terms, $order) {
		$zoho_order = array();
		$zoho_order ['customer_id'] = $zoho_cust_id;
		$zoho_order ['shipping_address_id'] = $zoho_address_id;
		$zoho_order ['salesorder_number'] = ZOHO_SALESORDER_PREFIX.$order->get_order_number();
		$zoho_order ['custom_fields'][] = array (
				'customfield_id'	=> '1504573000002888095', // Order source
				'value'				=> 'Website',
			);
		$zoho_order ['shipping_charge'] = $order->get_shipping_total();
		$zoho_order ['discount'] = $order->get_discount_total();
		$zoho_order ['payment_terms_label'] = $payment_terms ['name'];
		$zoho_order ['payment_terms'] = $payment_terms ['days'];
		$zoho_order ['notes'] = $order->get_customer_note();
		if ($zoho_order ['discount'] == 0) unset ($zoho_order ['discount']);
		if (empty ($zoho_order ['notes']) ) {
			unset ($zoho_order ['notes']);
			$zoho_order ['notes_default'] = true;
		}
		$zoho_order['terms_default'] = true;
		
		// Iterate Through Items
		$total = 0;		
		foreach ( $order->get_items() as $item_id=>$item ) {
			$zoho_line = array();
			$zoho_line ['item_id'] = get_post_meta ($item->get_product_id(), BBZ_PM_ZOHO_ID, true);
			$zoho_line ['quantity'] = $item->get_quantity();
			$zoho_line ['rate'] = $item->get_subtotal() / $zoho_line ['quantity'];
			if ($zoho_line ['item_id'] !== false) {
				$zoho_order ['line_items'][] = $zoho_line;
			}
			$total += $zoho_line['rate'] * $zoho_line ['quantity'];
		}
		if ( $total !== $order->get_subtotal()) {  // account for any rounding errors
			$zoho_order ['adjustment'] = $order->get_subtotal() - $total;
		};
		//bbz_debug ($zoho_order, 'Order array before sending', false);
		$zoho = new zoho_connector;
		$zoho_order = $zoho->create_salesorder ($zoho_order);
		$zoho_order_id = $zoho_order['salesorder_id'];
		$order->add_meta_data ('zoho_order_id', $zoho_order_id);
		return $zoho_order;

	}
	
	private function create_zoho_invoice ($zoho_order, $order) {
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

		//bbz_debug ($zoho_invoice, 'Invoice array before sending', false);
		$zoho = new zoho_connector;
		$zoho_invoice = $zoho->create_invoice ($zoho_invoice, $confirm=true);
		$zoho_invoice_id = $zoho_order['invoice_id'];
		$order->add_meta_data ('zoho_invoice_id', $zoho_invoice_id);
		return $zoho_invoice;

	}
	
	private function create_zoho_payment ($zoho_invoice, $order) {
		$zoho_payment = array();
		$zoho_payment ['customer_id'] = $zoho_invoice ['customer_id'];
		$zoho_payment ['amount'] = $order->get_total();
		$zoho_payment ['date'] = substr ($order->get_date_paid(), 0, 10);
		$zoho_payment ['invoices'][] = array(
			'invoice_id' => $zoho_invoice ['invoice_id'],
			'amount_applied' => $order->get_total()
		);
		if (stristr ($order->get_payment_method(), 'paypal')) {
			$zoho_payment ['payment_mode'] = 'Paypal';
			$zoho_payment ['account_id'] = ZOHO_PAYPAL_ACCOUNT_ID;
		} elseif (stristr ($order->get_payment_method(), 'stripe')) {
			$zoho_payment ['payment_mode'] = 'Stripe';
			$zoho_payment ['account_id'] = ZOHO_STRIPE_ACCOUNT_ID;
		} else return false;
		$zoho_payment ['reference_number'] = $order->get_transaction_id();

		//bbz_debug ($zoho_payment, 'Payment array before sending', false);
		
		$zoho = new zoho_connector;
		$zoho_payment = $zoho->create_payment ($zoho_payment);
		$order->add_meta_data ('zoho_payment_id', $zoho_payment ['payment_id']);
		return $zoho_payment;
	}
	
	private function notify_admin ($message, $order) {
		$subject = 'Failed to create Zoho order #'.$order->get_order_number();
		$message .= '\nOrder Data\n'.print_r ($order->get_data(), true);
		bbz_email_admin ($subject, $message);
	}
		
		

}
?>