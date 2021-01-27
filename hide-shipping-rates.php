<?php
/**
 * Plugin Name:       Hide shipping rates
 * Plugin URI:        https://janih.eu/
 * Description:       Hide shipping rates when free shipping is available.
 * Version:           0.01
 * Author:            Jani Huumonen
 * Author URI:        https://janih.eu/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

/* Copyright 2020 Jani Huumonen */

namespace JHWHSF;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

/**
 * Hide shipping rates when free shipping is available.
 * Updated to support WooCommerce 2.6 Shipping Zones.
 *
 * @param array $rates Array of rates found for the package.
 * @return array
 */
function hide_shipping_when_free_is_available( $rates ) {
	$free = array();
	foreach ( $rates as $rate_id => $rate ) {
		if ( 'free_shipping' === $rate->method_id ) {
			$free[ $rate_id ] = $rate;
			break;
		}
	}
	return ! empty( $free ) ? $free : $rates;
}
add_filter( 'woocommerce_package_rates', 'JHWHSF\hide_shipping_when_free_is_available', 100 );

