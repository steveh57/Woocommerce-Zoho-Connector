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
		$this->options = get_option ( OPTION_NAME);
		if(!$this->options || !is_array($this->options) ) $this->options =array();
	}
	
	public function get ($option_name='') {
		return isset($this->options [$option_name]) ? $this->options [$option_name] : false;
	}
	
	public function update ($option_name='', $value) {
		$this->options [$option_name] = $value;
	}
	
	public function delete ($option_name='') {
		unset ($this->options [$option_name]);
	}
	
	public function save () {
		update_option (OPTION_NAME, $options);
	}

	
}
 
 