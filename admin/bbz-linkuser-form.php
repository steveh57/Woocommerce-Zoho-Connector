<?php
/**
 * Link User form
 *
 * 
 */
 
 // If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class bbz_linkuser_form extends bbz_admin_form {

	private $linkuserform = array (
		'name'		=> 'bbzform-link-user',
		'class'		=> 'bbz_linkuser_form',
		//'action'	=>	'link_user_action',
		'title'		=>	'<h2>Link web user to Zoho customer</h2>',
		'text_before'	=> '<p>Select wehsite and Zoho customers to link.  Linking will update billing and shipping addresses '.
							'in WooCommerce</p>',
		'fields'	=>	array (
			'webuser'			=> array (
				'type'			=> 'select',
				'title'		=> 'Website User',
				'optionfunc'	=> 'web_user_list',
			),
			'zohouser'	=> array (
				'type'		=> 'select',
				'title'		=> 'Zoho Customer',
				'optionfunc'	=> 'zoho_user_list',
			),
		),
		'button'		=> array (
			'name'			=> 'submit',
			'type'			=> 'primary',
			'title'			=> 'Link user'
		)
	);
	
	function __construct () {
		parent::__construct($this->linkuserform);
	}
	
	public function zoho_user_list () {
		$zoho = new zoho_connector;
		return $zoho->get_customer_names ();
	}
	
	public function web_user_list () {
		$webusers = get_users();
		$results [0] = "GUEST USER";  // Insert special case for guest user
		foreach ($webusers as $user) {
			$results [$user->data->ID] = $user->user_email;
		}
		return $results;
	}
	public function action () {
		$this->options->reload();
		$webuser = $this->options->get('webuser');  // wordpress user id
		$zohouser = $this->options->get('zohouser'); //zoho user id
		
		if ($webuser == 0) { // Guest user selected
			$this->options->update(array(BBZ_OP_GUESTID=>$zohouser));
			$result = true;
		} else {
			$result = bbz_link_user ($webuser, $zohouser);
		}
		if (!is_wp_error($result)) {
			$this->options->set_admin_notice ('User linked successfully', 'success');
		} else {
			$this->options->set_admin_notice ('User link failed', 'error');
			$this->options->update (array('data'=>$result));
		}
	}
	
	public function display_data () {
		$webuser = $this->options->get ('webuser');
		if (!empty ($webuser)) {
			echo 'User Meta Data For User ID ', $webuser ;
			echo '<pre>';
			print_r (get_user_meta ($webuser,'',true));
			echo '</pre>';
			$this->options->delete('webuser');
			$this->options->delete('zohouser', true);
		
		}
	}
}
?>