<?php
/*
    Plugin Name:    Bittern Books Zoho Connector
    Plugin URI:     http://www.unilake.co.uk/
    Description:    Elements to link Bittern Books website to Zoho
    Author:         Steve Haines
    Author URI:     http://www.unilake.co.uk/
    Version:        0.0520
    Requirements:   PHP 5.2.4 or above, WordPress 3.4 or above. Admin Page Framework 3.1.3 or above
*/

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}
define ('BBZ_PATH', dirname( __FILE__ ));
define ('BBZ_CLASSPATH', BBZ_PATH . '/classes' );
include_once ( BBZ_PATH . '/bbz-definitions.php');
include_once ( BBZ_PATH . '/bbz-utils.php');
include_once ( BBZ_CLASSPATH . '/bbz-usermeta.php');
include_once ( BBZ_CLASSPATH . '/bbz-addresses.php');
include_once ( BBZ_CLASSPATH . '/bbz-options.php');
include_once ( BBZ_CLASSPATH . '/bbz-zoho-connector.php');
include_once ( BBZ_CLASSPATH . '/bbz-order.php');
include_once ( BBZ_CLASSPATH . '/bbz-admin-form.php');
include_once ( BBZ_CLASSPATH . '/bbz-test-form.php');
include_once ( BBZ_CLASSPATH . '/bbz-linkuser-form.php');
include_once ( BBZ_CLASSPATH . '/bbz-action-form.php');
include_once ( BBZ_CLASSPATH . '/bbz-admin-page.php');
include_once ( BBZ_PATH . '/bbz-wwlc-filters.php');
include_once ( BBZ_PATH . '/bbz-short-description.php');
include_once ( BBZ_PATH . '/bbz-wwof-filters.php');
include_once ( BBZ_PATH . '/bbz-thwma-filters.php');
include_once ( BBZ_PATH . '/bbz-functions.php');  // miscellaneous actions and filter functions
//include_once ( BBZ_PATH . '/bbz-registration-form.php'); // user registration form shortcode

// Submenu class
class bbz_submenu {

        /**
     * A reference the class responsible for rendering the submenu page.
     *
     * @var    Submenu_Page
     * @access private
     */
    private $submenu_page;
 
    /**
     * Initializes all of the partial classes.
     *
     * @param Submenu_Page $submenu_page A reference to the class that renders the
     *                                                                   page for the plugin.
     */
    public function __construct( $submenu_page ) {
        $this->submenu_page = $submenu_page;
    }
 
    /**
     * Adds a submenu for this plugin to the 'Tools' menu.
     */
    public function init() {
         add_action( 'admin_menu', array( $this, 'add_options_page' ) );
    }
 
    /**
     * Creates the submenu item and calls on the Submenu Page object to render
     * the actual contents of the page.
     */
    public function add_options_page() {
 
        add_options_page(
            'BBZ Connector',
            'BB Zoho Connector',
            'manage_options',
            'bbz-admin-page',
            array( $this->submenu_page, 'render' )
        );
    }
}
/**
 * Performs all sanitization functions required to save the option values to
 * the database.
 *
 * This will also check the specified nonce and verify that the current user has
 * permission to save the data.
 *
 * @package Custom_Admin_Settings
 */
/**
 * Starts the plugin.
 *
 * @since 1.0.0
 */
 
add_action( 'plugins_loaded', 'bbz_admin_settings' );

function bbz_admin_settings() {

	$page = new bbz_admin_page;
	$page->init();
 
    $plugin = new bbz_submenu( $page );
    $plugin->init();
 
}

// Run account payment gateway - can't be loaded until woocommerce is loaded.
add_action('plugins_loaded', 'bbz_init_account_payment_gateway');
function bbz_init_account_payment_gateway()
{
		require_once BBZ_CLASSPATH . '/bbz-wc-gateway-account.php';
		new bbz_wc_gateway_account;
}
add_filter('woocommerce_payment_gateways', 'bbz_register_account_payment_gateway');

function bbz_register_account_payment_gateway($gateways)
	{
		$gateways[] = 'bbz_wc_gateway_account';
		return $gateways;
	}

