<?php
/**
 * bbz_addresses Class
 *
 * Manages the bbz address data saved in user meta data
 *
 * This is used as an interface between the addresses held in Woocommerce
 * and the backend Zoho addresses.
 *
 * Revision May 22 - no longer using ThemeHigh Multiple Addresses (THWMA)
 * * simplify structure and just hold one shipping address for each user.
 *
 *
 ********************
 *
 * The Woocommerce default billing and shipping addresses are held explicitly in user metadata
 *		For these each entry is stored as billing_<name> or shipping_<name>
 * The corresponding Zoho address ids are stored in user metadata as
 *	shipping_zoho_id
 *	billing_zoho_id
 *
 * When a woocommerce user is first linked to a zoho customer a new shipping address is created in zoho and linked specifically to the user,
 * and whenever the user places a sales order the users zoho address is updated.
 *
 *****/
 // If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class bbz_addresses {
	
	private $user_meta;
	
 	private static $zoho_field_map = array ( 
		// zoho name to woo name - add shipping or billing prefix
		'address_id'	=>	'zoho_id',
		'firstname'		=>	'first_name',
		'lastname'		=>	'last_name',
		'company'		=>	'company',
		'address'		=> 	'address_1',
		'street2'		=>	'address_2',
		'city'			=>	'city',
		'zip'			=>	'postcode',
		'state'			=>	'state',
		'country'		=>	'country',
		'phone'			=>	'phone'
	);
	private static $woo_field_map = array ( 
		// woo name to zoho name - add shipping or billing prefix for user meta names
		//'zoho_id' 		=>	'address_id',
		'first_name'	=>	array('attention','before'),
		'last_name'		=>	array('attention','after'),
		'company'		=>	array('address','line1'),
		'address_1'		=> 	array('address','line2'),
		'address_2' 	=>	'street2',
		'city'			=>	'city',
		'postcode'		=>	'zip',
		'state'			=>	'state',
		'country'		=>	'country',
		'phone'			=>	'phone',
		'email'			=>	'fax'	// this is an extra field created so we get the email into zoho
	);
	private static $zoho_address_types = array ('shipping', 'billing');
	
	function __construct ($user_id = '') {
		$this->user_meta = new bbz_usermeta($user_id);  // defaults to current user if blank
	}

	// utility functions
	
	/******
	* get_woo_address_fields
	*
	* $type	prefix (shipping or billing) used in user meta and elsewhere - may be omitted
	*
	* returns an array of <woo_field_name> => <type>'_'<woo_field_name>
	* if type is blank or omitted the value field is just the field name without a prefix
	******/
	public static function get_woo_address_fields ($type='') {
		$woo_fields = array();
		foreach (bbz_addresses::$woo_field_map as $woo_suffix=>$zoho_field) {
			// copy field with type prefix, but excude zoho id from list
			if (!($woo_suffix === 'zoho_id')) {
				$woo_fields[$woo_suffix] = empty($type) ? $woo_suffix : $type.'_'.$woo_suffix;
			}
		}
		return $woo_fields;
	}
	
	// Zoho will reject addresses containing certain characters, use this list to eliminate them.
	private function zoho_clean ($input_string) {
		$bad = array("&", "$", "%", "#", "<", ">", "|", "=");
		$replace = array (' and ', '', '', '', '', '', ' ', '-');
		return str_replace($bad, $replace, $input_string);
	}
	
	/******
	*	woo_to_zoho
	*
	*	convert a woo format address to zoho format
	*
	*  Zoho will reject addresses if they contain certain characters, use
	*  $this->zoho_clean to eliminate them.
	******/
	
	public function woo_to_zoho ($woo_address) {
		$zoho_address = array ();
		
		foreach ($this::$woo_field_map as $woo_field_name => $zoho_field_name) {
			//$woo_field_name = $type.$woo_suffix;
			if (!empty ($woo_address[$woo_field_name]) && !empty ($zoho_field_name)) {
				// create a clean version of the woo field, and convert country codes
				if ($woo_field_name == 'country') { //wc has country code - eg GB
					$clean = $this->zoho_clean (WC()->countries->get_countries()[ $woo_address [$woo_field_name] ]);
				} else {
					$clean = $this->zoho_clean ($woo_address [$woo_field_name]);
				}
				// some woo fields have to be combined to fit the zoho address model
				if (is_array ($zoho_field_name)) {
					if (empty($zoho_address[$zoho_field_name[0]])) {
						$zoho_address[$zoho_field_name[0]] = $clean;
					} else {
						switch($zoho_field_name[1]) {
							case 'before':
								$zoho_address[$zoho_field_name[0]] = $clean.' '.$zoho_address[$zoho_field_name[0]];
								break;
							case 'after':
								$zoho_address[$zoho_field_name[0]] .= ' '.$clean;
								break;
							case 'line1':
								$zoho_address[$zoho_field_name[0]] = $clean."\n".$zoho_address[$zoho_field_name[0]];
								break;
							case 'line2':
								$zoho_address[$zoho_field_name[0]] .= "\n".$clean;
								break;
						}
					}
				} else {
					$zoho_address[$zoho_field_name] = $clean;
				}
			}
		}
		//bbz_debug (array ($woo_address, $zoho_address), 'bbz_translate_address_w2z');
		return $zoho_address;
	}

	
	/******
	* add_zoho_address
	*
	* Adds a new address to zoho and returns zoho address id
	* $woo_address array(suffix only, no shipping/billing prefix key values)
	* $zoho_contact_id if blank defaults to current user's soho id
	*
	* Returns wp_error or zoho address id
	*	*
	* Only used locally apart form in test form, so could be private
	*
	****/

	public function add_zoho_address ($woo_address, $zoho_contact_id='') {
		// convert woo address to zoho format
		$zoho_address = $this->woo_to_zoho ($woo_address);
		if (empty($zoho_contact_id)) $zoho_contact_id = $this->user_meta->get_zoho_id();

		$zoho = new zoho_connector;
		$result = $zoho->add_address ($zoho_contact_id, $zoho_address);
		//bbz_debug (array($result, $zoho_address, $zoho_contact_id), 'After zoho add_address', false);
		if (is_wp_error ($result)) {
			$result->add('bbz-adr-001', 'bbz_addresses->add_zoho_address failed', 
				array (
					'woo_address'=> $woo_address,
					'zoho_address'=> $zoho_address));
			return $result;
		} else {
			return $result ['address_id'];
		}
	}

	/******
	*	delete_address
	*
	*  Deletes an address in zoho
	*
	****/

	public function delete_address ($zoho_guest_id, $zoho_address_id) {
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
	* update_zoho_address
	*
	* Updates an existing address in zoho and updates the bbz addresses in user meta
	* $Woo_address is the woo format address
	* $address_id is the zoho address id to be updated
	* $type = 'shipping' or 'billing'
	* $zoho_contact_id
	*
	* Returns wp_error or zoho address id
	*
	****/

	private function update_zoho_address ($woo_address, $address_id, $type, $zoho_contact_id) {
		
		// convert woo address to zoho format
		$zoho_address = $this->woo_to_zoho ($woo_address);
		
		// now load update to zoho api
		$zoho = new zoho_connector;
		$result = $zoho->update_address ($zoho_contact_id, $zoho_address, $address_id);
		if (is_wp_error ($result)) {
			// try creating a new address (old one might have been deleted)
			$result = $zoho->add_address ($zoho_contact_id, $zoho_address);
		};
		if (is_wp_error ($result)) {
			$result->add ('bbz-adr-003', 'bbz_addresses->update_zoho_address failed', array (
				'zoho contact id'=> $zoho_contact_id, 
				'zoho address' => $zoho_address,
				'address id' => $address_id));
			return $result;
		} else { //address successfully added or updated
			// now record address id in usermeta
			if (in_array($type, $this::$zoho_address_types)) {
				$this->user_meta->update_zoho_address_id ($type, $result['address_id']);
				return $result['address_id'];
			}
		}
	}	

	private $zoho_address_names = array (
		'billing'		=> 'billing_address',
		'shipping'		=> 'shipping_address',
	);
	/*****
	* zoho_address_to_woo
	*
	* $zoho_address	address array from zoho contact or salesorder
	* Returns woo format address array
	*
	******/
	
	public static function zoho_address_to_woo ($zoho_address) {
			// special handling for first and last name for billing and shipping
		// zoho just has one field 'attention' so,
		// if set and two words, we map these onto first and last names
		// otherwise we enter first 'Attention:, last zoho attention field
		// if empty we use the user's first and last name - see below
		if (!empty ($zoho_address ['attention'])) {
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
		
		foreach (bbz_addresses::$zoho_field_map as $zoho_field => $woo_field) {
			if ( empty ( $zoho_address[$zoho_field])) {
				// no data for this field from zoho
				switch ($zoho_field) {
					case 'country':
						$woo_address [$woo_field] = 'GB';  // default country to GB if blank
						break;
					default:
						$woo_address [$woo_field] = '';
				}
			} else {
				if ($zoho_field == 'country'  && $zoho_address[$zoho_field] == 'United Kingdom' ) { //translate country name to woo code
					$woo_address [$woo_field] = 'GB'; //$zoho_country_map[$zoho_address[$zoho_field]];
				} else {
					$woo_address [$woo_field] = $zoho_address[$zoho_field];
				}
			}
		}
		return $woo_address;
	}
	
	/*****
	* 	zoho to woo
	*
	*	uses fields from zoho contact record (as returned from zoho api)
	*	to create a woo format address array.
	*	$type is shipping, billing or addresses
	* 	$index is only applicable for type=addresses
	*****/
	
	private function zoho_to_woo ($zoho_contact, $type, $index='') {
		//$this->_display ($zoho_contact);
		$woo_address = array();
		if ($type == 'billing' || $type == 'shipping') {
			$zoho_address = $zoho_contact [$this->zoho_address_names[$type]];
		} elseif (!empty ($index) && isset($zoho_contact['addresses'][$index])) {
			$zoho_address = $zoho_contact ['addresses'][$index];
			$type = 'shipping';  // use shipping prefix for additional addresses
		} else return false;
		$zoho_address['company'] = $zoho_contact ['company_name'];
		$userdata = get_userdata ( $this->user_id );
		if (empty ($zoho_address ['attention'])) {
			$zoho_address ['attention'] = $userdata->first_name.' '.$userdata->last_name;
		}
		if (empty ($zoho_address ['phone'])) {
			$zoho_address ['phone'] = $zoho_contact['phone'];
		}
		return $this->zoho_address_to_woo ($zoho_address);
	}
	/*****
	* is_same - compare two woo format addresses with field name prefixes $type
	*
	* This function isn't used anywhere, so set to private for now
	******/
	
	private function is_same ($address1, $address2, $type='') {
		$match = true;
		//bbz_debug ($this->get_woo_address_fields($type), 'Address fields');
		foreach ($this->get_woo_address_fields($type) as $woo_name=>$fieldname) {
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
	* Called when user first linked to zoho customer.
	*
	* $zoho_contact	array containing data returned from zoho
	*****/
	
	public function load_from_zoho_contact ($zoho_contact) {
		bbz_debug ($zoho_contact, 'Zoho Contact', false);
		$user_id = $this->user_meta->get_user_id();

		// load default woocommerce shipping and billing addresses into woo usermeta
		foreach (array ('billing', 'shipping') as $type) {
			$woo_address = $this->zoho_to_woo ($zoho_contact, $type);
			//bbz_debug ($woo_address, $type, false);
			// save default billing/shipping woo addresses
			$this->user_meta->update_woo_address ($type, $woo_address);
		}
		
		// now create a new address in zoho specifically for this user
		
		$woo_address = $this->user_meta->get_woo_address ('shipping');
		// Add in the email address - saved to the fax field in zoho
		$woo_address['email'] = get_userdata ($user_id) ->user_email;
		$result = $this->add_zoho_address ($woo_address);
		if (is_wp_error ($result)) {
			$result->add ('bbz-adr-004', 'bbz_addresses->add_zoho_address failed', array (
				'zoho contact'=> $zoho_contact, 
				'woo address' => $woo_address));
				
		} else {
			$woo_address ['zoho_id'] = $result;
			$this->user_meta->update_woo_address ('shipping', $woo_address);
		}
			
		return $result;
		
	}
	
	/*****
	* get_zoho_address_id
	*
	* gets the zoho address id matching the address fields from the order.
	* if user is not logged in we create a new address for the guest user.
	*
	* $order_address	array containing the order address fields (billing or shipping)
	* $type				string 'billing' or 'shipping'
	* $zoho_contact_id
	* $guest			true if this is a guest user - if so a new zoho address is created
	*****/
	
	public function get_zoho_address_id ($order_address, $type, $zoho_contact_id, $guest=false) {

		// is this a guest user?
		if ($guest ) {
			// create new address for guest user
			$result = $this->add_zoho_address ($order_address, $zoho_contact_id);
		} else { // registered user
			// update the address in zoho to make sure it matches the order
			$result = $this->update_zoho_address ($order_address, $this->user_meta->get_zoho_address_id ($type), $type, $zoho_contact_id,);
		}
		if (is_wp_error ($result)) {
			$result->add ('bbz-adr-004','get_zoho_address_id failed', array(
				'order address'=>$order_address,
				'type'=>$type,
				'contact_id'=>$zoho_contact_id,
				'guest'=>$guest)); 
		}
		return $result;
	}
 
} //class bbz_address 
?>