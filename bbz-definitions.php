<?php
// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}
/**
 * BBZ PHP definitions
 *
 * @package Custom_Admin_Settings
 */
 


//define ('NONCE_NAME', 'bbz-nonce');
define ('SAVE_ACTION', 'bbz-save');
define ('BBZ_OPTION_NAME', 'bbz-options');
define ('ZOHO_AUTH_URL', 'https://accounts.zoho.com/oauth/v2/');
define ('ZOHO_BOOKS_API_URL', 'https://books.zoho.com/api/v3/');
define ('ZOHO_ANALYTICS_API_URL', 'https://analyticsapi.zoho.com/api/steve@bitternbooks.co.uk/ZohoBooks/');
define ('ZOHO_AUTH_SCOPE', 'ZohoBooks.fullaccess.all,ZohoReports.data.read');

// Zoho REST call timeout in seconds
define ('BBZ_ZOHO_TIMEOUT', 40);

// Payment method names used by woocommerce payment gateways
define ('BBZ_PAYMENT_METHOD_PAYPAL', 'ppcp-gateway');
define ('BBZ_PAYMENT_METHOD_STRIPE', 'stripe');
define ('BBZ_PAYMENT_METHOD_ACCOUNT', 'account');

if (stristr(site_url(), 'bitternbooks.co.uk') !== false) { // if running on live site
	define ('ZOHO_SALESORDER_PREFIX', 'WO-');
	define ('BBZ_DEBUG', false);
	define ('BBZ_RUNCRONS', false);
} else { //assume this is a test instance
	define ('ZOHO_SALESORDER_PREFIX', 'TEST-');
	define ('BBZ_DEBUG', true);
	define ('BBZ_RUNCRONS', true);
}

// Post Meta tags (for products)
define ('BBZ_PM_ZOHO_ID', 'zoho_item_id');
define ('BBZ_PM_WHOLESALE_PRICE', 'wholesale_customer_wholesale_price');
define ('BBZ_PM_HAVE_WHOLESALE_PRICE', 'wholesale_customer_have_wholesale_price');
define ('BBZ_PM_INACTIVE_REASON', 'bbz_inactive_reason');
define ('BBZ_PM_WHOLESALE_DISCOUNT', 'bbz_wholesale_discount');
define ('BBZ_PM_DIMENSION_STRING', 'bbz_dimension_string');
define ('BBZ_PM_AVAILABILITY', 'bbz_availability');

// Post Meta tags for orders
define ('BBZ_PM_ZOHO_ORDER_ID', 'zoho_order_id');
define ('BBZ_PM_SHIPMENTS', 'zoho_shipments');

// User meta tags
define ('BBZ_UM_ZOHO_ID', 'zoho_contact_id');
define ('BBZ_UM_PAYMENT_TERMS', 'bbz_payment_terms');
define ('BBZ_UM_SALES_HISTORY', 'bbz_sales_history');
define ('BBZ_UM_ADDRESSES', 'bbz_addresses');

// BBZ Options Names
define ('BBZ_OP_GUESTID', 'guestuserid');

// Account IDs
define ('ZOHO_PAYPAL_ACCOUNT_ID', '1504573000005379057');
define ('ZOHO_STRIPE_ACCOUNT_ID', '1504573000000149167');

// Stock availability constants - must match values used in Zoho items Availability (fka Inactivity Reason) field
define ('BBZ_AVAIL_OFF', array ('out-of-print', 'delisted', 'no-longer-available', 'replaced-by-new-edition'));
define ('BBZ_AVAIL_SOON', array ('available', 'pre-order'));  //Allows backorders on these items
define ('BBZ_AVAIL_PRE', array ('pre-order'));

// Constants for Trackingmore connection
define ('BBZ_TM_URL', 'https://api.trackingmore.com/v4/');
define ('BBZ_TM_APIKEY', '671eugd-yazk-q76s-62ak-v2ypc7bxkryr');
define ('BBZ_TM_TIMEOUT', 30);


//Site options
// BBZ_AUTO_CREATE_CONTACT - if true, create a contact when placing an order for a logged in user
// This function has been implemented and tested, but switched off at the moment as would create too many
// contacts in Zoho
define ('BBZ_AUTO_CREATE_CONTACT', false);  

DEFINE ('BBZ_LOGGED_IN_DAYS', 90); // Set number of days users remain logged in for when they check 'remember me'

// Product index - used to translate zoho product id to wp post id in user meta and sales history
global $bbz_product_index;  //make global to avoid reloading
$bbz_product_index = array();

?>