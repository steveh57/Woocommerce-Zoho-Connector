<?php
 // If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

include_once ( dirname( __FILE__ ) . '/bbz-definitions.php');

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

/**
 * Hide shipping rates when free shipping is available.
 * Updated to support WooCommerce 2.6 Shipping Zones.
 *
 * @param array $rates Array of rates found for the package.
 * @return array
 */
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


/**
 * Removes the "Additional Information" tab that displays the product attributes.
 * 
 * @param array $tabs WooCommerce tabs to display.
 * 
 * @return array WooCommerce tabs to display, minus "Additional Information".
 */
// add_filter( 'woocommerce_product_tabs', 'bbz_remove_product_attributes_tab', 100 );

 function bbz_remove_product_attributes_tab( $tabs ) {
 
    unset( $tabs['additional_information'] );
    return $tabs;
 }

/**
 * Displays product attributes in the top right of the single product page.
 * 
 * @param $product
 */ 
// add_action( 'woocommerce_product_meta_end', 'jmj_list_attributes' );
function jmj_list_attributes( $product ) {
	global $product;
	global $post;
	 
	$attributes = $product->get_attributes();
	if ( ! $attributes ) return;   
	 
	foreach ( $attributes as $attribute ) {
		if(strpos($attribute[ 'name' ], "pa_") !== false){
			// Contains "pa_"? Then it's an attribute array and you're free to run the code

			// Get the taxonomy.
			$terms = wp_get_post_terms( $product->get_id(), $attribute[ 'name' ], 'all' );
			$taxonomy = $terms[ 0 ]->taxonomy;
				
			// Get the taxonomy object.
			$taxonomy_object = get_taxonomy( $taxonomy );
			
			// Get the attribute label.
			$attribute_label = $taxonomy_object->labels->name;
			
			// Display the label followed by a clickable list of terms.
			$terms_as_text = get_the_term_list( $post->ID, $attribute[ 'name' ] ,
				'<span class="attributes">' . $attribute_label . ': ' , ', ', '</span>' );
			if (!empty($terms_as_text)){
				echo str_replace("Product","",$terms_as_text);
			}
		}
	}
}
 

// Re order product single hooks
/*
remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_meta', 40 );
add_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_meta', 1 );

remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
add_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 15 );
*/

// possible to replace 
function bbz_user_banner() { 
		
	// Display on cart or checkout only, add other conditions here to display on more pages
	if(is_cart() || is_checkout() ){ 
		$roles = "";
		$shipping_notice = "Free shipping for orders over £30";
		if( is_user_logged_in() ) {
			$user = wp_get_current_user();
			$roles = ( array ) $user->roles;

			foreach($roles as $value){
				if($value=="wholesale_customer"){
					$shipping_notice = "Free shipping for orders over £60";
				}
			}
		}
		echo '<div class="shipping-notice">'. $shipping_notice.'</div>';
	}
}

?>