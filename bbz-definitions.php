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
define ('OPTION_NAME', 'bbz-options');
define ('ZOHO_AUTH_URL', 'https://accounts.zoho.com/oauth/v2/');
define ('ZOHO_BOOKS_API_URL', 'https://books.zoho.com/api/v3/');
define ('ZOHO_ANALYTICS_API_URL', 'https://analyticsapi.zoho.com/api/steve@bitternbooks.co.uk/ZohoBooks/');
define ('ZOHO_AUTH_SCOPE', 'ZohoBooks.fullaccess.all,ZohoReports.data.read');

// Post Meta tags
define ('BBZ_PM_ZOHO_ID', 'zoho_item_id');
define ('BBZ_PM_WHOLESALE_PRICE', 'wholesale_customer_wholesale_price');
define ('BBZ_PM_HAVE_WHOLESALE_PRICE', 'wholesale_customer_have_wholesale_price');
define ('BBZ_PM_INACTIVE_REASON', 'bbz_inactive_reason');


// User meta tags
define ('BBZ_UM_ZOHO_ID', 'zoho_contact_id');
define ('BBZ_UM_PAYMENT_TERMS', 'bbz_payment_terms');
define ('BBZ_UM_SALES_HISTORY', 'bbz_sales_history');


?>