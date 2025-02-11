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
//define ('SAVE_ACTION', 'bbz-save');
define ('BBZ_OPTION_NAME', 'bbz-options');
define ('ZOHO_AUTH_URL', 'https://accounts.zoho.com/oauth/v2/');
define ('ZOHO_BOOKS_API_URL', 'https://www.zohoapis.com/books/v3/');
define ('ZOHO_ANALYTICS_API_URL', 'https://analyticsapi.zoho.com/api/steve@bitternbooks.co.uk/ZohoBooks/');
define ('ZOHO_AUTH_SCOPE', 'ZohoBooks.fullaccess.all,ZohoReports.data.read');

// Zoho REST call timeout in seconds
define ('BBZ_ZOHO_TIMEOUT', 80);

// Payment method names used by woocommerce payment gateways
define ('BBZ_PAYMENT_METHOD_PAYPAL', 'ppcp-gateway');
define ('BBZ_PAYMENT_METHOD_STRIPE', 'stripe');
define ('BBZ_PAYMENT_METHOD_ACCOUNT', 'account');

// Payment method names used by Zoho
define ('BBZ_PROFORMA', 'Pro Forma');

// Zoho invoice template ids
define ('BBZ_TEMPLATE_PROFORMA', 1504573000001016358);

// Zoho Sales Order substatus codes
define ('BBZ_SOSUB_WAITSTOCK', 'cs_waiting');
define ('BBZ_SOSUB_WAITPO', 'cs_waitfor');
define ('BBZ_SOSUB_WAITPAYMENT', 'cs_70eoqdr');

if (stristr(site_url(), 'test')) { // if running on test site
	define ('ZOHO_SALESORDER_PREFIX', 'TEST-');
	define ('BBZ_DEBUG', true);
	define ('BBZ_RUNCRONS', false);
} else { //assume this is the live site
	define ('ZOHO_SALESORDER_PREFIX', 'WO-');
	define ('BBZ_DEBUG', false);
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
define ('BBZ_PM_RESTRICTIONS', 'bbz_restrictions');
define ('BBZ_PM_RELEASE_DATE', 'bbz_release_date');

// Post Meta tags for orders
define ('BBZ_PM_ZOHO_ORDER_ID', 'zoho_order_id');
define ('BBZ_PM_SHIPMENTS', 'zoho_shipments');

// User meta tags
define ('BBZ_UM_ZOHO_ID', 'zoho_contact_id');
define ('BBZ_UM_PAYMENT_TERMS', 'bbz_payment_terms');
define ('BBZ_UM_SALES_HISTORY', 'bbz_sales_history');
define ('BBZ_UM_PREVIOUS_PRODUCTS', 'bbz_previous_products');
define ('BBZ_UM_ADDRESSES', 'bbz_addresses');

// BBZ Options Names
define ('BBZ_OP_GUESTID', 'guestuserid');

// Account IDs
define ('ZOHO_PAYPAL_ACCOUNT_ID', '1504573000005379057');
define ('ZOHO_STRIPE_ACCOUNT_ID', '1504573000000149167');


// Constants for Trackingmore connection
define ('BBZ_TM_URL', 'https://api.trackingmore.com/v4/');
define ('BBZ_TM_APIKEY', '671eugd-yazk-q76s-62ak-v2ypc7bxkryr');
define ('BBZ_TM_TIMEOUT', 30);


//Site options
// BBZ_AUTO_CREATE_CONTACT - if true, create a contact when placing an order for a logged in user
// This function has been implemented and tested, but switched off at the moment as would create too many
// contacts in Zoho
define ('BBZ_AUTO_CREATE_CONTACT', false);  
define ('BBZ_DISCOUNT_WARNING', 40.2);  // adds warning to daily reports for products with high discounts

DEFINE ('BBZ_LOGGED_IN_DAYS', 90); // Set number of days users remain logged in for when they check 'remember me'

?>