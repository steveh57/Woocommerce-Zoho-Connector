<?php
// This file includes miscellaneous funnctions, actions and filters
// included here for ease of maintenance rather than in functions.php
// Some functions that are very specific to the theme layout remain in functions.php in the child theme.

 // If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
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
		if( is_user_logged_in() ) {
			$user = wp_get_current_user();
			$roles = ( array ) $user->roles;

			foreach($roles as $value){
				if($value=="wholesale_customer"){
					$banner_text = "Free shipping for orders over £60";
				}
			}
		}
		echo '<div class="bbz-user-banner">'. $banner_text.'</div>';
	}
}

?>