<?php
// This file includes miscellaneous funnctions, actions and filters
// included here for ease of maintenance rather than in functions.php
// Some functions that are very specific to the theme layout remain in functions.php in the child theme.

 // If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}
/**
 * @snippet       WooCommerce Holiday/Pause Mode
 * @how-to        Get CustomizeWoo.com FREE
 * @sourcecode    https://businessbloomer.com/?p=20862
 * @author        Rodolfo Melogli
 * @testedwith    WooCommerce 3.5.1
 * @donate $9     https://businessbloomer.com/bloomer-armada/
 */
 
// Trigger Holiday Mode
 
//add_action ('init', 'bbloomer_woocommerce_holiday_mode');
 
 
// Disable Cart, Checkout, Add Cart
 
function bbloomer_woocommerce_holiday_mode() {
   remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10 );
   remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
   remove_action( 'woocommerce_proceed_to_checkout', 'woocommerce_button_proceed_to_checkout', 20 );
   remove_action( 'woocommerce_checkout_order_review', 'woocommerce_checkout_payment', 20 );
   add_action( 'woocommerce_before_main_content', 'bbloomer_wc_shop_disabled', 5 );
   add_action( 'woocommerce_before_cart', 'bbloomer_wc_shop_disabled', 5 );
   add_action( 'woocommerce_before_checkout_form', 'bbloomer_wc_shop_disabled', 5 );
}
 
 
// Show Holiday Notice
 
function bbloomer_wc_shop_disabled() {
        wc_print_notice( 'Our Online Shop is Closed Today :)', 'error');
} 



/*********
* Filter to add isbn to structured product data used by google
*
**********/

add_filter( 'woocommerce_structured_data_product', 'bbz_structured_data_product_filter', 20, 2);

function bbz_structured_data_product_filter ( $markup, $product) {
	$sku = $product->get_sku();
	if (strlen ($sku) == 13 & strpos ($sku, "978")===0) { // Only use sku as isbn if it has 13 chars starting 978
		$markup['isbn'] =  $sku; // Set isbn to product sku.
	}
    return $markup;
}
// above doesn't work if RankMath plugin installed.  Try this

add_filter( 'rank_math/snippet/rich_snippet_product_entity', "bbz_add_product_meta");
function bbz_add_product_meta ( $entity ) {
    if(is_product()){
        global $product;
		$sku = $product->get_sku();
        if (strlen ($sku) == 13 & strpos ($sku, "978")===0) {
              $entity['isbn'] = $sku;
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
				$entity [$entname] = $attribute;
			}
		}
    }
    return $entity;
}

/*********
 * Availability text filter
 *
 * Removes "(can be backordered)" from the In stock message
 */
add_filter( 'woocommerce_get_availability', 'bbz_availability_filter', 20, 2);

function bbz_availability_filter( $availability ) {
	// parameter is array with
	// 'availability' => availability text
	// 'class' => 'out-of-stock', 'available-on-backorder', 'in-stock';
	$text = $availability['availability'];
	global $post;
	$zoho_text = get_post_meta ($post->ID, BBZ_PM_INACTIVE_REASON, true);
	switch ($availability['class']) {
		case 'out-of-stock':
			$text = !empty ($zoho_text) ? $zoho_text : 'Not available';
			break;
	
		case 'available-on-backorder':
			if (in_array ($zoho_text, BBZ_AVAIL_PRE)) {
				$text = 'Available to pre-order';
			} else {
				$text = !empty ($zoho_text) ? $zoho_text." - available to backorder" : 'Available to backorder';
			}
			break;
			
		default:
			$text = str_replace (' (can be backordered)', '', $text);
	}
	$availability['availability'] = $text;
	return $availability;
}

/*********
 * Availability text filter for trade order form
 *
 * Replaces 'out of stock' with more informative message
 */
add_filter( 'wwof_filter_product_item_action_controls', 'bbz_wwof_availability_filter', 20, 3);

function bbz_wwof_availability_filter( $action_field, $product, $alternate ) {
	// action field is html text for display in trade order form
	$availability = get_post_meta ($product->get_id(), BBZ_PM_INACTIVE_REASON, true);
	if (strpos ($action_field, 'Out of Stock')!==false && is_string($availability)) {
		$action_field = str_replace ("Out of Stock", $availability, $action_field);
	} elseif ( strpos ($action_field, 'Add To Cart')!==false && $product->get_stock_status() == 'onbackorder') {
		if (in_array ($availability, BBZ_AVAIL_PRE)) {
			$action_field = str_replace ('Add To Cart', $availability, $action_field);
		} else {
			$action_field = str_replace ('Add To Cart', 'Backorder', $action_field);
		}
	}
	return $action_field;
}

/* Purchasable [sic] filter
* 
* stops backordered items being sold to retail customers.
*/

add_filter( 'woocommerce_is_purchasable', 'bbz_is_purchasable_filter', 20, 2);

function bbz_is_purchasable_filter( $purchasable, $product ) {
	if ($purchasable && in_array ($product->get_stock_status(), array ('onbackorder', 'outofstock'))) {
		$availability = get_post_meta ($product->get_id(), BBZ_PM_INACTIVE_REASON, true);
		if (bbz_is_wholesale_customer()) {
			if (in_array ($availability, BBZ_AVAIL_OFF) ) return false;
		} else {  // retail customer - allow pre orders
			if (!in_array ($availability, BBZ_AVAIL_PRE)) return false;
		}
		
	}
	return $purchasable;
}

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
 * Remove the "Additional Information" tab that displays the product attributes.
 */
 
add_filter( 'woocommerce_product_tabs', 'bbz_remove_product_attributes_tab', 100 );
function bbz_remove_product_attributes_tab( $tabs ) {
    unset( $tabs['additional_information'] );
    return $tabs;
}

/**
 * Display product attributes in the top right of the single product page.
 * 
 * @param $product
 */ 
add_action( 'woocommerce_product_meta_end', 'bbz_list_attributes' );
function bbz_list_attributes( $product ) {
	global $product;
	global $post;
 
	$attributes = $product->get_attributes();
	if ( ! $attributes ) {
		return;
	}
	 
	foreach ( $attributes as $attribute ) {
		if(strpos($attribute[ 'name' ], "pa_") !== false){
			// Contains "pa_"? Then it's an attribute array and you're free to run the code

			// Get the taxonomy.
			$terms = wp_get_post_terms( $product->get_id(), $attribute[ 'name' ], 'all' );
			$taxonomy = $terms[ 0 ]->taxonomy;
			$taxonomy_object = get_taxonomy( $taxonomy );
			$attribute_label = $taxonomy_object->labels->name;
			
			// Display the label followed by a clickable list of terms.
			$terms_as_text = get_the_term_list( $post->ID, $attribute[ 'name' ] ,
				'<span class="attributes">' . $attribute_label . ': ' , ', ', '</span>' );
			if (!empty($terms_as_text)){
	//			$tags_stripped = strip_tags($terms_as_text, '<span>');
	//			echo str_replace("Product","",$tags_stripped);
				echo str_replace("Product","",$terms_as_text);
			}
		}
	}
}

/**
 * Hide shipping rates when free shipping is available.
 * Updated to support WooCommerce 2.6 Shipping Zones.
 *
 * @param array $rates Array of rates found for the package.
 * @return array
 */
 add_filter( 'woocommerce_package_rates', 'bbz_hide_shipping_when_free_is_available', 100 );

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


/*****
* bbz_user_banner
*
* Places a banner at the top of the page before the main content
* css user-banner defined in the theme custom css file
*
* This could be improved by making the text configurable through the admin interface
******/
 
// add_action ( 'woocommerce_before_main_content', 'bbz_user_banner');
add_action ( 'woocommerce_before_cart', 'bbz_user_banner');
add_action ( 'woocommerce_before_checkout_form', 'bbz_user_banner');
function bbz_user_banner() { 
		
	// Display on cart or checkout only, add other conditions here to display on more pages
	if(is_cart() || is_checkout() ){ 
		$roles = "";
		$banner_text = "Free shipping for orders over £30";
		if( bbz_is_wholesale_customer () ) {
			$banner_text = "Free shipping for orders over £60";
		}
		echo '<div class="bbz-user-banner">'. $banner_text.'</div>';
	}
}

/**
 * Order handling
 * Called when order is complete to post order in Zoho
 */

add_action( 'woocommerce_thankyou', 'bbz_order_processing');

function bbz_order_processing( $order_id ){	
	// spawn cron job to process the order asap (short delay to allow current page to complete)
	wp_schedule_single_event (time() + 30, 'bbz_process_order_hook', array ('order_id'=>$order_id));
}

add_action ('bbz_process_order_hook', 'bbz_process_single_order', 10, 2);

function bbz_process_single_order ( $order_id ) {
	$order = new bbz_order ($order_id);
	$order->process_new_order ();
	// error handling is dealt with by process_new_order
}

/*****
* Set up hourly and daily cron jobs
*
******/

if ( ! wp_next_scheduled( 'bbz_hourly_cron' ) ) {
    wp_schedule_event( time(), 'hourly', 'bbz_hourly_cron' );
}
if ( ! wp_next_scheduled( 'bbz_daily_cron' ) ) {
    wp_schedule_event( strtotime ('01:00 tomorrow'), 'daily', 'bbz_daily_cron' );
}

/******
* Order processing
* Called as a cron job to process any new orders to be sent to zoho and check if any are completed
*****/

add_action ('bbz_hourly_cron', 'bbz_process_orders');
function bbz_process_orders ($resubmit=false) {
	$orders = wc_get_orders (array ('status'=>'processing'));
	foreach ($orders as $order) {
		$bbz_order = new bbz_order ($order);
		if ( empty($bbz_order->get_zoho_order_id())) {
			//order not yet submitted to zoho
			$bbz_order->process_new_order($resubmit);
		} else {
			// check if order completed yet
			$bbz_order->update_order_status();
		}
	}
}

/*****
*  Daily database updates
*
*****/
// some error handling to notify admin if it fails would be a good idea...

add_action ('bbz_daily_cron', 'bbz_daily_user_update');
add_action ('bbz_daily_cron', 'bbz_update_products');  // function in bbz_utils

function bbz_daily_user_update () {
	bbz_load_sales_history ('all');
	bbz_update_payment_terms ('all');
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

		if ( ! empty ($user_id)) {
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
// block paypal payment for wholsale user
// not using the option built into wholesale pricing as this overrides the decision on whether to allow
// payment on account.
//add_filter( 'woocommerce_available_payment_gateways' , 'bbz_filter_available_payment_gateways' , 100 , 1 );
function bbz_filter_available_payment_gateways( $available_gateways ) {

	if ( current_user_can( 'manage_options' ) || !bbz_is_wholesale_customer() )
		return $available_gateways;
	
	// for wholesale customer, block paypal as an option
	$filtered_gateways  = array();
	foreach ( $available_gateways as $gateway )
		if ( !strstr($gateway->get_title(), 'paypal') ) 
			$filtered_gateways[ $gateway->id ] = $gateway;

	if ( !empty( $filtered_gateways ) ) {

		WC()->payment_gateways()->set_current_gateway( $filtered_gateways );
		
		return $filtered_gateways;

	} else
		return $available_gateways;
}
// Info message before payment options
		// insert a message about credit availability
//add_action ('woocommerce_review_order_before_payment', 'bbz_action_before_payment');
function bbz_action_before_payment () {
	if (bbz_is_wholesale_customer()) {
		$user_meta = new bbz_usermeta ();
		$terms = $user_meta->get_payment_terms ();
		if (!empty ($terms ['name'] ) && isset($terms['available_credit']) && $terms['available_credit'] > 0 ) {
			echo 'Payment on account is available.  Terms: ', $terms['name'];
			if (isset($terms['available_credit']) && $terms['available_credit'] > 0) {
				echo 'Available credit: ', $terms['available_credit'];
			}
		} else {
			echo 'Sorry, payment on account is not available.  Please contact Bittern Books to arrange credit facilities.';
		}
	}
}

 
 
?>