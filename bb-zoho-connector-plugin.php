<?php
/*
    Plugin Name:    Bittern Books Zoho Connector
    Plugin URI:     http://www.unilake.co.uk/
    Description:    Elements to link Bittern Books website to Zoho
    Author:         Steve Haines
    Author URI:     http://www.unilake.co.uk/
    Version:        2.9.2
	Release Date:	09/04/2025
    Requirements:   PHP 5.4 or above, WordPress 3.4 or above.
*/

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}
define ('BBZ_PATH', dirname( __FILE__ ));
define ('BBZ_CLASSPATH', BBZ_PATH . '/classes' );
define ('BBZ_ASSETSPATH', BBZ_PATH . '/assets' );
define ('BBZ_ADMINPATH', BBZ_PATH . '/admin' );
include_once ( BBZ_PATH . '/bbz-definitions.php');
include_once ( BBZ_PATH . '/bbz-utils.php');		// utility functions
include_once ( BBZ_PATH . '/bbz-availability.php');		// availability functions
include_once ( BBZ_CLASSPATH . '/bbz-usermeta.php');
include_once ( BBZ_CLASSPATH . '/bbz-addresses.php');
include_once ( BBZ_CLASSPATH . '/bbz-options.php');
include_once ( BBZ_CLASSPATH . '/bbz-zoho-core.php');
include_once ( BBZ_CLASSPATH . '/bbz-zoho-connector.php');
include_once ( BBZ_CLASSPATH . '/bbz-zoho-shipmentorders.php');
include_once ( BBZ_CLASSPATH . '/bbz-order.php');
include_once ( BBZ_ADMINPATH . '/bbz-admin-form.php');
include_once ( BBZ_ADMINPATH . '/bbz-order-entry-form.php');
include_once ( BBZ_ADMINPATH . '/bbz-test-form.php');
include_once ( BBZ_ADMINPATH . '/bbz-test-zoho-form.php');
include_once ( BBZ_ADMINPATH . '/bbz-linkuser-form.php');
include_once ( BBZ_ADMINPATH . '/bbz-action-form.php');
include_once ( BBZ_ADMINPATH . '/bbz-admin-page.php');
include_once ( BBZ_CLASSPATH . '/bbz-products.php');
include_once ( BBZ_CLASSPATH . '/bbz-shipments.php');
include_once ( BBZ_CLASSPATH . '/bbz-sales-history.php');
include_once ( BBZ_PATH . '/bbz-wwlc-filters.php');
//include_once ( BBZ_PATH . '/bbz-wwof-filters.php');
include_once ( BBZ_PATH . '/bbz-functions.php');  // miscellaneous actions and filter functions
include_once ( BBZ_PATH . '/bbz-cron.php');		// daily and hourly cron functions
	


// Submenu class - loads the plugin page to the Settings submenu
class bbz_submenu {

    private $submenu_page;
 
    public function __construct($page) {
        $this->submenu_page = $page;
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

// load the plugin admin page
 
add_action( 'plugins_loaded', 'bbz_admin_settings' );

function bbz_admin_settings() {

    new bbz_submenu(new bbz_admin_page);

	add_action( 'admin_bar_menu', 'bbz_admin_bar_menu', 1000 );


}
	
// Add a new menu item to the WP admin toolbar - quick access to order entry
function bbz_admin_bar_menu( $admin_bar ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		return; // security check.
	}
	$admin_bar->add_menu( [
		'id'     => 'bbz-menu-item',
		'title'  => 'Order Entry',
		'href'   => esc_url( admin_url( 'options-general.php?page=bbz-admin-page&tab=order' ) )
	] );
}


// Run account payment gateway - can't be loaded until woocommerce is loaded.
add_action('plugins_loaded', 'bbz_init_account_payment_gateway');
function bbz_init_account_payment_gateway()
{
	if ( class_exists( 'WC_Payment_Gateway' ) ) {  // just in case it hasn't loaded
		require_once BBZ_CLASSPATH . '/bbz-wc-gateway-account.php';
		new bbz_wc_gateway_account;
	} else {
		echo 'ERROR: WC_Payment_Gateway not loaded<br>';
	}
}
add_filter('woocommerce_payment_gateways', 'bbz_register_account_payment_gateway');

function bbz_register_account_payment_gateway($gateways)
{
	$gateways[] = 'bbz_wc_gateway_account';
	return $gateways;
}