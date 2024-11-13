<?php
/**
 * Creates the forms for the plugin.
 *
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
 
class bbz_admin_form {
 
 /**
	 * forms
	 *
     * Form definitions
     */
 
	private $form_definitions = array (
		// bbzform-auth
		'bbzform-auth'			=> array (
			'name'		=>	'bbzform-auth',
			'action'	=>	'auth_action',
			'title'		=>	'<h2>Connect to Zoho</h2>',
			'text_before'	=> '<p>You will have to first register your application with Zoho\'s Developer console in order get your Client ID'.
				'and Client Secret. To register your application, go to '.
				'<a href=https://accounts.zoho.com/developerconsole>Zoho Developer Console</a> and click on Add Client ID. '.
				'Provide the required details to register your application - e.g.:<br>'.
				'Client Name: Bittern Books Website<br>'.
				'Client domeain: bitternbooks.co.uk<br>'.
				'Authorized redirect URIs: https://bitternbooks.co.uk/wp-admin/admin-post.php</p>'.
				'<p>On successful registration, you will be provided with a set of OAuth 2.0 credentials such as a Client ID '.
				'and Client Secret that are known to both Zoho and your application. Do not share these credentials anywhere.</p>',
			'fields'	=>	array (
				'client_id' => array(    // Single text field
					'type'          => 'text',
					'title'         => 'Client ID',
					'attributes'	=>	array ('size' => 60)
				),
				'client_secret' => array(    // Single text field
					'type'          => 'text',
					'title'         => 'Client Secret',
					'attributes'	=>	array ('size' => 60)
				),
				'redirect_uri' => array(    // Single text field
					'type'          => 'text',
					'title'         => 'Redirect URI',
					'attributes'	=>	array ('size' => 60)
				),
				'state'			=> array(    // Status hidden
					'type'          => 'hidden',
					'title'         => 'State',
					'value'			=>	'bbzauth1'
				)
			),
			'button'		=> array (
				'name'			=> 'submit',
				'type'			=> 'primary',
				'title'			=> 'Authorise',
			)
		),
		'bbzform-reset'			=> array (
			'name'		=>	'bbzform-reset',
			'action'	=>	'reset_action',
			'title'		=>	'<h2>Connection to Zoho established</h2>',
			'text_before'	=> '<p>The connection to Zoho has already been established.  To reset the connection and enter '.
								'new credentials, use the Reset button here</p>',
			'fields'	=>	array (
				'state'			=> array(    // Status hidden
					'type'          => 'hidden',
					'title'         => 'State',
					'value'			=>	'bbzauthx'
				)
			),
			'button'		=> array (
				'name'			=> 'submit',
				'type'			=> 'primary',
				'title'			=> 'Reset Credentials',
			)
		),
	);
	
	protected $options;

	private function attribute_list () {
		$attributes = wc_get_attribute_taxonomies();
		foreach ($attributes as $key=>$a) {
			$results [$a->attribute_name] = $a->attribute_label.' ('.$a->attribute_name.')';
		}
		return $results;
	}

	protected $form = array();
	
	function __construct ($form) { // parameter may be form array or name of form defined here
		if (is_array ($form) ) {
			$this->form = $form;
		} else {
			if ( ! isset ($this->form_definitions [ $form] )) return false;
		
			$this->form = $this->form_definitions [ $form];
		}
		$this->options = new bbz_options();
	}

     /**
	 * render_form
	 *
     * Renders the html for the form defined by the contents of the array 
	 * $form	defines the contents of the form
	 * $values	(optional) contains current values for the form fields
     */
	
	function render ($values = array()) {
		//echo 'Form:<pre>'; print_r ($this->form); echo '</pre>';
		$fields = $this->form ['fields'];
		$action = 'bbzsave';
		
		//set field values
		//if there is an existing value from previous filling, use that
		// if field has a value in the form, then use that
		//	else if there's a value in the $values array, then use that
		//	else if there's an 'init' value in the form use that
		foreach ($fields as $field_id => &$field) {
			$value = $this->options->get($field_id);
			if (!empty($value)) $field['value'] = $value;
			if ( !isset ($field['value']) ) {
				if (isset ($values [ $field_id ]) ) {
					$field['value'] = $values [ $field_id ];
				} else {
				
					if (isset ($field['init'] ) ) {
						$field ['value'] = $field['init'];
					}
				}
			}
		}
		unset ($field);
/*		
		foreach ($values as $field_id => $field_value) {
			if (isset ($fields[$field_id])) {
				
				$fields[$field_id]['value']=$field_value;
			}
		}
*/
?>
    <form method="post" enctype="multipart/form-data" action="<?php echo esc_html( admin_url ('admin-post.php') ); ?>">
	    <div id="universal-message-container">
<?php
		if (isset ($this->form['title'])) echo $this->form['title'];
		if (isset ($this->form['text_before'])) echo $this->form['text_before'];
		
/*		// debuggery
		echo 'Form fields array<br><pre>';
		print_r ($fields);
		echo '</pre>'; */
		
		// show fields
?>
			<table border="0" cellpadding = "6" cellspacing = "6">
<?php	

		foreach ($fields as $field_id => $field) { 
			if (!in_array($field['type'], array('hidden', 'submit'))) {	// Don't display label for hidden fields
?>
					<tr>
						<td><label><?php echo $field['title'] ?></label></td>
						<td>
<?php						
			}

			switch ($field['type']) {
				case 'select':
					echo '<select';
					echo ' name='.$field_id;
					if (isset($field['attributes'])) {
						foreach ($field['attributes'] as $att=>$value) {
							echo ' '.$att.'="'.$value.'"';
						}
					}
					echo '>';
					if (isset ($field['value'])) {
						$selected = $field['value'];
					} else {
						$selected = '';
					}
					if (isset ( $field['options'] ) ) {
						$options = $field['options'];
					} elseif (isset ( $field['optionfunc'] )) {
						$options = call_user_func (array ($this, $field['optionfunc']));
					}
					if (!empty ($options)) {
						foreach ($options as $opt => $title) {
							echo '<option value="',$opt,'"';
							if ( $selected == $opt ) echo ' selected';
							echo '>',$title,'</option>';
						}
					}
					echo '</select>';
					break;
				case 'textarea':
					echo '<textarea';
					echo ' name='.$field_id;
					if (isset($field['attributes'])) {
						foreach ($field['attributes'] as $att=>$value) {
							echo ' '.$att.'="'.$value.'"';
						}
					}
					echo '>';
					echo '</textarea>';
					break;
				case 'checkbox':
					echo '<input type=checkbox';
					if (isset($field['value']) && $field['value']=='on') {
						echo ' checked';
					}
					echo ' name='.$field_id;
					if (isset($field['attributes'])) {
						foreach ($field['attributes'] as $att=>$value) {
							echo ' '.$att.'="'.$value.'"';
						}
					}
					echo '>';
					break;
				case 'file':
					echo '<input type=file';
					echo ' name='.$field_id;
					if (isset($field['attributes'])) {
						foreach ($field['attributes'] as $att=>$value) {
							echo ' '.$att.'="'.$value.'"';
						}
					}
					echo '>';
					break;
					
				default:
					echo '<input';
					echo ' type='.$field['type'];
					if (isset($field['value'])) {
						echo ' value="'.$field['value'].'"';
					}
					echo ' name=', isset($field['name'])?$field['name']:$field_id;
					if (isset($field['attributes'])) {
						foreach ($field['attributes'] as $att=>$value) {
							echo ' '.$att.'="'.$value.'"';
						}
					}
					echo '>';
			}
			if (!in_array($field['type'], array('hidden', 'submit'))) {
?>
						</td>
					</tr>
<?php
			}
		} 
?>
			</table>
			<input type="hidden" name="action" value=<?php echo '"'.SAVE_ACTION.'"' ?>; >
<?php
		$this->add_nonce ();  //add nonce and form name for security
		if (isset($this->form['button'])) {
			$button = $this->form['button'];
			submit_button($button['title'], $button['type'], $button['name'], TRUE, isset($button['attributes']) ? $button['attributes'] : '');
		}
?>
		</div><!-- #universal-message-container -->
    </form>
<?php
	}
	
	
     /**
     * Add the nonce field using the form name
     */
	private function add_nonce() {
		//add form name
		echo "<input type='hidden' name='form' value = {$this->form['name']}>";
		if (isset ($this->form['class'])) {
			echo "<input type='hidden' name='class' value = {$this->form['class']}>";
		}
		// and nonce field
		wp_nonce_field( $this->form['name'].'_save', $this->form['name'].'_wpnonce' );
	}
     /**
     * Get the $_POST fields for the form
	 *
	 * Returns an array of sanitized post fields for the current form as 
	 * 	field_name => field_value
	 * pairs.
     */
	
	public function post_data () {
		// First, validate the nonce  and check user has permission to save.
		if ($this->has_valid_nonce() && current_user_can( 'manage_options' ) ) {
			$results = array();
			// now load each field
			foreach ($this->form['fields'] as $field_id => $field) {
				// If the above are valid, sanitize and save the option.
				if ( isset ($_POST[$field_id] ) && null !== wp_unslash( $_POST[$field_id] ) ) {
					if ($field['type'] = 'textarea') {
						$results[$field_id] = sanitize_textarea_field( $_POST[$field_id] );
					} else {
						$results[$field_id] = sanitize_text_field( $_POST[$field_id] );
					}
				} elseif ($field['type'] = 'checkbox') {  // special case for unset checkbox
					$results[$field_id] = 'off';
				}
			}
			return $results;
		} else {
			return false;
		}
	}
    /**
     * Determines if the nonce variable associated with the options page is set
     * and is valid.
     *
     * @return boolean False if the field isn't set or the nonce value is invalid;
     *                 otherwise, true.
     */
    private function has_valid_nonce() {
        // If the field isn't even in the $_POST, then it's invalid.
		$noncename = $this->form['name'].'_wpnonce';
		$action = $this->form['name'].'_save';

        if ( ! isset( $_POST[$noncename] ) ) { // Input var okay.
            return false;
        }
        $nonce  = wp_unslash( $_POST[$noncename] );

        return wp_verify_nonce( $nonce, $action );
    }
	/***
	* action
	*
	* Calls action function for the current form to process results
	*
	*****/
	public function action () {
	
		$this->options->reload();
		
		if ( isset( $this->form['action'] ) ) {
			call_user_func (array ($this, $this->form['action']));
		}
	}
	/***
	* display_data
	*
	* Calls display_data function for the current form to display results
	*
	*****/
	public function display_data () {
		
		if ( isset( $this->form['display_data'] ) ) {
			call_user_func (array ($this, $this->form['display_data']));
		}
	}

 
	/*****
	* Form specific action functions
	*
	*****/
	private function auth_action () {

		// Go to Zoho for to get first authorisation code
		$auth_url = ZOHO_AUTH_URL.'auth';
		$zoho_args = array (
			'scope' => ZOHO_AUTH_SCOPE,
			'prompt' => 'consent',		//forces login screen and necessary to get refresh token
			'access_type' => 'offline', //necessary to get refresh token
			'client_id' => $this->options->get('client_id'),
			'response_type' => 'code',
			'redirect_uri' => $this->options->get('redirect_uri'),
			'state' => 'bbzauth2'
		);

		wp_redirect($auth_url.'?'.http_build_query($zoho_args));
		exit();
	}
	
	private function reset_action () {
		$this->options->reset();
	}
/*	
	private function short_desc_action ($options) {
		
	}
	
*/	
	
	
}
?>