<?php
/**
 * Plugin Name:       Theme addons for Hestia & WooCommerce
 * Plugin URI:        https://janih.eu/
 * Description:       Shorten the shop product list description
 * Version:           0.01
 * Author:            Jani Huumonen
 * Author URI:        https://janih.eu/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

/* Copyright 2020 Jani Huumonen */

namespace JHWHTA;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

// Filter for Hestia shop loop product card: <div class="card-description">
add_filter( 'hestia_shop_excerpt_words', 'JHWHTA\limit_description_words' );

function limit_description_words ( $num_words )
{
	// default 60 words
	return 20;
}

/*
// Filter for regular woocommerce short description
add_filter( 'woocommerce_short_description', 'JHWHTA\limit_description' );
function limit_description ( $limited_excerpt )
{
	return $limited_excerpt;
}
*/
