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

include_once ( dirname( __FILE__ ) . '/bbz-definitions.php');
include_once ( dirname( __FILE__ ) . '/bbz-zoho-connector-class.php');
include_once ( dirname( __FILE__ ) . '/bbz-admin-forms.php');

class bbz_test extends bbz_admin_form {

	private $testform = array (
			'name'		=>	'bbzform-test',
			'class'		=>	'bbz_test',
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
						'get-product-detail'	=> 'Call product function (key=product_id, value=function)',
						'get-customers'		=> 'Customer Data',
						'get-emails'		=> 'Get email list',
						'get-names'			=> 'Get customer names',
						'get-contact'		=> 'Get contact by email address',
						'get-contact-id'	=> 'Get contact by id',
						'get-addresses'	=> 'Get addresses for customer id',
						'get-sales-history'	=> 'Get sales history',
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
		$this->form = $this->testform;
	}
	
	public function action ($options) {
// CODE FOR TESTING ONLY - TO BE REMOVED
				if (isset($options['function']) && $options['function'] == 'load-auth' ) {
					$options ['client_id'] = '1000.TPISI0TSLJF7YMBBX5K8O2FJI6X1IH';
					$options ['client_secret'] = '1d98657adf93892a1baccadd32af546e9cae8a9187';
					$options ['redirect_uri'] = 'https://bitternbooks.co.uk/wp-admin/admin-post.php';
					$options ['refresh_token'] = '1000.73e32d9535b81dc513a6b87c0407ba61.0c8ba3110643c60a73f1713b6677647b';
				}
		update_option(OPTION_NAME, $options);
// END TEST CODE
	}

	public function display_data ($options) {
		if (!isset($options['function'])) return false;
		echo '<h2>Results for "'.$options['function'].'"</h2>';
		$zoho = new zoho_connector;
		echo '<br>Connection '.( $zoho->connected ? 'successful' : 'failed').'<br>';
		switch ($options['function']) {
			case 'check-products':
				$data = $zoho->get_missing_items();
				break;
				
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
			
			case 'get-product-detail':
				$product = wc_get_product ($options ['filterkey']);
				$data = call_user_func (array($product, $options['filtervalue']));
				break;
				
			case 'get-emails';
				$data = $zoho->get_customer_emails();
				break;
				
			case 'get-names';
				$data = $zoho->get_customer_names();
				break;
				
			case 'get-contact':
				$data = $zoho->get_contact_by_email($options['filtervalue']);
				break;
				
			case 'get-contact-id':
				$data = $zoho->get_contact_by_id ($options['filtervalue']);
				break;
				
			case 'get-addresses':
				$data = $zoho->get_contact_address($options['filtervalue']);
				break;

			case 'get-sales-history':
				$data = $zoho->get_sales_history();
				break;
				
			case 'product-filter':
				$args = array();
				$args ['post__in'][] = $options['filtervalue'];
				$data = bbz_wwof_product_filter ($args);
				break;
				
			case 'get-dataset':
				$filter = array();
				if (isset ($options['filterkey'])) {
					$filter = array ($options['filterkey']=>$options['filtervalue']);
				} 
				$response = $zoho->get_books ($options['dataset'], $filter);
				if (is_array($response)) {
					echo 'Headers: <pre>'; print_r ($response['headers']); echo '</pre>';
					echo 'Body: <pre>'; print_r (json_decode ($response['body'], true)); echo '</pre>';
				} else {
					echo '<br>No data returned';
				}
				$data = false;
				break;
				
			case 'get-analytics':
				$filter = array();
				if (isset ($options['filterkey'])) {
					$filter = array ($options['filterkey']=>$options['filtervalue']);
				} 
				$response = $zoho->get_analytics ($options['dataset'], $filter);
				if (is_array($response)) {
//						echo 'Response: <pre>'; print_r ($response); echo '</pre>';
					echo 'Headers: <pre>'; print_r ($response['headers']); echo '</pre>';
					echo 'Body: <pre>'; print_r (json_decode ($response['body'], true)); echo '</pre>';
				} else {
					echo '<br>No data returned';
				}
				$data = false;
				break;
		}

		if (isset($data)){
			if (is_array($data)) {
				echo count ($data) . ' items returned.<br>';
				echo '<pre>'; print_r ($data); echo '</pre>';
			} else {
				echo '<pre>'; var_dump ($data); echo '</pre>';
			}
		} else {
			echo '<br>No data returned';
		}
		unset ($options['function']);
		
	}




}
?>