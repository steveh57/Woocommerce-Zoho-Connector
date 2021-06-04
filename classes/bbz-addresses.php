<?php
/**
 * bbz_addresses Class
 *
 * Manages the bbz address data saved in user meta data
 *
 * This is used as an interface between the addresses held in Woocommerce
 * and the backend Zoho addresses.
 *
 *****************
 * THIS MODULE NEEDS REVIEW
 *
 * Need to review how addresses are added to the guest account and what happens when an address on an order cannot be found in zoho.
 * Sometimes if an address has been modified by the user when placing an order, the order is linked to the guest account.
 *
 * We're also hitting a problem with zoho where there are too many addresses on the guest account, so need to delete it after use.
 *
 ********************
 *
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
		$this->user_meta = new bbz_usermeta($user_id);  // defaults to current user if blank
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
	// Zoho will reject addresses containing certain characters, use this list to eliminate them.
	private function zoho_clean ($input_string) {
		return str_replace(array("$", "%", "#", "<", ">", "|", "&"), "", $input_string);
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
	
	/******
	* add_address
	*
	* Adds a new address to zoho and updates the bbz addresses in user meta
	* $type is billing or shipping
	* $address_id is name used by thwma (e.g. address_1)
	*
	****/

	private function add_address ($woo_address, $type, $address_id) {
		// convert woo address to zoho format
		$zoho_address = $this->get_zoho_address ($woo_address, $type);
		$zoho_contact_id = $this->user_meta->get_zoho_id();

		$zoho = new zoho_connector;
		$result = $zoho->add_address ($zoho_contact_id, $zoho_address);
		//bbz_debug (array($result, $zoho_address, $zoho_contact_id), 'After zoho add_address', false);
		if (is_wp_error ($result)) {
			$result->add('bbz-adr-001', 'bbz_addresses->add_address failed', array (
				'woo_address'=> $woo_address,
				'type' => $type,
				'address_id', $address_id));
			return $result;
		} else {
			$woo_address [$type.'_zoho_id'] = $result ['address_id'];
			return $this->user_meta->update_bbz_address ($type, $address_id, $woo_address);
		}
		//bbz_debug ($user_meta->get_bbz_addresses(), 'BBZ_ADDRESSES after add', false);
	}

	/******
	*	add_guest_address
	*
	* Adds a new address to zoho and returns the id.
	* $type is billing or shipping
	* $address_id is name used by thwma (e.g. address_1)
	*
	****/

	private function add_guest_address ($woo_address, $type, $zoho_guest_id) {
		// convert woo address to zoho format
		$zoho_address = $this->get_zoho_address ($woo_address, $type);
		$zoho = new zoho_connector;
		// add to zoho guest account
		$result = $zoho->add_address ($zoho_guest_id, $zoho_address);
		if (is_wp_error ($result)) {
			$result->add('bbz-adr-002', 'bbz_addresses->add_guest_address failed', array (
				'woo_address'=> $woo_address,
				'type' => $type,
				'guest_id', $zoho_guest_id));
			return $result;
		} else {
			return $result ['address_id'];
		}
	}

	/******
	*	delete_guest_address
	*
	* Unlinks the guest address from the guest account in zoho to stop the max number of additional addresses being exceeded.
	* BUT DOESN'T WORK - GETS ERROR MESSAGE The HTTP method DELETE is not allowed for the requested resource
	*
	****/

	public function delete_guest_address ($zoho_guest_id, $zoho_address_id) {
		$zoho = new zoho_connector;
		// delete from zoho guest account
		$result = $zoho->delete_address ($zoho_guest_id, $zoho_address_id);
		if (is_wp_error ($result)) {
			$result->add('bbz-adr-102', 'bbz_addresses->delete_guest_address failed');
			return $result;
		} else {
			return true;
		}
	}
	
	
	/******
	*	update_address
	*
	* Updates an existing address in zoho and updates the bbz addresses in user meta
	* $type is billing or shipping
	* $address_id is name used by thwma (e.g. address_1)
	*
	****/

	private function update_address ($woo_address, $type, $address_id) {
		
		// convert woo address to zoho format
		$zoho_address = $this->get_zoho_address ($woo_address, $type);
		
		$zoho_contact_id = $this->user_meta->get_zoho_id();
		//get zoho address id from saved address
		$address_id = $this->bbz_addresses[$type][$address_id][$type.'_zoho_id'];

		// now load update to zoho api
		$zoho = new zoho_connector;
		$result = $zoho->update_address ($zoho_contact_id, $zoho_address, $address_id);
		if (is_wp_error ($result)) {
			$result->add ('bbz-adr-003', 'bbz_addresses->update_address failed', array (
				'zoho contact id'=> $zoho_contact_id, 
				'zoho address' => $zoho_address,
				'address id' => $address_id));
			return $result;
		} else {
			$woo_address [$type.'_zoho_id'] = $result ['address_id'];
			return $this->user_meta->update_bbz_address ($type, $address_id, $woo_address);
		}
	}	

	/*****
	* 	get_woo_address  (zoho to woo)
	*
	*	uses fields from zoho contact record (as returned from zoho api)
	*	to create a woo format address array.
	*	$type is shipping, billing or addresses
	* 	$index is only applicable for type=addresses
	*****/

	private function get_woo_address ($zoho_contact, $type, $index='') {
		//$this->_display ($zoho_contact);
		$woo_address = array();
		if ($type == 'billing' || $type == 'shipping') {
			$zoho_address = $zoho_contact [$this->get_zoho_address_name($type)];
		} elseif (isset($zoho_contact['addresses'][$index])) {
			$zoho_address = $zoho_contact ['addresses'][$index];
			$type = 'shipping';  // use shipping prefix for additional addresses
		} else return false;
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
	// Zoho will reject addresses if they contain certain characters, use $this->invalid_zoho_characters to eliminate them.
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
						// if first_name isn't 'Attention:' or similar
						if (!stristr ($woo_address[$woo_field_name], 'attention' )) { 
							$zoho_address['attention'] = $this->zoho_clean ($woo_address [$woo_field_name]).' '.$zoho_address['attention'];
						};
						break;
						
					case 'lastname' :
						  // add to attention field
						$zoho_address['attention'] .= $this->zoho_clean ($woo_address [$woo_field_name]);
						break;
						
					case 'company' : // no company field in zoho address
						$zoho_address['address'] = $this->zoho_clean ($woo_address [$woo_field_name]).' '.$zoho_address['address'];
					
					case 'country':  //wc has country code - eg GB
						$zoho_address[$zoho_field_name] = WC()->countries->get_countries()[ $woo_address [$woo_field_name] ];
						break;
						
					default:
						$zoho_address[$zoho_field_name] = $this->zoho_clean ($woo_address [$woo_field_name]);
				}
			}
		}
		//bbz_debug (array ($woo_address, $zoho_address), 'bbz_translate_address_w2z');
		return $zoho_address;
	}
	/*****
	* is_same - compare two woo format addresses with field name prefixes $type
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
	
	/*****
	* load_from_zoho_contact
	* 
	* Loads addresses from the zoho contact dataset to user meta
	* Also forces thwma(Woocommerce Multiple Address plugin) to set up its user meta address array
	*
	* $zoho_contact	array containing data returned from zoho
	*****/
	
	public function load_from_zoho_contact ($zoho_contact) {
		bbz_debug ($zoho_contact, 'Zoho Contact', false);
		// Check if Thwma installed and clear existing array
		// if you don't do this, update_address_to_user throws an error
		$user_id = $this->user_meta->get_user_id();

		if (class_exists( 'THWMA_Utils')) {
			update_user_meta ($user_id, THWMA_Utils::ADDRESS_KEY, array());
		}

		// load default woocommerce shipping and billing addresses
		foreach ($this->address_types as $type) {
			$woo_address = $this->get_woo_address ($zoho_contact, $type);
			//bbz_debug ($woo_address, $type, false);
			// save default billing/shipping woo addresses
			$this->user_meta->update_woo_address ($type, $woo_address);
			$this->user_meta->update_bbz_address ($type, 'address_0', $woo_address);

			// if thwma installed, call thwma util functions to add address
			// adding to thwma array will also trigger a call to update_from_thwma
			// to update the bbz_addresses array.
			if (class_exists( 'THWMA_Utils')) {
				//$default_address = THWMA_Utils::get_default_address($user_id, $type);
				//bbz_debug ($default_address, 'THWMA address', False);
				$woo_address[$type.'_heading'] = '';
				THWMA_Utils::update_address_to_user ( $user_id, $woo_address, $type, 'address_0');
			}
		}
		
		// Load additional addresses as extra shipping addresses
		if (isset($zoho_contact['addresses'] )) {
			foreach ($zoho_contact['addresses'] as $key=>$address) {
				$woo_address = $this->get_woo_address ($zoho_contact, 'addresses', $key);
				$address_id = 'address_'.($key+1);
				$this->user_meta->update_bbz_address ('shipping', $address_id, $woo_address);
				if (class_exists( 'THWMA_Utils')) {
					//$default_address = THWMA_Utils::get_default_address($user_id, $type);
					//bbz_debug ($default_address, 'THWMA address', False);
					$woo_address['shipping_heading'] = '';
					THWMA_Utils::update_address_to_user ( $user_id, $woo_address, 'shipping', $address_id);
				}
			}
		}
	
		$this->bbz_addresses = $this->user_meta->get_bbz_addresses();
		//bbz_debug ($this->bbz_addresses, 'bbz_addresses after load', false);
		//bbz_debug (get_user_meta ($user_id, THWMA_Utils::ADDRESS_KEY, true), 'thwma addresses after load', false);

	}
	
	/*****
	* get_zoho_address_id
	*
	* gets the zoho address id matching the address fields from the order.
	* if user is not logged in we create a new address for the guest user.
	*
	* $order_address	array containing the order address fields (billing or shipping)
	* $type				string 'billing' or 'shipping'
	* $guest_zoho_id	if specified, a new zoho address is created for this id
	*					if blank, attempt to match to an existing address
	*****/
	
	public function get_zoho_address_id ($order_address, $type, $guest_zoho_id='') {
		// add type (shipping or billing) prefix to fields to match woo format
		$woo_address = array();
		foreach ($order_address as $key=>$value) {
			$woo_address [$type.'_'.$key] = $value;
		}
		$zoho_address_id = '';
		// is this a guest user?
		if (!empty($guest_zoho_id) ) {
			// create new address for guest user
			$zoho_address_id = $this->add_guest_address ($woo_address, $type, $guest_zoho_id);
		
		} else { // registered user
			// find match - address should have been created for user already 
			
			if (!empty ($this->bbz_addresses[$type])) {
				foreach ($this->bbz_addresses[$type] as $address_id=>$bbz_address) {
					if ($this->is_same ($woo_address, $bbz_address, $type) ) {
						// match found
						$zoho_address_id = $bbz_address[$type.'_zoho_id'];
						break;
					}
				}
			}
			
		}
		if (empty($zoho_address_id))  {
			return new WP_Error ('bbz-adr-004','Registered user address id not found', array(
				'order address'=>$order_address,
				'woo Address'=>$woo_address,
				'type'=>$type,
				'guest zoho id'=>$guest_zoho_id,
				'bbz_addresses'=>$this->bbz_addresses)); 
		} else {
			return $zoho_address_id;
		}
	}
 
} //class bbz_address 
?>