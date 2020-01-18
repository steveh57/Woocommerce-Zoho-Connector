<?php
/*
    Plugin Name:    Bittern Books Zoho Connector
    Plugin URI:     http://www.unilake.co.uk/
    Description:    Elements to link Bittern Books website to Zoho
    Author:         Steve Haines
    Author URI:     http://www.unilake.co.uk/
    Version:        0.1
    Requirements:   PHP 5.2.4 or above, WordPress 3.4 or above. Admin Page Framework 3.1.3 or above
*/

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}
include_once ( dirname( __FILE__ ) . '/bbz-admin-page.php');
include_once ( dirname( __FILE__ ) . '/bbz-user-registration.php');
include_once ( dirname( __FILE__ ) . '/bbz-short-description.php');
include_once ( dirname( __FILE__ ) . '/bbz-sales-history.php');

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


