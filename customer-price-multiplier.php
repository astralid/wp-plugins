<?php
/**
 * Plugin Name:       Customer Price Multiplier for WooCommerce
 * Plugin URI:        https://janih.eu/
 * Description:       Set a price multiplier for each customer
 * Version:           0.01
 * Author:            Jani Huumonen
 * Author URI:        https://janih.eu/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

/* Copyright 2020 Jani Huumonen */

namespace JHWCPM;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

const opt_name = 'JHWCPM_data';
const role_name = 'customer';

register_activation_hook(__FILE__, 'JHWCPM\activate');
function activate()
{
	register_deactivation_hook(__FILE__, 'JHWCPM\deactivate');
}
function deactivate()
{
	register_uninstall_hook(__FILE__, 'JHWCPM\uninstall');
}
function uninstall()
{
	// Remove from database all data added by this plugin
	foreach ( relevant_users() as $user )
		delete_user_meta( $user->ID, opt_name );
}
#	if ( ! current_user_can('activate_plugins') ) return;

function relevant_users()
{
	return get_users('orderby=nicename&role=' . role_name);
}
function get_multiplier() {
// TODO: hash friendly?
	 return (100 - (int)wp_get_current_user()->get(opt_name)) / 100;
}
function custom_price( $price, $product ) {
	return $price === '' ? '' : (float) $price * get_multiplier();
}
function add_price_multiplier_to_variation_prices_hash( $hash ) {
	$hash[] = get_multiplier();
	return $hash;
}

add_action('admin_menu', 'JHWCPM\add_users_menu');
add_action('init', 'JHWCPM\add_filters');

function add_filters()
{
	if ( in_array( role_name, wp_get_current_user()->roles ) ) {
		// Simple, grouped and external products
		add_filter('woocommerce_product_get_regular_price', 'JHWCPM\custom_price', 90, 2);
		add_filter('woocommerce_product_get_sale_price', 'JHWCPM\custom_price', 90, 2);
		add_filter('woocommerce_product_get_price', 'JHWCPM\custom_price', 90, 2);
		// Variations
		add_filter('woocommerce_product_variation_get_regular_price', 'JHWCPM\custom_price', 90, 2);
		add_filter('woocommerce_product_variation_get_sale_price', 'JHWCPM\custom_price', 90, 2);
		add_filter('woocommerce_product_variation_get_price', 'JHWCPM\custom_price', 90, 2);
		// Variable (price range)
		add_filter('woocommerce_variation_prices_regular_price', 'JHWCPM\custom_price', 90, 2);
		add_filter('woocommerce_variation_prices_sale_price', 'JHWCPM\custom_price', 90, 2);
		add_filter('woocommerce_variation_prices_price', 'JHWCPM\custom_price', 90, 2);
		// Handle price caching
		add_filter( 'woocommerce_get_variation_prices_hash', 'JHWCPM\add_price_multiplier_to_variation_prices_hash', 90, 1 );
	}
}
function add_users_menu()
{
	add_users_page(
		'Asiakaskohtaiset alennusprosentit',
		'Alennukset',
		'edit_users',
		'alennusasetukset',
		'JHWCPM\settings_page');
}
function settings_page()
{
	if ( !current_user_can( 'edit_users' ) )  {
		wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
	}
	$hidden_field_name = 'ale_submit_hidden';

	// if form has been posted
	if( isset($_POST[ $hidden_field_name ]) && $_POST[ $hidden_field_name ] == 'Y' ) {
		// read posted (<input name="opt_name[userID]">) values into an array [ userID => value ]
		$opt_val_arr = $_POST[ opt_name ];
		// save posted values to database
		foreach ( $opt_val_arr as $id => $val ) {
			update_user_meta( $id, opt_name, $val );
		}
		echo '<div class="updated"><p><strong>'. __('Settings saved.', 'woocommerce') .'</strong></p></div>';
	}

	echo '<div class="wrap">';
	echo "<h2>" . 'Asiakaskohtaiset alennusprosentit' . "</h2>";
?>
<form name="form1" method="post" action="">
<input type="hidden" name="<?php echo $hidden_field_name; ?>" value="Y">
<?php
	echo '<table border=1 cellpadding=5> <tr><th>Asiakas</th><th>Ale %</th></tr>';
	foreach ( relevant_users() as $user ) {
		echo '<tr>';
		echo '<td>'. esc_html( $user->display_name ) .'</td>';
		echo '<td><input name="'. opt_name .'['. $user->ID .']" type="text" value="'. get_user_meta( $user->ID, opt_name, true ) .'"></td>';
		echo '</tr>';
	}
	echo '</table>';
?>
<p class="submit">
<input type="submit" name="Submit" class="button-primary" value="<?php esc_attr_e('Save', 'woocommerce') ?>" />
</p>
</form>
</div>
<?php
}


