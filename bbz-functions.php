<?php
// This file includes miscellaneous funnctions, actions and filters
// included here for ease of maintenance rather than in functions.php
// Some functions that are very specific to the theme layout remain in functions.php in the child theme.

 // If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}
 
 /**
 * Re-invokes certain WooCommerce hooks to activate the Ideal Postcodes address validation plugin on checkout
 * Requires a shortcode "[add_ideal_postcodes]" to be added to the checkout page
 *** Looks like this is not required as it gets invoked anyway on Elementor checkout form
 */
//add_shortcode('add_ideal_postcodes', function(){
    // Loads JavaScript and CSS assets
//	do_action("ideal_postcodes_address_search");
//});

/*****
* Restrict use of Frequently bought together to retail customers
*****/

add_filter ('woobt_show_items', 'bbz_frequently', 10, 2);

function bbz_frequently ($items, $product_id) {
	if (bbz_is_wholesale_customer()) return false;
	return $items;
}

 /*****
 * 	Add Twitter card and facebbok meta to page header
 *****/
add_action('wp_head', 'bbz_add_meta', 10);
 
function bbz_add_meta () {
	global $post;
	if (is_object($post) && (is_single() || is_page() || is_product() )) {
		$meta_url    = wp_get_shortlink(); //get_permalink();
		$meta_title  = get_the_title();
		$meta_desc   = get_the_excerpt();
		$meta_thumbs = wp_get_attachment_image_src( get_post_thumbnail_id($post->ID), 'thumbnail' );
		if(!$meta_thumbs) {
			$meta_thumb_url = 'https://bitternbooks.co.uk/wp-content/uploads/2019/11/Bittern-Logo-280x280.png';
		} else {
			$meta_thumb_url = $meta_thumbs[0];
		}
/* Twitter tags unnecessary - Twitter accepts og tags which also work for Facebook
<meta name="twitter:url" content="<?php echo $meta_url; ?>" />
<meta name="twitter:title" content="<?php echo $meta_title; ?>" />
<meta name="twitter:description" content="<?php echo $meta_desc; ?>" />
<meta name="twitter:image" content="<?php echo $meta_thumb_url; ?>" />
*/		
?><meta name="twitter:card" content="summary" />
<meta name="twitter:site" content="@BitternBooks" />
<meta name="twitter:creator" content="@BitternBooks" />
<meta name="og:url" content="<?php echo $meta_url; ?>" />
<meta name="og:title" content="<?php echo $meta_title; ?>" />
<meta name="og:description" content="<?php echo $meta_desc; ?>" />
<meta name="og:image" content="<?php echo $meta_thumb_url; ?>" />
<?php
	 }
}



 /*****
 *	Fix for Elementor not including structured data on single product page
 8
 ******/
add_action('woocommerce_before_single_product', 'bbz_woocommerce_before_single_product', 10);

function bbz_woocommerce_before_single_product() {
 
    global $product;
  
    if(is_object($product)) {
        //  call wc function to generate data - Elementor should do this !!!
        WC()->structured_data->generate_product_data($product);
    }
}


/*********
* Filter to add structured product data used by google
*
**********/

add_filter( 'woocommerce_structured_data_product', 'bbz_structured_data_product_filter', 20, 2);

function bbz_structured_data_product_filter ( $markup, $product) {
	$sku = $product->get_sku();
	if (strlen ($sku) == 13 & strpos ($sku, "978")===0) { // Only use sku as isbn if it has 13 chars starting 978
		$markup['isbn'] =  $sku; // Set isbn to product sku.
	}
	$attribute_map = array (
			"bookFormat"	=> 'format',
			'numberOfPages'	=> 'pages',
			'author'		=> 'author-2',
			'publisher'		=> 'publisher',
			'isPartOf'		=> 'series',
			'position'		=> 'position',
		);
			
	foreach ($attribute_map as $entname=>$slug) {
		$attribute = $product->get_attribute ($slug);
		if (!empty ($attribute) ) {
			$markup [$entname] = $attribute;
		}
	}
	$markup['shippingLabel'] = $product->get_shipping_class();
	
    return $markup;
}
/********
* Insert tag to support Google Analytics GA4 
*
* - Assumes we're using GA Pro
* - Can remove this if we update GA Pro and it supprts GA4
*
********/
add_action( 'wc_google_analytics_pro_before_tracking_code', 'bbz_add_ga4_tag' );

function bbz_add_ga4_tag ( ) {
?>
<!-- GA4 tag from bbz-functions -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-L5E3PE17QW"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', 'G-L5E3PE17QW');
</script>
<?php
}

/********
* Add user role to Google Analytics tracking
*
* - Assumes we are using Google Analytics Pro
* - Custom dimensions must be set up in Google Analytics Admin/Property/Custom Dimensions
*
********/
add_action( 'wc_google_analytics_pro_after_tracking_code_setup', 'bbz_add_google_tracking_code' );

function bbz_add_google_tracking_code( $ga_function ) {

// dimension1 - User Role
	$role = 'guest';  //set default
	if ( is_user_logged_in()) {
		$user = wp_get_current_user();
		if (is_object ($user) ) {
			$user_roles = (array) $user->roles;
			$role = $user_roles[0];
		}
	}
	echo "{$ga_function}( 'set', 'dimension1', '$role' );";
}



/*********
 * WWOF PLUGIN NO LONGER USED
 * Availability text filter for trade order form
 *
 * Replaces 'out of stock' with more informative message
 
add_filter( 'wwof_filter_product_item_action_controls', 'bbz_wwof_availability_filter', 20, 3);

function bbz_wwof_availability_filter( $action_field, $product, $alternate ) {
	// action field is html text for display in trade order form
//	$availability = get_post_meta ($product->get_id(), BBZ_PM_INACTIVE_REASON, true);
	$zoho_code = get_post_meta ($post->ID, BBZ_PM_AVAILABILITY, true);
	$zoho_text = get_post_meta ($post->ID, BBZ_PM_INACTIVE_REASON, true);
	
	// if item out of stock, replace string with availability field from zoho if set
	if (strpos ($action_field, 'Out of Stock')!==false && is_string($zoho_text)) {
		$action_field = str_replace ("Out of Stock", $zoho_text, $action_field);
	
	// if item on backorder, then replace action with pre-order or backorder as appropriate
	} elseif ( strpos ($action_field, 'Add To Cart')!==false && $product->get_stock_status() == 'onbackorder') {
		if (in_array ($zoho_code, BBZ_AVAIL_PRE)) {
			$action_field = str_replace ('Add To Cart', $zoho_text, $action_field);
		} else {
			$action_field = str_replace ('Add To Cart', 'Backorder', $action_field);
		}
	}
	return $action_field;
}
*/


/******
* Wholesale original price filter
*
* Changes display of original price from deleted to RRP
*
* Call:   $wholesale_price_html = apply_filters( 'wwp_product_original_price' , '<del class="original-computed-price">' . $price . '</del>' , $wholesale_price , $price , $product , array( $user_wholesale_role ) );
*
****/
add_filter ( 'wwp_product_original_price', 'bbz_product_original_price_filter', 20, 3);

function bbz_product_original_price_filter ($html, $wsp, $price) {
	return '<span class="original-computed-price">RRP ' . $price . '</span>';
}

/**
 * Hide shipping rates when free shipping is available.
 *
 * @param array $rates Array of rates found for the package.
 * @return array
 */
// removed to allow priority paid shipping option
// add_filter( 'woocommerce_package_rates', 'bbz_hide_shipping_when_free_is_available', 100 );

function bbz_hide_shipping_when_free_is_available( $rates ) {
	$free = array();
	foreach ( $rates as $rate_id => $rate ) {
		if ( 'free_shipping' === $rate->method_id ) {
			$free[ $rate_id ] = $rate;
			break;
		}
	}
	return ! empty( $free ) ? $free : $rates;
}

// Update payment terms on entry to checkout page
add_action ('woocommerce_before_checkout_form', 'bbz_before_checkout');

function bbz_before_checkout() {
	bbz_update_payment_terms(); // in module bbz_utils
}

/**
 * Order handling
 * Called when order is complete to post order in Zoho
 * For testing call bbz_process_single_order to process the order in line
 * On the live site call bbz_order_processing to process in background and reduce waiting time
 */
if ( BBZ_DEBUG ) {
	add_action( 'woocommerce_thankyou', 'bbz_process_single_order', 10, 1);
} else {
	add_action( 'woocommerce_thankyou', 'bbz_schedule_order_processing', 10, 1);
}

function bbz_schedule_order_processing( $order_id ){	
	// spawn cron job to process the order asap (short delay to allow current page to complete)
	$response = wp_schedule_single_event (time() + 30, 'bbz_process_order_hook', array ($order_id), true);
	if (is_wp_error($response) ) {
		$response->add ('bbz-func-005', 'Scheduling single order processing', array(
			"Order ID"=>$order_id));
		bbz_email_admin ("Failed to create Zoho order", $response);
	}
}

add_action ('bbz_process_order_hook', 'bbz_process_single_order', 10, 1);

function bbz_process_single_order ( $order_id ) {
	$order = new bbz_order ($order_id);
	if (false == $order->is_on_zoho()) {  // skip if it's already been loaded
		$response = $order->process_new_order ();
		if (is_wp_error($response) ) {
			$response->add ('bbz-func-006', 'Processing single order', array(
				"Order ID"=>$order_id));
			bbz_email_admin ("Failed to create Zoho order", $response);
		}
	}
}


// Change address placeholder text

add_filter('woocommerce_default_address_fields', 'override_address_fields');
function override_address_fields( $address_fields ) {
	$address_fields['address_2']['placeholder'] = 'District';
return $address_fields;
}

/**
 * Ajax Add to Cart
 * Load js script from woo_ajax_add_to_cart plugin
 * Modified to load on all pages so it works with wishlist page.
 */
 if (!defined('QLWCAJAX_PLUGIN_VERSION')) {
  define('QLWCAJAX_PLUGIN_VERSION', '1.1.4');
}
 if (!defined('QLWCAJAX_JS_FILENAME')) {
  define('QLWCAJAX_JS_FILENAME', 'woo-ajax-add-to-cart.min.js');
}
add_action('wp_enqueue_scripts', 'bbz_add_product_js', 99);
function bbz_add_product_js() {

  wp_register_script('woo-ajax-add-to-cart', plugin_dir_url(__FILE__) . 'assets/'.QLWCAJAX_JS_FILENAME, array('jquery', 'wc-add-to-cart'), QLWCAJAX_PLUGIN_VERSION, true);

//      if (function_exists('is_product') && is_product()) {
	wp_enqueue_script('woo-ajax-add-to-cart');
//      }
}

// Add affiliate links to permalinks.  works with Yith Woocommerce Affiliate plugin
add_filter ('post_type_link', 'bbz_add_affiliate_ref', 10, 3);
function bbz_add_affiliate_ref ($permalink, $post, $leavename) {
	$token = '';
	if (class_exists('YITH_WCAF_Affiliate_Handler') ) {
		$user_id = get_current_user_id();

		if ( ! empty ($user_id) && !current_user_can('administrator')) {  // don't add affiliate ref for admin
			$WCAF_handler = YITH_WCAF_Affiliate_Handler();
			$affiliate = $WCAF_handler->get_affiliate_by_user_id ($user_id, $enabled=true, $exclude_banned=true);
			if(!empty($affiliate)) {
				$token = $affiliate['token'];
				$ref_name = $WCAF_handler->get_ref_name();
			}
		}
		//echo add_query_arg ($ref_name, $token, $permalink);exit;
	}
	return empty ($token) ? $permalink : add_query_arg ($ref_name, $token, $permalink);
}

// ignore affiliate link if user is wholesale - i.e.no commission
add_filter ('yith_wcaf_is_valid_token', 'bbz_affiliate_exclude_wholesale', 10, 1);
function bbz_affiliate_exclude_wholesale ($is_valid_token) {
	if ($is_valid_token && bbz_is_wholesale_customer() ){
		return false;
	}
	return $is_valid_token;
}


/******
* Ensure shipping address is shown for wholesale customers
*
******/
add_filter ('woocommerce_ship_to_different_address_checked', 'bbz_woocommerce_ship_to_destination', 10, 1);
function bbz_woocommerce_ship_to_destination ($checked) {
	if (bbz_is_wholesale_customer()) {
		// Always show shipping address
		$checked = true;
	}
	return $checked;
}

/*********
* Remove product data tab from single product page
*
*/
 
add_filter( 'woocommerce_product_tabs', 'bbz_filter_product_tabs', 98 );
 
function bbz_filter_product_tabs ( $tabs ) {
  unset( $tabs['additional_information'] ); // To remove the additional information tab
  return $tabs;
}

/********
* Extend remember me period
*
*/
function bbz_remember_me_expiration( $expiration ) {
    return 86400 * BBZ_LOGGED_IN_DAYS; // number of seconds
}
add_filter( 'auth_cookie_expiration', 'bbz_remember_me_expiration' );

/********
* Display cart weight totals
*
*/
function bbz_cart_weight () {
		$cart_weight = WC()->cart->get_cart_contents_weight();
		
		?>
<tr class="total-weight">
	<th>Total Weight</th>
	<td data-title="Total Weight"><?php echo esc_html( wc_format_weight( $cart_weight ) ); ?></td>
</tr>
<?php
}

add_action ('woocommerce_cart_totals_after_order_total', 'bbz_cart_weight');
add_action ('woocommerce_review_order_after_order_total', 'bbz_cart_weight');

/******
* Block emails for zoho created orders
*
*****/

function bbz_filter_woocommerce_email ( $recipient, $order, $email ) { 
    if ( ! $order || ! is_a( $order, 'WC_Order' ) ) return $recipient;
    
    // If transaction_id starts with 'ZOHO' - set in class bbz_orders
    if ( substr($order->get_transaction_id (), 0, 4) === 'ZOHO') {
        $recipient = '';
    }
    return $recipient;
}
add_filter( 'woocommerce_email_recipient_new_order', 'bbz_filter_woocommerce_email', 10, 3 );
add_filter( 'woocommerce_email_recipient_customer_on_hold_order', 'bbz_filter_woocommerce_email', 10, 3 );
add_filter( 'woocommerce_email_recipient_customer_processing_order', 'bbz_filter_woocommerce_email', 10, 3 );
add_filter( 'woocommerce_email_recipient_customer_pending_order', 'bbz_filter_woocommerce_email', 10, 3 );



/******
*	bbz_sales_history shortcode
*
*	Used to output sales history for current user and product
*	Used on trade ordering page
*	Code in classes/bbz-sales-history.php
******/

add_shortcode ('bbz_sales_history', array ('bbz_sales_history','shortcode'));

/******
*	Add shortcode to get author slug.  Used in Query to get 'About the Author' detail for product page.
*
******/

add_shortcode ('bbz_author_slugs', 'bbz_get_author_slugs');

function bbz_get_author_slugs () {
	$post_id = get_queried_object_id();
	$args = array('fields'=>'slugs');
	$terms = wp_get_post_terms($post_id, 'pa_author-2', $args);
	return implode(',', $terms);
}


