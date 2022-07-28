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
} else {
	define ('ZOHO_SALESORDER_PREFIX', 'TEST-');
	define ('BBZ_DEBUG', true);
}

// Post Meta tags
define ('BBZ_PM_ZOHO_ID', 'zoho_item_id');
define ('BBZ_PM_WHOLESALE_PRICE', 'wholesale_customer_wholesale_price');
define ('BBZ_PM_HAVE_WHOLESALE_PRICE', 'wholesale_customer_have_wholesale_price');
define ('BBZ_PM_INACTIVE_REASON', 'bbz_inactive_reason');


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
define ('BBZ_AVAIL_OFF', array ('Out of print', 'Delisted', 'No longer available', 'Replaced by new edition'));
define ('BBZ_AVAIL_TEMP', array ('Reprinting', 'Temporarily unavailable', 'Pre-order'));  //Allows backorders on these items
define ('BBZ_AVAIL_PRE', array ('Pre-order'));

//Site options
// BBZ_AUTO_CREATE_CONTACT - if true, create a contact when placing an order for a logged in user
// This function has been implemented and tested, but switched off at the moment as would create too many
// contacts in Zoho
define ('BBZ_AUTO_CREATE_CONTACT', false);  


global $bbz_product_index;  //make global to avoid reloading
$bbz_product_index = array();


?>