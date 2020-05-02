<?php
/**
 * Creates the submenu page for the plugin.
 *
 * @package Custom_Admin_Settings
 */
 
 // If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}



/**
 * Creates the submenu page for the plugin.
 *
 * Provides the functionality necessary for rendering the page corresponding
 * to the submenu with which this page is associated.
 *
 * @package Custom_Admin_Settings
 */
 
class bbz_admin_page {
 

	public function init() {
		//  Add handler for form posts
        add_action( 'admin_post_'.SAVE_ACTION, array( $this, 'save' ) );
		// Add handler for Oauth return from Zoho to base url for site
		add_action('init', array( $this, 'save_auth' ) );
    }

	/*******
	* admin_notice
	*
	* Displays a message on the admin screen
	* $type	
	*		error – error message displayed with a red border
	*		warning – warning message displayed with a yellow border
	*		success – success message displayed with a green border
	*		info – info message displayed with a blue border
	*/
	
	private function render_admin_notice (&$options, $dismissable=true) {
		if (isset ( $options['admin_notice']) && //isset ($options ['admin_message']) &&
			in_array ( $options['admin_notice'], array ('error', 'warning', 'success', 'info'))) {
?>
	<div class="notice notice-<?php echo $options['admin_notice'].($dismissable ? ' is-dismissible' : ''); ?>" >
		<p><strong><?php echo $options ['admin_message'] ?></strong></p>
	</div>
<?php
			unset ( $options['admin_notice'] );
			unset ( $options['admin_message'] );
			return true;
		}
		return false;
	}
	
	private function set_admin_notice (&$options, $msg='message', $type='error') {
		if ( in_array ( $type, array ('error', 'warning', 'success', 'info'))) {
			$options ['admin_notice'] = $type;
			$options ['admin_message'] = $msg;

		}
	}
 
     /**
     * This function renders the contents of the page associated with the Submenu
     * that invokes the render method. In the context of this plugin, this is the
     * Submenu class.
     */
    public function render() {
		$zoho_url = '';
		$options = get_option ( OPTION_NAME);
		if(!$options || !is_array($options) ) $options =array();
		
		if( isset( $_GET[ 'tab' ] ) ) {
			$active_tab = $_GET[ 'tab' ];
		} else {
			$active_tab = 'setup';
		}
		
?>
	<div class="wrap">
		<h1>Zoho Connector for Bittern Books</h1>
		<h2 class="nav-tab-wrapper">
			<a href="?page=bbz-admin-page&tab=setup" class="nav-tab <?php echo $active_tab == 'setup' ? 'nav-tab-active' : ''; ?>">Setup</a>
			<a href="?page=bbz-admin-page&tab=action" class="nav-tab <?php echo $active_tab == 'action' ? 'nav-tab-active' : ''; ?>">Actions</a>	
			<a href="?page=bbz-admin-page&tab=link-user" class="nav-tab <?php echo $active_tab == 'link-user' ? 'nav-tab-active' : ''; ?>">Link User</a>
			<a href="?page=bbz-admin-page&tab=short-desc" class="nav-tab <?php echo $active_tab == 'short-desc' ? 'nav-tab-active' : ''; ?>">Short Description</a>	
			
			<a href="?page=bbz-admin-page&tab=test" class="nav-tab <?php echo $active_tab == 'test' ? 'nav-tab-active' : ''; ?>">Testing</a>
		</h2>
<?php		
		
		// Display message from last action if set.
		$this->render_admin_notice ($options);
		
		if (!isset ($options['redirect_uri'])) $options['redirect_uri'] = site_url();
		
		switch ($active_tab) {
			case 'setup':
				if(!isset ($options['refresh_token']) ) {
					$form = new bbz_admin_form ('bbzform-auth');
				} else {
					$form = new bbz_admin_form ('bbzform-reset');
				}
				break;
			case 'action':
				$form = new bbz_action_form ();
				break;
			case 'link-user':
				$form = new bbz_linkuser_form ();
				break;
			case 'short-desc':
//				echo '<pre>';
//				print_r (wc_get_attribute_taxonomies()); echo '</pre>';
				$form = new bbz_admin_form ('bbzform-short-desc');
				break;
			case 'test':
			
				// Testing form
				$form = new bbz_test_form() ;
				break;
		}
		$form->render ($options);
		
		$form->display_data ($options);

		// save options
		update_option (OPTION_NAME, $options);
?>
	</div>
<?php
    }
	
	/**
	 * save
	 *
     * Called to handle the form data returned in $_POST
     */

	public function save() {
		//echo '<pre>'; print_r ($_POST); echo '</pre>';

		$options = get_option ( OPTION_NAME);
		if(!$options || !is_array($options) ) $options =array();
		
		// Process return from one of our forms
		if (isset( $_POST['form'] )) {
			if (isset( $_POST['class'] )) {
				$form = new $_POST['class'];
			} else {
				$form = new bbz_admin_form ($_POST['form']);
			}

			if (! $form) {
				$this->set_admin_notice ($options, 'Invalid form name '.$_POST['form'], 'error');
			} else {
				//echo 'post_data:<pre>'; print_r ($form->post_data()); echo '</pre>';
				foreach ($form->post_data () as $key => $value) {
						// If the above are valid, sanitize and save the option.
					$options[$key] = $value;
				}
			}
		}

		update_option( OPTION_NAME, $options );
		
		// execute form specific actions
		$form->action ($options);
				
		$this->redirect();
    }

	// callback function from zoho authorisation
	public function save_auth () {
		// Process auth response from Zoho
		if(isset($_REQUEST['state']) && $_REQUEST['state'] == 'bbzauth2') {
//			print_r ($_REQUEST);
//			exit();
			if(isset($_REQUEST['code'])) {
				$options = get_option(OPTION_NAME);
				$options['auth_code']=$_REQUEST['code'];
				update_option(OPTION_NAME, $options);
				
				// now need to get access token and refresh token from Zoho
				$request_url = ZOHO_AUTH_URL.'token';
				$request_args = array(
					'body' => array (
						'code' => $options['auth_code'],
						'client_id' => $options['client_id'],
						'client_secret' => $options['client_secret'],
						'redirect_uri' => $options ['redirect_uri'],
						'grant_type' => 'authorization_code',
						'scope' => ZOHO_AUTH_SCOPE,
					)
				);
				$response = wp_remote_post ($request_url, $request_args);
				if (!is_array ($response)) {
					echo 'HTTP error retrieving tokens '.$response;
					exit ();
				} else {
					$content = json_decode ($response['body'], true);
					// var_dump ($content);
					if (isset( $content['error'])) {
						echo 'Error response from Zoho: '.$content['error'];
						// var_dump ($content);
						exit();
					} else {
						foreach ($content as $key => $value) {
							$options [$key] = $value;
						}
						if (isset ($content['expires_in'])) {
							// save time at which token expires (in seconds)
							$options ['token_expires'] = time() + $content['expires_in'] - 10;
						}
						update_option(OPTION_NAME, $options);
						
					}
				}
				$this->redirect(admin_url('options-general.php?page=bbz-admin-page'));
			}
		}
	}
    /**
     * Redirect to the page from which we came (which should always be the
     * admin page. If the referred isn't set, then we redirect the user to
     * the login page.
     *
     * @access private
     */
    private function redirect($default_url='') {
		if($default_url=='') $default_url = wp_login_url();
		
        // To make the Coding Standards happy, we have to initialize this.
        if ( ! isset( $_POST['_wp_http_referer'] ) ) { // Input var okay.
            $_POST['_wp_http_referer'] = $default_url;
        }
        // Sanitize the value of the $_POST collection for the Coding Standards.
        $url = sanitize_text_field(
                wp_unslash( $_POST['_wp_http_referer'] ) // Input var okay.
        );
 
        // Finally, redirect back to the admin page.
        wp_safe_redirect( urldecode( $url ) );
        exit;
    }
	
}
?>