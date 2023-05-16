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

	/*******
	*	get - get one or more options
	*	$option	name of option, or array of option names
	*	$select	'any' returns only matching options that are set
	*			'all' returns false if any of the specified options are not set
	******/
	
	public function get ($option, $select='any') {
		if (is_array ($option) ) {
			foreach ($option as $option_name) {
				if (!isset ($this->options [$option_name])) {
					if ($select == 'all') return false;
				} else {
					$result [$option_name] = $this->options [$option_name];
				}
			}
			return $result;
		} else {
			return isset($this->options [$option]) ? $this->options [$option] : false;
		}
	}

	/*******
	*	is_set - check one or more options are set
	*	$option	name of option, or array of option names
	*	Returns true only if all specified options are set
	******/	
	public function is_set ($option) {  //if $option is an array, all options must be set to return true
		if (is_array ($option) ) {
			foreach ($option as $option_name) {
				if (!isset ($this->options [$option_name])) return false;
			}
			return true;
		} else {
			return isset($this->options [$option]);
		}
	}
	
	public function getall () {
		return $this->options;
	}
	/*******
	*	update - update one or more options 
	*	$option	array of option pairs
	******/	
	
	public function update ($option, $save=true) {
		foreach ($option as $key=>$value) {
			$this->options [$key] = $value;
		}
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
 
 