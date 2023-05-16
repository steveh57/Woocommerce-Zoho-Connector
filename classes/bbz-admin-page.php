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
		
	protected $options;

	public function init() {
		//  Add handler for form posts
        add_action( 'admin_post_'.SAVE_ACTION, array( $this, 'save' ) );
		// Add handler for Oauth return from Zoho to base url for site
		add_action('init', array( $this, 'save_auth' ) );
		$this->options = new bbz_options;
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
	
	private function render_admin_notice ($dismissable=true) {
		$admin_notice = $this->options->get_admin_notice();
		if (!empty ($admin_notice) ) {
?>
	<div class="notice notice-<?php echo $admin_notice.($dismissable ? ' is-dismissible' : ''); ?>" >
		<p><strong><?php echo $this->options->get_admin_message() ?></strong></p>
	</div>
<?php
			$this->options->clear_admin_notice();
			return true;
		}
		return false;
	}
	
     /**
     * This function renders the contents of the page associated with the Submenu
     * that invokes the render method. In the context of this plugin, this is the
     * Submenu class.
     */
    public function render() {
		$this->options->reload();
		$zoho_url = '';
		
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
			<a href="?page=bbz-admin-page&tab=test" class="nav-tab <?php echo $active_tab == 'test' ? 'nav-tab-active' : ''; ?>">Testing</a>
		</h2>
<?php		
/*
			<a href="?page=bbz-admin-page&tab=short-desc" class="nav-tab <?php echo $active_tab == 'short-desc' ? 'nav-tab-active' : ''; ?>">Short Description</a>	
*/			
		
		// Display message from last action if set.
		$this->render_admin_notice ();
		
		if ( empty ($this->options->get('redirect_uri'))) {
			$this->options->update(array('redirect_uri'=>site_url()));
		}
		
		switch ($active_tab) {
			case 'setup':
				if(empty ($this->options->get('refresh_token') ) ) {
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

			case 'test':
			
				// Testing form
				$form = new bbz_test_form() ;
				break;
		}
		$form->render ();
		
		$form->display_data ();


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

		$this->options->reload ();
		
		// Process return from one of our forms
		if (isset( $_POST['form'] )) {
			if (isset( $_POST['class'] )) {
				$form = new $_POST['class'];
			} else {
				$form = new bbz_admin_form ($_POST['form']);
			}

			if (! $form) {
				$this->options->set_admin_notice ('Invalid form name '.$_POST['form'], 'error');
			} else {
				//echo 'post_data:<pre>'; print_r ($form->post_data()); echo '</pre>';
				$this->options->update($form->post_data());
			}
		}

		// execute form specific actions
		$form->action ();
				
		$this->redirect();
    }

	// callback function from zoho authorisation
	public function save_auth () {
		// Process auth response from Zoho
		if(isset($_REQUEST['state']) && $_REQUEST['state'] == 'bbzauth2') {
//			print_r ($_REQUEST);
//			exit();
			if(isset($_REQUEST['code'])) {
				$this->options->reload ();
				$this->options->update(array('auth_code'=> $_REQUEST['code']));
				
				// now need to get access token and refresh token from Zoho
				$request_url = ZOHO_AUTH_URL.'token';
				$request_args = array(
					'body' => array (
						'code' => $this->options->get('auth_code'),
						'client_id' => $this->options->get('client_id'),
						'client_secret' => $this->options->get('client_secret'),
						'redirect_uri' => $this->options->get('redirect_uri'),
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
						if (isset ($content['expires_in'])) {
							// save time at which token expires (in seconds)
							$content['token_expires'] = time() + $content['expires_in'] - 10;
						}
						$this->options->update($content);						
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