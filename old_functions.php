<?php
/*
    Plugin Name:    Bittern Books Zoho Connector
    Plugin URI:     http://www.unilake.co.uk/
    Description:    Elements to link Bittern Books website to Zoho
    Author:         Steve Haines
    Author URI:     http://www.unilake.co.uk/
    Version:        2.0802
    Requirements:   PHP 5.2.4 or above, WordPress 3.4 or above.
*/

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}


// Add meta attributes if RankMath plugin installed.

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

/*****
* bbz_user_banner
*
* Places a banner at the top of the page before the main content
* css user-banner defined in the theme custom css file
*
* This could be improved by making the text configurable through the admin interface
******/
 
add_action ( 'woocommerce_before_main_content', 'bbz_user_banner');
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
