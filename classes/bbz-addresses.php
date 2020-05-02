<?php
/**
 * bbz_addresses Class
 *
 * Manages the bbz address data saved in user meta data
 *
 * This is used as an interface between the addresses held in Woocommerce
 * and the backend Zoho addresses.
 * The Woocommerce addresses are held in two ways:
 * a) the default billing and shipping addresses held explicitly in user metadata
 *		For these each entry is stored as billing_<name> or shipping_<name>
 
 * b) the multiple addresses stored by the Themehigh Woocommerce Multiple Address (thwma) plugin and
 *    stored in user metadata in an array under key thwma_custom_address
 * Example format:
		 Array(
            [billing] => Array(
                    [address_0] => Array (
                            [billing_heading] => 
                            [billing_first_name] => John
                            [billing_last_name] => Doe
                            [billing_company] => 
                            [billing_country] => GB
                            [billing_address_1] => Spitalfields Arts Market
                            [billing_address_2] => 
                            [billing_city] => London
                            [billing_state] => London
                            [billing_postcode] => E1 6RL
                            [billing_phone] => 
                            [billing_email] => steve@unilake.co.uk
                        )
                    [address_1] => Array (
                            [billing_first_name] => Steve
                            [billing_last_name] => Haines
                            [billing_company] => 
                            [billing_country] => GB
                            [billing_address_1] => 24 Wroxham Road
                            [billing_address_2] => Coltishall
                            [billing_city] => Norwich
                            [billing_state] => 
                            [billing_postcode] => NR12 7EA
                            [billing_phone] => 
                            [billing_email] => steve@unilake.co.uk
                            [billing_heading] => 
                        )
                )
            [shipping] => Array (
                    [address_0] => Array (
                            [shipping_first_name] => John
                            [shipping_last_name] => Doe
                            [shipping_company] => 
                            [shipping_country] => GB
                            [shipping_address_1] => Spitalfields Arts Market
                            [shipping_address_2] => 
                            [shipping_city] => London
                            [shipping_state] => London
                            [shipping_postcode] => E1 6RL
                            [shipping_phone] => 01394 388890
                            [shipping_heading] => 
                        )
                    [address_1] => Array (
                            [shipping_first_name] => Steve
                            [shipping_last_name] => Haines
                            [shipping_company] => 
                            [shipping_country] => GB
                            [shipping_address_1] => 24 Wroxham Road
                            [shipping_address_2] => Coltishall
                            [shipping_city] => Norwich
                            [shipping_state] => 
                            [shipping_postcode] => NR12 7EA
                            [shipping_phone] => 
                        )
                )
            [default_shipping] => address_1
        )
 *
 *****/
 // If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class bbz_addresses {
	
	private $bbz_addresses = array ();
	private $user_meta;
	
 	private $zoho_field_map = array ( 
		// zoho name to woo name - add shipping or billing prefix
		'address_id'	=>	'_zoho_id',
		'firstname'		=>	'_first_name',
		'lastname'		=>	'_last_name',
		'company'		=>	'_company',
		'address'		=> 	'_address_1',
		'street2'		=>	'_address_2',
		'city'			=>	'_city',
		'zip'			=>	'_postcode',
		'state'			=>	'_state',
		'country'		=>	'_country',
		'phone'			=>	'_phone'
	);
	private $address_types = array ('billing', 'shipping');
	
	private $zoho_address_names = array (
		'billing'		=> 'billing_address',
		'shipping'		=> 'shipping_address',
	);
	
	function __construct ($user_id = '') {
		$this->user_meta = new bbz_usermeta($user_id);
		$this->bbz_addresses = $this->user_meta->get_bbz_addresses();
	}

	// utility functions
 	public function get_address_field_map () {
		return $this->zoho_field_map;
	}
	public function get_w2z_address_field_map ($type) {
		$w2z_map = array();
		foreach ($this->zoho_field_map as $zoho_name=>$woo_suffix) {
			$w2z_map [$type.$woo_suffix] = $zoho_name;
		}
		return $w2z_map;
	}
	public function get_woo_address_fields ($type) {
		$w2z_fields = array();
		foreach ($this->zoho_field_map as $zoho_name=>$woo_suffix) {
			// copy field with type prefix, but excude zoho id from list
			if (!($woo_suffix === '_zoho_id')) $w2z_fields[] = $type.$woo_suffix;
		}
		return $w2z_fields;
	}
	private function get_zoho_address_name ($type) {
		return $this->zoho_address_names [$type];
	}
	private function get_address_types () {
		return $this->address_types;
	}


	/*****
	*  update_from_thwma
	*
	*  Update bbz addresses, including update to zoho, from thwma address structure
	*	called when thwma updates its usermeta array
	*****/	
	public function update_from_thwma ($thwma_addresses) {
		//bbz_debug (array($thwma_addresses, $this->bbz_addresses), 'update_from_thwma');
		//compare thwma addresses with stored bbz addresses
		foreach ( $this->get_address_types() as $type) { //shipping and billing
			if(isset($thwma_addresses[$type])) {
				foreach ($thwma_addresses[$type] as $address_id=>$thwma_address) {
					// is there a corresponding bbz address?
					if (!isset ($this->bbz_addresses[$type][$address_id] )) {  // no matching bbz address, so need to add it
						//bbz_debug ($thwma_address, 'About to add', false);
						$this->add_address ($thwma_address, $type, $address_id);
					} else {
						// the address already exists, so we need to check if there are any changes
						// compare thwma address to stored bbz address
						//bbz_debug (array($this->bbz_addresses[$type][$address_id], $thwma_address), 'About to compare', false);
						if (!$this->is_same ($this->bbz_addresses[$type][$address_id], $thwma_address, $type)) {
							// if address has changed, update it in zoho
							$this->update_address ($thwma_addresses[$type][$address_id], $type, $address_id);
						}
					}
				}
			}
		}
		//bbz_debug ($this->user_meta->get_bbz_addresses(), 'BBZ_ADDRESSES after updates');

		// now let thwma carry on...
	}

	private function add_address ($woo_address, $type, $address_id) {
		// convert woo address to zoho format
		$zoho_address = $this->get_zoho_address ($woo_address, $type);
		$zoho_contact_id = $this->user_meta->get_zoho_id();

		$zoho = new zoho_connector;
		$result = $zoho->add_address ($zoho_contact_id, $zoho_address);
		//bbz_debug (array($result, $zoho_address, $zoho_contact_id), 'After zoho add_address', false);
		if (is_array ($result) && isset ($result ['address_id'])) {
			$woo_address [$type.'_zoho_id'] = $result ['address_id'];
			return $this->user_meta->update_bbz_address ($type, $address_id, $woo_address);
		}
		return false;
		//bbz_debug ($user_meta->get_bbz_addresses(), 'BBZ_ADDRESSES after add', false);
	}

	private function update_address ($woo_address, $type, $address_id) {
		
		// convert woo address to zoho format
		$zoho_address = $this->get_zoho_address ($woo_address, $type);
		
		$zoho_contact_id = $this->user_meta->get_zoho_id();
		//get zoho address id from saved address
		$address_id = $this->bbz_addresses[$type][$address_id][$type.'_zoho_id'];

		// now load update to zoho api
		$zoho = new zoho_connector;
		$result = $zoho->update_address ($zoho_contact_id, $zoho_address, $address_id);
		//bbz_debug ($result, 'After zoho update_address', false);
		if (is_array ($result) && isset ($result ['address_id'])) {
			$woo_address [$type.'_zoho_id'] = $result ['address_id'];
			return $this->user_meta->update_bbz_address ($type, $address_id, $woo_address);
		}
		return false;
		//bbz_debug ($user_meta->get_bbz_addresses(), 'BBZ_ADDRESSES after update');
	}	

	/*****
	* 	get_woo_address  (zoho to woo)
	*
	*	uses fields from zoho contact record (as returned from zoho api)
	*	to create a woo format address array.
	*	$type is shipping or billing
	*****/

	private function get_woo_address ($zoho_contact, $type) {
		//$this->_display ($zoho_contact);
		$zoho_address_name = $this->get_zoho_address_name($type);
		$woo_address = array();
		$zoho_address = $zoho_contact [$zoho_address_name];
		$zoho_address['company'] = $zoho_contact ['company_name'];
		$userdata = get_userdata ( $this->user_id );
		
		// special handling for first and last name for billing and shipping
		// zoho just has one field 'attention' so,
		// if set and two words, we map these onto first and last names
		// otherwise we enter first 'Attention:, last zoho attention field
		// if empty we use the user's first and last name - see below
		if (empty ($zoho_address ['attention'])) {
			$zoho_address ['firstname'] = $userdata->first_name;
			$zoho_address ['lastname'] = $userdata->last_name;
		} else {
			$att_split = explode (' ', $zoho_address ['attention']);
			if ( count($att_split) == 2) {
				$zoho_address ['firstname'] = $att_split[0];
				$zoho_address ['lastname'] = $att_split[1];
			} else {
				$zoho_address ['firstname'] = 'Attention:';
				$zoho_address ['lastname'] = $zoho_address ['attention'];
			}
		}
		//$this->_display ($zoho_address);
		//bbz_debug(array($zoho_address, $zoho_contact));
		
		foreach ($this->zoho_field_map as $zoho_field => $woo_field) {
			$woo_field_name = $type.$woo_field;
			if ( empty ( $zoho_address[$zoho_field])) {
				// no data for this field from zoho
				switch ($zoho_field) {
					case 'phone':  // if shipping or billing phone blank, use main contact phone number
						$woo_address [$woo_field_name] = $zoho_contact['phone'];
						break;
					case 'country':
						$woo_address [$woo_field_name] = 'GB';  // default country to GB if blank
						break;
					default:
						$woo_address [$woo_field_name] = '';
				}
			} else {
				if ($zoho_field == 'country'  && $zoho_address[$zoho_field] == 'United Kingdom' ) { //translate country name to woo code
					$woo_address [$woo_field_name] = 'GB'; //$zoho_country_map[$zoho_address[$zoho_field]];
				} else {
					$woo_address [$woo_field_name] = $zoho_address[$zoho_field];
				}
			}
		}
		return $woo_address;
	}
	// convert a woo format address to zoho format.  $type is billing or shipping.
	private function get_zoho_address ($woo_address, $type) {
		$address_map = $this->get_w2z_address_field_map ($type);

		$zoho_address = array ();
		$zoho_address ['attention'] = ''; //set up attention field
		
		foreach ($address_map as $woo_field_name => $zoho_field_name) {
			//$woo_field_name = $type.$woo_suffix;
			if (!empty ($woo_address[$woo_field_name]) && !empty ($zoho_field_name)) {
				switch ($zoho_field_name) {
					// zoho just has an attention field, not separate first and last names.
					case 'firstname' :
						if (!stristr ('attention' ,$woo_address[$woo_field_name])) { // if first_name isn't 'Attention:' or similar
							$zoho_address['attention'] = $woo_address [$woo_field_name].' '.$zoho_address['attention'];
						};
						break;
						
					case 'lastname' :
						$zoho_address['attention'] .= $woo_address [$woo_field_name];  // add to attention field
						break;
					
					case 'country':  //wc has country code - eg GB
						$zoho_address[$zoho_field_name] = WC()->countries->get_countries()[ $woo_address [$woo_field_name] ];
						break;
						
					default:
						$zoho_address[$zoho_field_name] = $woo_address [$woo_field_name];
				}
			}
		}
		//bbz_debug (array ($woo_address, $zoho_address), 'bbz_translate_address_w2z');
		return $zoho_address;
	}
	/*****
	* is_same - compare two woo format addresses
	*
	******/
	
	public function is_same ($address1, $address2, $type) {
		$match = true;
		//bbz_debug ($this->get_woo_address_fields($type), 'Address fields');
		foreach ($this->get_woo_address_fields($type) as $fieldname) {
			if ( !($address1[$fieldname] === $address2[$fieldname])) {
				//bbz_debug (array($address1[$fieldname], $address2[$fieldname]), 'Comparing', false);
				$match = false;
				break;
			}
		}
		return $match;
	}
	
	// load addresses to user meta from zoho contact dataset
	public function load_from_zoho_contact ($zoho_contact) {
		// load default woocommerce shipping and billing addresses
		foreach ($this->address_types as $type) {
			$woo_address = $this->get_woo_address ($zoho_contact, $type);
			//bbz_debug ($woo_address, $type, false);
			// save default billing/shipping woo addresses
			$this->user_meta->update_woo_address ($type, $woo_address);
			$this->user_meta->update_bbz_address ($type, 'address_0', $woo_address);
		}
		$this->bbz_addresses = $this->user_meta->get_bbz_addresses();
		//bbz_debug ($this->bbz_addresses, 'bbz_addresses after load');
	}
 
} //class bbz_address 
?>