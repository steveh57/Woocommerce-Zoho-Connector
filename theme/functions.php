<?php
// Load stylesheet from Bittern theme
add_action( 'wp_enqueue_scripts', 'bb_enqueue_styles' , 99);
function bb_enqueue_styles() {
    wp_enqueue_style( 'bittern-style', get_stylesheet_directory_uri() . '/style.css'  );
}


add_action( 'after_setup_theme', 'avada_lang_setup' );
function avada_lang_setup() {
	$lang = get_stylesheet_directory() . '/languages';
	load_child_theme_textdomain( 'Avada', $lang );
}

// Add custom Theme Functions here


// Re order product single hooks

remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_meta', 40 );
add_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_meta', 1 );

remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
add_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 15 );


/**
 * Override loop template and show quantities next to add to cart buttons
 *
 * Note detail button is removed by means of a modified template wc-show-details-button in the child theme
 */
//add_filter( 'woocommerce_loop_add_to_cart_link', 'quantity_inputs_for_woocommerce_loop_add_to_cart_link', 10, 2 );
function quantity_inputs_for_woocommerce_loop_add_to_cart_link( $html, $product ) {
	if ( $product && $product->is_type( 'simple' ) && $product->is_purchasable() && $product->is_in_stock() && ! $product->is_sold_individually() ) {
		$html = '<form action="' . esc_url( $product->add_to_cart_url() ) . '" class="cart" method="post" enctype="multipart/form-data">';
		$html .= woocommerce_quantity_input( array(), $product, false );
		$html .= '<button type="button" class="fusion-button button-flat button-default add_to_cart_button ajax_add_to_cart">' .
		'<span class="fusion-button-text">' . esc_html( $product->add_to_cart_text() ) . '</span></button>';
		$html .= '</form>';
	}
	return $html;
}

/**
 * Create a shortcode for custom registration form
 */
  
// THIS WILL CREATE A NEW SHORTCODE: [wc_reg_form_bittern]
  
add_shortcode( 'wc_reg_form_bittern', 'bittern_registration_form' );
    
function bittern_registration_form() {
   if ( is_admin() ) return;
   if ( is_user_logged_in() ) return;
   ob_start();
 
   require get_stylesheet_directory().'/bb_custom_registration_form.php';  
   return ob_get_clean();
}

?>