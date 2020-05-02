<?php
/**
 * Show details button.
 *
 * @author     ThemeFusion
 * @copyright  (c) Copyright by ThemeFusion
 * @link       https://theme-fusion.com
 * @package    Avada
 * @subpackage Core
 * @since      5.1.0
 */
// modified to remove details button

global $product;

$has_quick_view = Avada()->settings->get( 'woocommerce_enable_quick_view' ) ? ' fusion-has-quick-view' : '';
$add_styles     = (bool) ( ( ! $product->is_purchasable() || ! $product->is_in_stock() ) && ! $product->is_type( 'external' ) );

/*
<a href="<?php echo esc_url_raw( get_permalink() ); ?>" class="show_details_button<?php echo esc_attr( $has_quick_view ); ?>"<?php echo ( $add_styles ) ? ' style="float:none;max-width:none;text-align:center;"' : ''; ?>>
	<?php esc_html_e( 'Details', 'Avada' ); ?>
</a>
*/
if ( Avada()->settings->get( 'woocommerce_enable_quick_view' ) ) : ?>
	<a href="#fusion-quick-view" class="fusion-quick-view" data-product-id="<?php echo esc_attr( $product->get_id() ); ?>"<?php echo ( $add_styles ) ? ' style="float:none;max-width:none;text-align:center;"' : ''; ?>>
		<?php esc_html_e( 'Quick View', 'Avada' ); ?>
	</a>
<?php endif; ?>
