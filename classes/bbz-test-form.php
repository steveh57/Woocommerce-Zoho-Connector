<?php
/**
 * Testing functions for bb-zoho-connector
 *
 * 
 */
 
 // If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class bbz_test_form extends bbz_admin_form {

	private $testform = array (
			'name'		=>	'bbzform-test',
			'class'		=>	'bbz_test_form',
			'title'		=>	'<h2>Test Connection</h2>',
			'text_before'	=> '<p>Select the Zoho request to test</p>',
			'fields'	=>	array (
				'function'			=> array (
					'type'			=> 'select',
					'title'		=> 'Test',
					'options'	=> array (
						'get-dataset'	=>	'Get specified Books dataset',
						'get-analytics'	=>	'Get specified Analytics dataset',
						'get-itemdata'		=>	'Item Data',
						'get-shipping-classes' => 'Get wc shippng classes',
						'call-function'		=> 'Call function filterkey(filtervalue)',
						'get-product-detail'	=> 'Call product function (key=product_id, value=function)',
						'get-customers'		=> 'Get Zoho customer list',
						'get-emails'		=> 'Get Zoho email list',
						'get-names'			=> 'Get Zoho customer names',
						'get-contact'		=> 'Get Zoho contact by email address',
						'get-contact-id'	=> 'Get Zoho contact by id',
						'get-addresses'		=> 'Get Zoho addresses for customer id',
						'get-sales-history'	=> 'Get Zoho sales history',
						'get_salesorder'	=> 'Get Zoho salesorder (dataset=zoho order id)',
						'show-order'		=> 'Display woo order data (key=order no)',
					//	'submit-order'		=> 'Submit order (key=order no) to Zoho',
						'confirm-order'		=> 'Confirm sales order (key=zoho order id)',
						'process-orders'	=> 'New process outstanding orders',
						'get-user-meta'		=> 'Get user meta (key=user id, val=meta key(optional)',
						'get-post-meta'		=> 'Get post meta (key=post id, val=meta key(optional)',
						'show-options'		=> 'Show bbz option data',
						'set-option'		=> 'Set bbz option (key)',
						'product-filter'	=> 'Test product filter',
						'load-auth'		=> 'Load Authorisation',
					)
				),
				'dataset' => array(
					'type'		=> 'text',
					'title'		=> 'Dataset',
				),
				'filterkey'		=> array (
					'type'		=> 'text',
					'title'		=> 'Filter Key'
				),
				'filtervalue'	=> array (
					'type'		=> 'text',
					'title'		=> 'Filter Value'
				),
				'state'			=> array(    // Status hidden
					'type'          => 'hidden',
					'title'         => 'State',
					'value'			=>	'bbztest'
				)
			),
			'button'		=> array (
				'name'			=> 'submit',
				'type'			=> 'primary',
				'title'			=> 'Fetch data'
			)
	);


	function __construct () {
		parent::__construct($this->testform);

	}
	
	public function action () {
	}

	public function display_data () {
		$function = $this->options->get('function');
		$filterkey = $this->options->get ('filterkey');
		$filtervalue = $this->options->get ('filtervalue');
		$filter = array($filterkey=>$filtervalue);
		$dataset = $this->options->get('dataset');
		
		if (empty ( $function )) return false;
		echo '<h2>Results for "'.$function.'"</h2>';
		$zoho = new zoho_connector;
		echo '<br>Connection '.( $zoho->connected ? 'successful' : 'failed').'<br>';
		switch ($function) {

			case 'get-itemdata':
				$data = $zoho->get_items();
				break;

			case 'get-customers':
				$data = $zoho->get_customers();
				break;
				
			case 'get-shipping-classes':
				$shipping= new WC_shipping();
				$data = $shipping->get_shipping_classes();
				break;
				
			case 'call-function':
				$data = call_user_func ($filterkey,$this->options->get ('filtervalue')) ;
				break;			
			
			case 'get-product-detail':
				$product = wc_get_product ($filterkey);
				$data = call_user_func (array($product, $filtervalue));
				break;
				
			case 'get-emails';
				$data = $zoho->get_customer_emails();
				break;
				
			case 'get-names';
				$data = $zoho->get_customer_names();
				break;
				
			case 'get-contact':
				$data = $zoho->get_contact_by_email($filtervalue);
				break;
				
			case 'get-contact-id':
				$data = $zoho->get_contact_by_id ($filtervalue);
				break;
				
			case 'get-addresses':
				$data = $zoho->get_contact_address($filtervalue);
				break;

			case 'get-sales-history':
				$data = $zoho->get_sales_history();
				break;
			
			case 'get_salesorder':
				$data = $zoho->get_salesorder ($dataset, $filter);
				break;
				
			case 'product-filter':
				$args = array();
				$args ['post__in'][] = $filtervalue;
				$data = bbz_wwof_product_filter ($args);
				break;
				
			case 'get-user-meta':
				$user_id = !empty ($filterkey) ? $filterkey : wp_get_current_user()->ID;
				$data = get_user_meta ($user_id, $filtervalue);
				break;
			
			case 'get-post-meta':
				$post_id = $filterkey;
				$data = get_post_meta ($post_id, $filtervalue, true);
				break;
				
			case 'show-options':
				$data = $this->options->getall();
				break;
				
			case 'set-option':
				$this->options->update ($filterkey, $filtervalue, true);
				$data = $this->options->getall();
				break;
				
			case 'get-dataset':
				$data = $zoho->get_books ($dataset, $filter);
				break;
				
			case 'get-analytics':
				$response = $zoho->get_analytics ($dataset, $filter);
				if (is_array($response)) {
//						echo 'Response: <pre>'; print_r ($response); echo '</pre>';
					echo 'Headers: <pre>'; print_r ($response['headers']); echo '</pre>';
					echo 'Body: <pre>'; print_r (json_decode ($response['body'], true)); echo '</pre>';
				} else {
					echo '<br>No data returned';
				}
				$data = false;
				break;
			
			case 'show-order':
				$order_id = $filterkey;
				$data = wc_get_order($order_id);// ($order_id);
				//$data = array ($order_id=>$order->get_data());
				break;
			
			case 'process-orders':
				bbz_process_orders ($resubmit=$filtervalue);
				break;

			case 'confirm-order':
				$order_id = $filterkey;
				echo 'Processing order ', $order_id;
				$zoho = new zoho_connector;
				$result = $zoho->confirm_salesorder($order_id);
				echo 'Result ', $result ? $result : 'failed';
				break;

		}

		if (!empty($data)){
			if (is_array($data)) {
				echo count ($data) . ' items returned.<br>';
				echo '<pre>'; print_r ($data); echo '</pre>';
			} else {
				echo '<pre>'; var_dump ($data); echo '</pre>';
			}
		} else {
			echo '<br>No data returned';
		}
		$this->options->delete ('function', true);
		
	}




}
?>