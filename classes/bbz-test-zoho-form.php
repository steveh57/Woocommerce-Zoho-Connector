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

class bbz_test_zoho_form extends bbz_admin_form {

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
						'get-dataset'	=>	'Get specified Zoho Books dataset',
						'post-dataset'	=>	'Post (key:value) to specified Books dataset',
						'get-analytics'	=>	'Get specified Analytics dataset',
						'get-itemdata'		=>	'Get Zoho items',
						'get-customers'		=> 'Get Zoho customer list',
						'get-emails'		=> 'Get Zoho email list',
						'get-names'			=> 'Get Zoho customer names',
						'get-contact'		=> 'Get Zoho contact by email address (filtervailue)',
						'get-contact-id'	=> 'Get Zoho contact by id (filtervailue)',
						'get-addresses'		=> 'Get Zoho addresses for customer id',
						'get-sales-history'	=> 'Get Zoho sales history',
						'get_salesorder'	=> 'Get Zoho salesorder (dataset=zoho order id)',
						'confirm-order'		=> 'Confirm sales order (key=zoho order id)',
						'delete-address'	=> 'Delete Zoho address (key=customer_id, value=address_id)',

						'get_shipmentorders' => 'Get shipment orders (key=id (optional),value=status)',
						'get_packages' 		=> 'Get packages (key=id (optional),value=status)',
					//	'update_shipment_status' => 'Update Zoho shipment status (key=id, val=status)',
					//	'update_shipment_test' => 'Test update_shipmentorder function',
					)
				),

				'filterkey'		=> array (
					'type'		=> 'text',
					'title'		=> 'Filter Key'
				),
				'filtervalue'	=> array (
					'type'		=> 'text',
					'title'		=> 'Filter Value'
				),
				'dataset' => array(
					'type'		=> 'text',
					'title'		=> 'Dataset',
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
		$filtervalue = strstr ($filtervalue, ',') ? str_getcsv ($filtervalue) : $filtervalue;
		$filter = array($filterkey=>$filtervalue);
		$dataset = $this->options->get('dataset');
		
		if (empty ( $function )) return false;
		echo '<h2>Results for "'.$function.'"</h2>';
		$zoho = new zoho_connector;
		if (is_wp_error ($zoho->connected)) {
			$data = $zoho->connected;
			echo '<br>Connection failed<br>';
			$codes = $data->get_error_codes();
			foreach ($codes as $error_code) {
				echo 'Error: '.$error_code.' -> '.$data->get_error_message ($error_code)."\n";
				echo 'Error data: <pre>'.print_r ($data->get_error_data ($error_code), true)."</pre>\n";
			}
		} else {
			echo '<br>Connection successful<br>';
		}
			switch ($function) {

			case 'get-dataset':
				$data = $zoho->get_books ($dataset, $filter);
				break;
				
			case 'post-dataset':
				$data = $zoho->post_books ($dataset, $filter);
				break;

				
			case 'get-itemdata':
				$data = $zoho->get_items();
				break;

			case 'get-customers':
				$data = $zoho->get_customers();
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
				
			case 'get-analytics':
				$data = $zoho->get_analytics ($dataset, $filter);
				break;
			
			case 'confirm-order':
				$order_id = $filterkey;
				echo 'Processing order ', $order_id;
				$data = $zoho->confirm_salesorder($order_id);
				break;
				
			case 'delete-address':
				$data = $zoho->delete_address($filterkey, $filtervalue);
				break;
			

			case 'get_shipmentorders':
				$zoho_so = new zoho_shipmentorders;
				if (!empty($filterkey)) {
					$data= $zoho_so->get_shipmentorder_by_id ($filterkey);
				} else{
					$data = $zoho_so->get_shipmentorders_by_status ($filtervalue);
				}
				break;
				
			case 'get_packages':
				$zoho_so = new zoho_shipmentorders;
				if (!empty($filterkey)) {
					$data= $zoho_so->get_package_by_id ($filterkey);
				} else{
					$data = $zoho_so->get_packages_by_status ($filtervalue);
				}
				break;
			
			}
		
		
		if (!empty($data)){
			if (is_wp_error ($data) ) {
				$codes = $data->get_error_codes();
				foreach ($codes as $error_code) {
					echo 'Error: '.$error_code.' -> '.$data->get_error_message ($error_code)."\n";
					echo 'Error data: <pre>'.print_r ($data->get_error_data ($error_code), true)."</pre>\n";
				}
			}					
		
			elseif (is_array($data)) {
				echo count ($data) . ' items returned.<br>';
				echo '<pre>'; print_r ($data); echo '</pre>';
			} else {
				echo '<pre>'; var_dump ($data); echo '</pre>';
			}
		} else {
			echo '<br>No data returned';
		}
		$this->options->reload();
		$this->options->delete ('function', true);

		
		$data= array ('Options'=>$this->options->getall());
		echo '<pre>'; print_r ($data); echo '</pre>';
		
		
	}




}
?>