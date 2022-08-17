<?php
/******
 * bbz_options Class
 * Management of bbz options data.
 * Note that some modules written before this class was set up access
 * options data directly.
 *
 ******/
 
// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class bbz_options {
	private $options;
	
	function __construct() {
		$this->options = get_option ( BBZ_OPTION_NAME);
		if(!$this->options || !is_array($this->options) ) $this->options =array();
	}
	public function reload() {
		$this->options = get_option ( BBZ_OPTION_NAME);
	}

	
	public function get ($option_name='') {
		return isset($this->options [$option_name]) ? $this->options [$option_name] : false;
	}
	public function getall () {
		return $this->options;
	}
	
	public function update ($option_name='', $value, $save=false) {
		$this->options [$option_name] = $value;
		if ($save) $this->save();
	}
	
	public function delete ($option_name='', $save=false) {
		unset ($this->options [$option_name]);
		if ($save) $this->save();

	}
	
	public function save () {
		update_option (BBZ_OPTION_NAME, $this->options );
	}
	
	public function reset () {
		$this->options = array();
		$this->save();
	}
		
	
	public function set_admin_notice ($msg='message', $type='error') {
		if ( in_array ( $type, array ('error', 'warning', 'success', 'info'))) {
			$this->options ['admin_notice'] = $type;
			$this->options ['admin_message'] = $msg;
			$this->save();
		}

	}
	
	public function get_admin_notice () {
		if (isset ( $this->options['admin_notice']) && //isset ($this->options ['admin_message']) &&
			in_array ( $this->options['admin_notice'], array ('error', 'warning', 'success', 'info'))) {
			return $this->options['admin_notice'];
		}
		return false;
	}
	public function get_admin_message () {
		if (isset ( $this->options['admin_message'])) {
			return $this->options['admin_message'];
		}
		return false;
	}
	public function clear_admin_notice () {
		unset ( $this->options['admin_notice'] );
		unset ( $this->options['admin_message'] );
		$this->save();
		return true;
	}

}
 
 