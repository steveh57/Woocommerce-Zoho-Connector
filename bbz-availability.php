<?php
/********************************************************
* BBZ-AVAILABILITY
*
* This module contains availability and visibility filters to
* control who sees which products according to various parameters
* transferred over from Zoho Items.
* BBZ_PM_INACTIVE_REASON is text from the Availability field in the Zoho Item
* BBZ_PM_AVAILABILITY is a sanitised version of the Availability field
*		all lower case with spaces replace with hyphen -
* BBZ_PM_RESTRICTIONS is one of 'none' | 'wholesale-only' | 'retail_only'
* 
*/

 // If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}
/*******
*
* Product availability codes are defined for each item in Zoho and used to control who can purchase
* products - retail or wholesale customers - and whether they can be backordered.
* Also to avoid issues with retail customers, we generally want to hide products they can't order,
* although we do allow to see them in searches.
* In Woocommerce values to control visibility, purchasability and backordering are stored in product meta
* but can be overridden dyneamically in most cases using filters.
* This module contains functions to set the stored values, and filters for dynamic control
******/

// Define availability codes to allow backorders for wholesale and retail customers
define ('BBZ_AVAIL_WHOLESALE_BACKORDER', ARRAY ('available', 'pre-order', 'coming-soon', 'special-order'));
define ('BBZ_AVAIL_RETAIL_BACKORDER', ARRAY ('pre-order'));
// Define availability codes to allow purchasing for wholesale and retail customers
define ('BBZ_AVAIL_WHOLESALE_PURCHASE', ARRAY ('available', 'pre-order', 'coming-soon', 'special-order'));
define ('BBZ_AVAIL_RETAIL_PURCHASE', ARRAY ('available', 'pre-order'));
// Define availability code to allow pre-order - same for both retail and wholesale customers
define ('BBZ_AVAIL_PRE', array ('pre-order'));

// Identify availability classes that can be backordered if out of stock
// Note Purchasable filter restricts backordering to wholesale customers

// The following functions are used in the product class to set the stored values for availability settings
// Most of these are overridden dynamically by filter calls

function bbz_default_backorders ($product_id) {
	$availability = get_post_meta ($product_id, BBZ_PM_AVAILABILITY, true);
	return in_array ($availability, BBZ_AVAIL_WHOLESALE_BACKORDER);
}
function bbz_default_visibility ($product_id, $stock) {
	//visible if: in stock OR pre-order OR special-order
	$availability = get_post_meta ($product_id, BBZ_PM_AVAILABILITY, true);
	return in_array ($availability, BBZ_AVAIL_WHOLESALE_PURCHASE) ? 'visible' : 'search';
}


/*****
* bbz_is_restricted
* 
* Checks for retail-only and wholesale-only restrictions on product
* Blank user_id defaults to current user.
******/

function bbz_is_restricted ($product_id, $user_id='') {
	$restrictions = get_post_meta ($product_id, BBZ_PM_RESTRICTIONS, true);
	$wholesale = bbz_is_wholesale_customer ($user_id);
	if ($wholesale) {
		if ($restrictions === 'retail-only') return true;
	} else {
		if ($restrictions === 'wholesale-only') return true;
	}
	return false;
}
/****
* bbz_get_availability
*
* Determines availability of a particular product for a particular user
* The output of this function is used in most of the filters with the objective
* of keeping most of the decision tree in one place.
* Takes into account:
*	Availability code from Zoho
*	Customer type - retail or wholesale
*	Stock availability
*	Wholesale only or Retail only restrictions from Zoho
*	Any release date set in Zoho
*
* Parameters
* 	product_id	post/product id from wordpress/woocommerce
*	user_id		optional - defaults to current user
*
* Returns an array
*	code	Can be one of:
*		available		in stock and anyone can buy
*		restricted		not permitted for this user to buy
*		special-order	special order item, available for backorder (even if in stock)
*		pre-order		not in stock but pre-order (backorder) available
*		not-available	not available to buy
* 	stock	current stock quantity
*	backorder	boolean if backorder permitted
*	purchasable	boolean, true if purchasing permitted
*	reason	text from Zoho giving reason for display to user
*
*******/


function bbz_get_availability ($product_id, $user_id='') {
	$availability = get_post_meta ($product_id, BBZ_PM_AVAILABILITY, true);
	$product = wc_get_product ($product_id);
	$wholesale = bbz_is_wholesale_customer ($user_id);
	
	// is item released yet? (if no date, assume yes)
	$release_date = get_post_meta ($product_id, BBZ_PM_RELEASE_DATE, true);
	$released = empty ($release_date) ? true : strtotime ($release_date) >= strtotime ('today');
	$stock = $product->get_stock_quantity();
	
	$result = array(
		'stock' => $stock,
		'code' => 'not-available',
		'backorder' => $wholesale // boolean
			? in_array ($availability, BBZ_AVAIL_WHOLESALE_BACKORDER) 
			: in_array ($availability, BBZ_AVAIL_RETAIL_BACKORDER),
		'reason' => get_post_meta ($product_id, BBZ_PM_INACTIVE_REASON, true),
		'purchasable' => $wholesale // boolean
			? in_array ($availability, BBZ_AVAIL_WHOLESALE_PURCHASE) 
			: in_array ($availability, BBZ_AVAIL_RETAIL_PURCHASE),
		);
	if ($stock==0 && $availability === 'available') $result['reason'] = 'Out of Stock';	
	

	// Check if restricted: wholesale only or retail only
	if (bbz_is_restricted ($product_id, $user_id)) {
		$result['code'] = 'restricted';
		$result['backorder'] = false;
		$result['purchasable'] = false;
	
	// special order items only available to wholesale
	} elseif ($availability === 'special-order') {
		$result ['code'] = $wholesale ? 'special-order' : 'restricted';
		
	// if we have stock, and released (excl special and restricted), then anyone can order
	} elseif ($stock>0 && $released ) {
		$result['code'] = 'available';
		$result['purchasable'] = true;
	} elseif (in_array ($availability, BBZ_AVAIL_PRE)) {
		$result['code'] = 'pre-order';
		
	} elseif ($result['backorder']) {
		$result['code'] = 'backorder';
	} // else default to not-available
	return $result;
}

/****
* bbz_availability_text
*
* Used to generate the availability text displayed to the customer cart box on the
* single product page
*****/

function bbz_availability_text ($product_id, $user_id = '', $availability='') {
	
	if (!is_array ($availability)) $availability = bbz_get_availability ($product_id, $user_id);
	$text = 'Not available'; //default
	
	switch ($availability['code']) {
		case 'available':
			if ($availability['stock'] > 30) {
				$text = 'More than 30 in stock';
			} elseif ($availability['stock'] > 6) {
				$text = $availability['stock'].' available in stock';
			} elseif ($availability['stock'] > 0) {
				$text = $availability['stock'].' left in stock';
				if ($availability['backorder'] ) {
					$text .= ', backorder available';
				} else {
					$text = 'Only '.$text;
				}
			} else $text = 'Out of stock'; //shouldn't get here!
			break;
		case 'pre-order':
			$text = 'Available to pre-order.';
			$release_date = get_post_meta ($product_id, BBZ_PM_RELEASE_DATE, true);
			if (!empty ($release_date)) {
				$text .= ' Release date '.date ('jS F Y', strtotime ($release_date));
			}
			break;
		case 'backorder': 
			$text = $availability['reason'].' - available to backorder'; break;
			
		case 'special-order':
			$text = 'Special order item - 2-4 weeks delivery'; 	break;
			
		case 'restricted': 
			$text = 'Sorry, not available to order'; break;
			
		case 'not-available': $text = $availability['reason']; break;
	}
	return $text;
}

/*********
* Availability text filter
*
* Called from the cart box
* stock_status is an array 
*	'availability' => availability text
*	'class' => 'out-of-stock', 'available-on-backorder', 'in-stock';
*/
add_filter( 'woocommerce_get_availability', 'bbz_availability_filter', 20, 2);

function bbz_availability_filter( $stock_status, $product, $user_id='' ) {
	// parameter is array with
	// 'availability' => availability text
	// 'class' => 'out-of-stock', 'available-on-backorder', 'in-stock';
	$code_map = array (
		'not-available' => 'out-of-stock',
		'restricted' => 'out-of-stock',
		'special-order' => 'available-on-backorder',
		'backorder' => 'available-on-backorder',
		'pre-order' => 'available-on-backorder',
		'available' => 'in_stock'
		);
		
	$product_id = $product->get_id();
	$reason = get_post_meta ($product_id, BBZ_PM_INACTIVE_REASON, true);
	$availability = bbz_get_availability ($product_id, $user_id);
	
	return array (
		'availability' => bbz_availability_text($product_id, $user_id, $availability),
		'class' => $code_map [$availability['code']],
		);
}

/*****
* bbz_availability_sc
* 
* Implementation of 'bbz_availability' shortcode to return the 
* availability text.
* May not be used anywhere as the text is displayed automatically 
* as part of the cart box calling the woocommerce_get_availability filter
* (see above)
*******/

add_shortcode ('bbz_availability', 'bbz_availability_sc');
function bbz_availability_sc ($atts = array() ) {
	$user_id =isset($atts['user'])?  $atts['user'] : '';
	global $product;
//	$product_id = isset($atts['product']) ? $atts['product'] : $product->get_id();
	if (isset($atts['product'])) {
		$product_id = $atts['product'] ;
	} else $product_id = $product->get_id();
	return bbz_availability_text ($product_id, $user_id);
}


/******
* Visibility Filter
*
* Return true or false
* Called from wc_product->is_visible()
* Note the default visibility must be true for this to work:
* i.e item must be published, catalog visibility for the product set to 'shop and search'
*  and Out of Stock Visibility on Woocommerce Settings/Product/Inventory must be unchecked.
******/
add_filter ('woocommerce_product_is_visible', 'bbz_is_visible',20,2);
add_filter ('woocommerce_variation_is_visible', 'bbz_is_visible',20, 2);

function bbz_is_visible ($visible, $product_id, $user_id='') {
	if (bbz_is_admin ($user_id)) return true;  // always visible for admin
	// $user_id is primarily for testing purposes
	if ($visible && !bbz_get_availability ($product_id, $user_id)['purchasable']) {
		// if not available to buy, only visible in search
		$visible = is_search();
	}			
	return $visible;
}

/***
* Backorder Filters
*
* Overrides backorders and stock status settings in product data
*****/

// internal function returns true or false depending on product and user role
function bbz_backorders_allowed ($product, $user_id) {
	return bbz_get_availability ($product->get_id(), $user_id)['backorder'];
}
// Filter called from wc_product->get_backorders
// return yes, no or notify
add_filter('woocommerce_product_get_backorders','bbz_get_backorders', 10, 2 );
add_filter('woocommerce_product_variation_get_backorders', 'bbz_get_backorders', 10, 2);
function bbz_get_backorders( $backorders_status, $product, $user_id='' ){
	// $user_id is primarily for testing purposes
	return bbz_backorders_allowed ($product, $user_id)? 'notify' : 'no';
}

// Filter called from wc_product->get_stock_status
// return 'instock', 'onbackorder' or 'outofstock'
// modifies the out of stock status depending on whether backorders are allowed for this user.
add_filter( 'woocommerce_product_get_stock_status', 'bbz_get_stock_status', 10, 2);
add_filter( 'woocommerce_product_variation_get_stock_status', 'bbz_get_stock_status', 10, 2 );
function bbz_get_stock_status( $stock_status, $product, $user_id='' ) {
    if ( 'instock' !== $stock_status ) {
		$stock_status = bbz_backorders_allowed ($product, $user_id) ? 'onbackorder' : 'outofstock';
    }
    return $stock_status;
}

// modify the text on backorder notification on the cart - this is displayed underneath
// the product name on the cart page.
// Changes 'Available on back-order' to 'Special order item'
add_filter( 'woocommerce_cart_item_backorder_notification','bbz_cart_item_backorder_notification', 10, 2);
function bbz_cart_item_backorder_notification ($text, $product_id, $user_id='') {
	if (bbz_get_availability ($product_id, $user_id)['code'] === 'special-order') {
		$text = strstr($text, '>', true).'>Special order item'.strrchr($text, '<');
		//$text = str_replace ('Available on back-order', 'Special order item', $text);
	}
	return $text;
}

// no filter required for woocommerce_product_backorders_allowed as derived from get_backorders

/* Purchasable filter
* Called from wc_product->is_purchasable
* stops backordered items being sold to retail customers.
*/
add_filter( 'woocommerce_is_purchasable', 'bbz_is_purchasable', 20, 2);
function bbz_is_purchasable( $purchasable, $product, $user_id='' ) {
	return $purchasable && bbz_get_availability ($product->get_id(), $user_id)['purchasable'];
}

/**********
* ADD TO CART TEXT
*
* Change add to cart text for pre-orders and backorders
*******/

add_filter( 'woocommerce_product_single_add_to_cart_text', 'bbz_add_to_cart_button_text', 10, 2 ); 
add_filter( 'woocommerce_product_add_to_cart_text', 'bbz_add_to_cart_button_text', 10, 2 );

function bbz_add_to_cart_button_text ( $add_to_cart_text, $product, $user_id='' ) {
	$availability = bbz_get_availability ($product->get_id(), $user_id);
	if ($availability['purchasable'])
		
	switch ($availability['code']) {
		case 'pre-order': 
			$add_to_cart_text = 'Pre-order'; break;
		case 'backorder': 
			$add_to_cart_text = 'Backorder'; break;
		case 'special-order':
			$add_to_cart_text = 'Special order'; break;
		//case 'restricted':
		//case 'not-available':
	}
    return $add_to_cart_text; 
}


