<?php
/**
 * Short Description plugin
 *
 * These functions allow the short description to be populated with data from 
 * product attribute fields
 *
 * 
 */
 
 // If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

function bbz_short_description ($post_excerpt) {

	$attr_map = array (
		'isbn'	=> 'ISBN',
		'author' => 'Author',
		'format' => 'Format',
		'pages'	=> 'Pages',
		'publisher' => 'Publisher',
		'year'	=> 'Year',
	);
	$options = get_option (OPTION_NAME);
	if (isset($options['sd_enable']) && $options['sd_enable'] == 'on') {
		$html = '<table class="bbz-product-attributes"><tbody>';
		$lines = 0;
		global $product;
		if (!empty ($product) ) {
			foreach ($attr_map as $name => $label) {
				if (isset($options[$name])) {  // map to attribute names set up in configuration
					$value = $product->get_attribute ($options[$name]);
					if ($value == ''  && $name == 'isbn') $value = $product->get_sku();
					if ($value !== '') {
						$html .= '<tr class="bbz-product-attributes-item">'.
							'<td class="bbz-product-attributes_label">'.$label.'</td>'.
							'<td class="bbz-product-attributes_value">'.$value.'</td>'.
							'</tr>';
						$lines += 1;
					}
				}
			}
		}
		$html .= '</tbody></table>';

//		if ($lines > 0) {
			$post_excerpt = $html.$post_excerpt;
//		}
	}
	return $post_excerpt;
}

add_filter( 'woocommerce_short_description', 'bbz_short_description', 10, 1 );

?>