<?php
/**
 * Action functions for bb-zoho-connector
 *
 * 
 */
 
 // If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class bbz_action_form extends bbz_admin_form {

	private $actionform	= array (
			'name'		=>	'bbzform-action',
			'class'		=>	'bbz_action_form',
			'title'		=>	'<h2>Update from Zoho</h2>',
			'text_before'	=> '<p>Choose an option and click the Execute button to import data and carry out the updates.<br>'.
				'Note that Update Products fetches product data from Zoho and uses it to overrides various product settings in '.
				'woocommerce with data from Zoho:<br>'.
				'* - Price<br>'.
				'* - Sale Price (if ORP set in Zoho)<br>'.
				'* - Wholesale price<br>'.
				'* - Tax Class<br>'.
				'* - Tax Status<br>'.
				'* - Shipping class (if set in Zoho)<br>'.
				'* - Stock level<br>'.
				'* - Backorder setting<br>'.
				'* - Catalog visibility<br>'.
				'.* - Wholesale visibility</p>',
			'fields'	=>	array (
				'function'	=> array (
					'type'		=> 'select',
					'title'		=> 'Action',
					'options'	=> array (
						'update-products'	=>	'Update Products',
						'update-users'	=>	'Update user info and sales history',
//						'update-addresses' => 'Update all user addresses from zoho', // disabled - this is dangerous!
						'submit-order' => 'Submit selected order to Zoho',
						'process-orders' => 'Run order processing (load outstanding orders and update status)',
						'check-products'	=>	'Check for missing products',
					)
				),
				'filterkey'		=> array (
					'type'		=> 'text',
					'title'		=> 'Filter Key'
				),
				'override'		=> array (
					'type'	=>	'checkbox',
					'title'	=>	'Override order resubmit check',
				),

				'state'			=> array(    // Status hidden
					'type'          => 'hidden',
					'title'         => 'State',
					'value'			=>	'bbzaction'
				)
			),
			'button'		=> array (
				'name'			=> 'submit',
				'type'			=> 'primary',
				'title'			=> 'Execute'
			)
		);



	function __construct () {
		parent::__construct($this->actionform);
	}
	
	public function action () {
		$this->options->reload ();
		switch ($this->options->get('function')) {
		case 'update-products':
			$result= bbz_update_products();
			if (! $result) {
				$this->options->set_admin_notice ('Product update failed', 'error');
			} else {
				$this->options->set_admin_notice ($result.' Products Updated', 'success');
			}
			break;
		case 'check-products':
			break;  // this is dealt with in display_data
		
		case 'update-users':
			$result= bbz_load_sales_history('all');  //update sales history  all linked users
			if ($result) $result = bbz_update_payment_terms('all');
			if (! $result) {
				$this->options>set_admin_notice ('Sales history load failed', 'error');
			} else {
				$this->options->set_admin_notice ($result.' users updated', 'success');
			}
			break;
			
		case 'update-addresses':
			$result= $this->update_addresses('all',true);  //update sales history and payment terms for all linked users
			if (! $result) {
				$this->options->set_admin_notice ('Update addresses failed', 'error');
			} else {
				$this->options->set_admin_notice ($result.' user addresses updated', 'success');
			}
			break;	
		
		
		case 'submit-order':
			$order_id = $this->options->get('filterkey');
			$resubmit = $this->options->get('override') == 'on';
			echo 'Processing order ', $order_id, "\n";
			$bbz_order = new bbz_order ($order_id);
			$result = $bbz_order->process_new_order($resubmit);
			$this->options->update ('data', $result);
			if (is_wp_error($result)) {
				$this->options->set_admin_notice ('Order '.$order_id.' load failed', 'error');
			} else {
				$this->options->set_admin_notice ( 'Order '.$order_id.' loaded successfully', 'success');
			}
			break;
		
		case 'process-orders':
			$resubmit = $this->options->get('override') == 'on';
			$result = bbz_process_orders($resubmit);
			break;
		}


	}

	public function display_data () {

		switch ($this->options->get('function')) {
			case 'check-products':
			case 'update-products':
				$data = $this->get_missing_items();
				if (is_array($data)) {
					echo '<h2>Missing Products</h2>';
					echo 'The following items are not present on the website:<br>';
					echo '<pre>'; print_r ($data); echo '</pre>';
				}
				break;
				
			case 'submit-order':
				echo '<pre>'; print_r ($this->options->get ('data')); echo '</pre>';
				$this->options->delete('data');
				break;

		}
		$this->options->delete('function', true);

	}
	/*****
	* GET MISSING ITEMS
	*
	* get list of items not present in woocommerce
	* returns array isbn => name
	*****/
	private function get_missing_items() {
	
		//get list of zoho items indexed by sku
		$zoho = new zoho_connector;
		$items = $zoho->get_items();
		if (is_array($items)) {
			// get list of product posts
			$args = array (
				'post_type' => 'product',	// only get product posts
				'numberposts' => -1,		// get all of them
			);
			$products = get_posts ( $args);
			$index = array();
			//build index of sku => product_id
			foreach ( $products as $product ) {
				$sku = get_post_meta ($product->ID, '_sku', $single=true);	// sku is in meta data
				if ( !('' == $sku) ) {
					$index [$sku] = $product->ID;
				}
			}
			// if item sku from zoho is not in product list
			foreach ($items as $sku=>$item) {
				if (!isset ($index[$sku]) && $item['status']=='active') {
					$results [$sku] = $item['name'];
				}
			}
			return $results;
		} else {
			return false;
		}
	}
	
	// update all users with a zoho id with addresses from zoho.
	// this is really only to initialize the bbz_addresses array
	
	private function update_addresses () {
		// if user not specified get list of all users
		$users =  get_users() ;
		$zoho = new zoho_connector;
		$update_count = 0;
		if (!empty ($users)) {
			foreach ($users as $user) {
				$user_meta = new bbz_usermeta ($user->ID);
				$zoho_cust_id = $user_meta->get_zoho_id();
				if (!empty ($zoho_cust_id) ) {
					$zoho_contact = $zoho->get_contact_by_id($zoho_cust_id);
					if (is_array ($zoho_contact)) {
						$bbz_addresses = new bbz_addresses ($user->ID);
						$bbz_addresses->load_from_zoho_contact ($zoho_contact);
						$update_count += 1;
					}
				}
			}
		}
		//bbz_debug ($update_count, 'Update Addresses Finished');
		return $update_count;
	}

}
?>