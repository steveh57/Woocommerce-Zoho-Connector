<?php
/**
 * Order Entry form
 *
 * This form allows an administrator to create an order in Zoho by 
 * selecting the customer and delivery address and pasting the order
 * text into the textarea field.  
 */
 
 // If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class bbz_orderentry_form extends bbz_admin_form {

	private $form_definitions = array (
		// bbzform-auth
		'bbzform-order1'	=> array (
			'name'		=> 'bbzform_orderentry',
			'class'		=> 'bbz_orderentry_form',
			'action'	=>	'order1_action',
			'title'		=>	'<h2>Enter order as text</h2>',
			'text_before'	=> '<p>Paste order text into form or select file</p>',
			'fields'	=>	array (
				'customer'			=> array (
					'type'			=> 'select',
					'title'		=> 'Select Customer',
					'optionfunc'	=> 'customer_list',
				),
				'format'			=> array (
					'type'			=> 'select',
					'title'		=> 'Order Format',
					'options'	=> array (
						'roys' => 'Roys order format',
						'comma'	=> 'Comma separated',
						'x' => 'x separated',
						'space' => 'Space separated',
						'tab'	=> 'Tab separated',
					),
				),
				'file_to_upload'			=> array (
					'type'		=> 'file',
					'title'		=> 'Upload File (optional)',
				),
				'ordertext'	=> array (
					'type'		=> 'textarea',
					'title'		=> 'Order Text',
					'attributes' => array (
						'rows' => '10',
						'cols' => '50'
						),
				),
				'reference' => array (
					'type'		=> 'text',
					'title'		=> 'Reference'
				),
				'nextform' => array (
					'type' => 'hidden',
					'value' => 'bbzform-order2'
					),
			),
			'button'		=> array (
				'name'			=> 'submit',
				'type'			=> 'primary',
				'title'			=> 'Parse order'
			)
		),
		'bbzform-order2'	=> array (
			'name'		=> 'bbzform_orderentry',
			'class'		=> 'bbz_orderentry_form',
			'action'	=>	'order2_action',
			'title'		=>	'<h2>Order 2</h2>',
			'text_before'	=> '<p>Check order and confirm</p>',
			'fields' => array(
				'ship_address' => array (
					'type'		=> 'select',
					'title'		=> 'Select shipping Address',
					'optionfunc'	=> 'ship_address_list',
				),
				'confirm'	=> array(
					'type' =>  'checkbox',
					'title' => 'Check box to submit order to Zoho',
				),
				'nextform' => array (
					'type' => 'hidden',
					'value' => 'bbzform-order1'
				),
			),
			'button'		=> array (
				'name'			=> 'submit',
				'type'			=> 'primary',
				'title'			=> 'Submit order'
			)			
		)
	);
	
	function __construct () {
		$this->options = new bbz_options();
		$form = $this->options->get('nextform');
		if (empty ($form)) $form = 'bbzform-order1';
		parent::__construct($this->form_definitions[$form]);
	}
	
	public function customer_list () {
		$zoho = new zoho_connector;
		return $zoho->get_customer_names ();
	}
	
	public function ship_address_list () {
		$customer_id = $this->options->get ('customer');
		$zoho = new zoho_connector;

		$addresses = $zoho->get_contact_address($customer_id);
		foreach ($addresses as $address) {
			$address_list [$address['address_id']] = $address['attention'].', '.$address['address'].', '.$address['zip'];
		}
		return $address_list;
	}
		
		
	private function parserow (DOMNode $domNode) {
		$row=array();
		foreach ($domNode->childNodes as $node) {
			if ($node->nodeName=='td') {
				/*if ($node->hasChildNodes()) {
					foreach ($node->childNodes() as $child) {
						if ($child->nodeName == 'table') $row [] = $this->parsetable ($child);
					}
				} else { */
					$row[] = $node->nodeValue;
				//}
			}
		}
		return $row;
	}
	
	private function parsetable(DOMNode $domNode) {
		
		$table=array();
		foreach ($domNode->childNodes as $node) {
			if ($node->nodeName=='tr') {
				$table[] = $this->parserow($node);
			} elseif (in_array($node->nodeName, array ('thead', 'tbody', 'tfoot'))) {
				$table[$node->nodeName] = $this->parsetable ($node);
			}
		}
		return $table;
	}  
	private function parseroys (DOMDocument $DOM) {
		$tables = array();
		$order = array();
		foreach ( $DOM->getElementsByTagName('table') as $table) {
			$tables[] = $this->parsetable ($table);
		}
		$order['po_number'] = $tables[1][0][5];
		$order['postcode'] = $tables [1][5][3];
		$order['items'] = array();
		foreach ($tables[3]['tbody'] as $row) {
			$order['items'][] = array (
				'isbn' => $row [4],
				'title' => $row [2],
				'qty' => (int)$row [3] * stristr($row[5],'/',true),
				'cost' => (float)$row [6],
				'value' => (float)$row [8]
			);
		}
		$order['total'] = $tables[3]['tfoot'][0][6];
		
		//$order['tables']=$tables; //for debugging
		return $order;
	}
	
	private function parse_text ($ordertext) {
		$separators = array (
			'comma' => ',',
			'x' => 'x',
			'space' => ' ',
			'tab' => "\t",
		);
		
		$orderlines = explode(PHP_EOL, $ordertext); //parse the rows
		$separator = $separators [$this->options->get('format')];
		$order['po_number'] = $this->options->get('reference');
		foreach ($orderlines as $line_no => $line) {
			if (strlen(trim($line)) > 0) {
				$fields = explode($separator, $line);
				foreach ($fields as $field) {
					$field = trim($field); //remove any spaces
					// identify isbn and quantity fields.  Can be in any order.
					if (strlen($field)==13 && is_numeric($field) && str_starts_with($field, '978')) {
						$order ['items'][$line_no]['isbn'] = $field;
					} elseif (is_numeric($field) && $field <= 999) {
						if(filter_var($field, FILTER_VALIDATE_INT) !== false) {
							$order ['items'][$line_no]['qty'] = $field;
						} else {
							$order ['items'][$line_no]['cost'] = $field;
						}
					} elseif (strlen($field)>0) {
						// add anything else to title field
						$order ['items'][$line_no]['title'] .= $field; 
					}
				}
			}
		}
		$order ['postcode'] = '';
		return $order;
	}
	
	public function order1_action () {
		$separators = array (
			'comma' => ',',
			'x' => 'x',
			'space' => ' '
		);
		$this->options->reload();
		if (!empty( $_FILES["file_to_upload"]["tmp_name"])) {
			$filename = $_FILES["file_to_upload"]["name"];
			$file_type = strtolower(pathinfo($filename,PATHINFO_EXTENSION));
			$ordertext = file_get_contents ($_FILES["file_to_upload"]["tmp_name"]);
		} else {
			$ordertext = $this->options->get('ordertext');
		}
		if (!empty($ordertext)) {
			if ($this->options->get('format') == 'roys' && $file_type== 'html') {
				$doc = new DOMDocument();
				$doc->loadHTML ($ordertext, LIBXML_NOERROR);
				$order = $this->parseroys ($doc);
				
			} else { //csv - should work with , or x separator
				$order= $this->parse_text ($ordertext);
			}
		}
		// now lookup zoho data
		if (!empty ($order) ) {
			$zoho = new zoho_connector;
			$zoho_items = $zoho->get_items();
			$order['zoho_total'] = 0;
			foreach ($order['items'] as $line=>$order_item) {
				$sku = $order_item['isbn'];
				if (!empty($sku) && !empty($zoho_items[$sku])) {
					if ($zoho_items[$sku]['status'] == 'inactive') {
						$order['items'][$line]['warning'] = 'Item inactive: '.$zoho_items[$sku]['availability'];
					} else {
						$order['items'][$line]['zoho_id'] =  $zoho_items[$sku]['zoho_id'];
						$order['items'][$line]['zoho_name'] =  $zoho_items[$sku]['name'];
						$order['items'][$line]['zoho_cost'] =  $zoho_items[$sku]['wsp'];
						if (isset ($order_item['cost']) && $zoho_items[$sku]['wsp'] != $order_item['cost'])
							$order['items'][$line]['warning'] = 'Cost price change';
						$order['items'][$line]['stock'] =  $zoho_items[$sku]['stock'];
						if (isset ($order_item['qty'])) {
							if( $zoho_items[$sku]['stock'] < $order_item['qty']) $order['items'][$line]['warning'] = 'Insufficient stock';
							$order['zoho_total'] += $order_item['qty'] * $zoho_items[$sku]['wsp'];
						} else {
							$order['items'][$line]['qty'] = 0;
							$order['items'][$line]['warning'] = 'No quantity specified';
						}
					}
				} else {
					$order['items'][$line]['warning'] = 'No product match in Zoho';
				}
			}
		}
		// save parsed order and original text for display by render()
		$this->options->update(array('order'=>$order, 'ordertext'=>$ordertext), true);
	}
	
	private function build_zoho_order ($customer_id, $ship_address, $order) {
		$zoho_order = array();
		$zoho_order ['customer_id'] = $customer_id;
		$zoho_order ['shipping_address_id'] = $ship_address;
		//$zoho_order ['salesorder_number'] = ZOHO_SALESORDER_PREFIX.$this->order->get_order_number();
		$zoho_order ['reference_number'] = $order['po_number'];
		
		$zoho_order ['custom_fields'][] = array (
				'customfield_id'	=> '1504573000002888095', // Order source
				'value'				=> 'Email',
			);
		$zoho_order['terms_default'] = true;
		
		// Iterate Through Items
		foreach ($order['items'] as $item ) {
			if (!empty($item['zoho_id']) && !empty($item['qty']) ) {
				$zoho_line = array();
				// Do we need the product id or the variation id?
				$zoho_line ['item_id'] = $item['zoho_id'];
				
				$zoho_line ['quantity'] = $item['qty'];
				$zoho_line ['rate'] = $item['zoho_cost'];
				if ($zoho_line ['item_id'] !== false) {
					$zoho_order ['line_items'][] = $zoho_line;
				}
			}
		}

		return $zoho_order;

	}

	// order2_action called to process second form
	
	public function order2_action () {
		$this->options->reload();
		$options = $this->options->getall();
		if ($options['confirm']=='on') {
			$zoho_order = $this->build_zoho_order ($options['customer'], $options['ship_address'], $options['order']);
			// $this->options->update (array('data'=>$zoho_order));
					
			$zoho = new zoho_connector();		
			$result = $zoho->create_salesorder ($zoho_order, $confirm=false);  // saves as draft
			
			if (!is_wp_error($result) ) {
				$this->options->set_admin_notice ('Order processed successfully', 'success');
				$this->options->delete('result');
			} else {
				$this->options->set_admin_notice ('Order processing failed', 'error');
				$this->options->update (array('result'=>$result));
			}
		}
		$this->options->delete(array('file_to_upload', 'customer', 'format', 'ordertext','nextform', 'order', 'ship_address', 'confirm', 'reference'));
		
	}
	
	private function build_order_html ($order) {
		$order_table = '<table><tr>';
		$order_table .= '<th>ISBN</th>';
		$order_table .= '<th>Title</th>';
		$order_table .= '<th>Qty</th>';
		$order_table .= '<th>Stock</th>';
		$order_table .= '<th>Cost</th>';
		$order_table .= '<th>Zoho Cost</th>';
		$order_table .= '<th>Warnings</th>';
		$order_table .= '</tr>';
		
		foreach ($order['items'] as $line) {
			$order_table .= '<tr>';
			$order_table .= '<td>'.(isset($line['isbn'])?$line['isbn']:'').'</td>';
			$order_table .= '<td>'.(isset($line['title'])?$line['title']:'');
			if (isset($line['zoho_name'])) $order_table .= "<br>{$line['zoho_name']}";
			$order_table .= "</td>";
			$order_table .= '<td>'.(isset($line['qty'])?$line['qty']:'').'</td>';
			$order_table .= '<td>'.(isset($line['stock'])?$line['stock']:'').'</td>';
			$order_table .= '<td>'.(isset($line['cost'])?$line['cost']:'').'</td>';
			$order_table .= '<td>'.(isset($line['zoho_cost'])?$line['zoho_cost']:'').'</td>';
			$order_table .= '<td>'.(isset($line['warning'])?$line['warning']:'').'</td>';
			$order_table .= '</tr>';
		}
		$order_table .= '<tr><td></td><td></td><td></td><td>Totals</td>';
		$order_table .= '<td>'.(isset($order['total'])?$order['total']:'').'</td>';
		$order_table .= '<td>'.(isset($order['zoho_total'])?$order['zoho_total']:'').'</td>';
		$order_table .= '</tr></table>';
		return $order_table;
	}


	public function render ($values = array()) {
		$this->options->reload();
		$order = $this->options->get('order');
		if (!empty($order))
			$this->form['text_before'] .= $this->build_order_html ($order);
		if (isset($this->form['fields']['ship_address'])) {
			$s = isset($order['postcode'])?' for postcode '.$order['postcode']:'';
			$this->form['fields']['ship_address']['title'] = "Select shipping address$s:";
		}
		//echo '<br><pre>'.print_r($this->form, true).'</pre>';
		parent::render();
	}
	
	
	
	public function display_data () {
	
			$options = array ('Options'=>$this->options->getall());
			echo '<pre>'; print_r ($options); echo '</pre>';
			$this->options->delete('data');


	}
}
?>