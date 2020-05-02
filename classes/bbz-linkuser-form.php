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
		$this->form = $this->linkuserform;
	}
	
	public function zoho_user_list () {
		$zoho = new zoho_connector;
		return $zoho->get_customer_names ();
	}
	
	public function web_user_list () {
		$webusers = get_users();
		foreach ($webusers as $user) {
			$results [$user->data->ID] = $user->user_email;
		}
		return $results;
	}
	public function action ($options) {
		$webuser = $options['webuser'];  // wordpress user id
		$zohouser = $options['zohouser']; //zoho user id
		
		$result = bbz_link_user ($webuser, $zohouser);
		
		if ($result) {
			$this->set_admin_notice ($options, 'User linked successfully', 'success');
		} else {
			$this->set_admin_notice ($options, 'User link failed', 'error');
		}
		update_option(OPTION_NAME, $options);
	}
	
	public function display_data ($options) {
		if (isset($options['webuser'])) {
			echo 'User Meta Data For User ID ', $options['webuser'] ;
			echo '<pre>';
			print_r (get_user_meta ($options['webuser'],'',true));
			echo '</pre>';
			unset ($options['webuser']);
			unset ($options['zohouser']);
			update_option (OPTION_NAME, $options);

			
		}
	}
}
?>