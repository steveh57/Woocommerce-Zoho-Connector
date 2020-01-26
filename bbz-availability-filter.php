<?php
/**
 * Availability text filter
 *
 * Removes "(can be backordered)" from the In stock message
 */
 
 // If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

include_once ( dirname( __FILE__ ) . '/bbz-definitions.php');


/*** Availability text filter **/

add_filter( 'woocommerce_get_availability', 'bbz_availability_filter', 20, 2);

function bbz_availability_filter( $availability ) {
	// parameter is array with
	// 'availability' => availability text
	// 'class' => 'out-of-stock', 'available-on-backorder', 'in-stock';
	$text = $availability['availability'];
	global $post;
	switch ($availability['class']) {
		case 'out-of-stock':
			$newtext = get_post_meta ($post->ID, BBZ_PM_INACTIVE_REASON, true);
			$text = !empty ($newtext) ? $newtext : 'Not available';
			break;
	
		case 'available-on-backorder':
			$user = wp_get_current_user();
			foreach ($user->roles as $role) {
				if ('wholesale_customer' == $role) return $availability;
			}
			$text = 'Not currently available';  //backorder only available to wholesale users
			break;
			
		default:
			$text = str_replace ('In stock (can be backordered)', 'In stock', $text);
	}
	$availability['availability'] = $text;
	return $availability;
}

/* Purchasable [sic] filter
* 
* stops backordered items being sold to retail customers.
*/

add_filter( 'woocommerce_is_purchasable', 'bbz_is_purchasable_filter', 20, 2);

function bbz_is_purchasable_filter( $purchasable, $product ) {
	if ($purchasable && in_array ($product->get_stock_status(), array ('onbackorder', 'outofstock'))) {
		$user = wp_get_current_user();
		foreach ($user->roles as $role) {
			if ('wholesale_customer' == $role) return $purchasable;
		}
		return false;
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
?>