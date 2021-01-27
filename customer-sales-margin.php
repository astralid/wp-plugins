<?php
/**
 * Plugin Name:       Customer Sales Margins for WooCommerce
 * Plugin URI:        https://janih.eu/
 * Description:       Set a sales margin percentage for each customer
 * Version:           0.01
 * Author:            Jani Huumonen
 * Author URI:        https://janih.eu/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

/* Copyright 2020 Jani Huumonen */

namespace JHWCSM;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

const opt_name = 'JHWCSM_data';
const role_name = 'customer';

register_activation_hook(__FILE__, 'JHWCSM\activate');
function activate()
{
	register_deactivation_hook(__FILE__, 'JHWCSM\deactivate');
}
function deactivate()
{
	register_uninstall_hook(__FILE__, 'JHWCSM\uninstall');
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
// TODO: set a default value (currently defaults to 1?)
	 return 1 / ( (100 - (int)wp_get_current_user()->get(opt_name)) / 100 );
}
function custom_price( $price, $product ) {
	return $price === '' ? '' : (float) $price * get_multiplier();
}
function add_price_multiplier_to_variation_prices_hash( $hash ) {
	$hash[] = get_multiplier();
	return $hash;
}

// $arr = posted (<input name="opt_name[userID]">) values as an array [ userID => value ]
function save_data( $arr ) {
	foreach ( $arr as $id => $val ) {
		update_user_meta( $id, opt_name, $val );
	}
	//TODO: success/failure
	return true;
}

add_action('admin_menu', 'JHWCSM\add_users_menu');
add_action('init', 'JHWCSM\add_filters');

function add_filters()
{
	if ( in_array( role_name, wp_get_current_user()->roles ) ) {
		// Simple, grouped and external products
		add_filter('woocommerce_product_get_regular_price', 'JHWCSM\custom_price', 90, 2);
		add_filter('woocommerce_product_get_sale_price', 'JHWCSM\custom_price', 90, 2);
		add_filter('woocommerce_product_get_price', 'JHWCSM\custom_price', 90, 2);
		// Variations
		add_filter('woocommerce_product_variation_get_regular_price', 'JHWCSM\custom_price', 90, 2);
		add_filter('woocommerce_product_variation_get_sale_price', 'JHWCSM\custom_price', 90, 2);
		add_filter('woocommerce_product_variation_get_price', 'JHWCSM\custom_price', 90, 2);
		// Variable (price range)
		add_filter('woocommerce_variation_prices_regular_price', 'JHWCSM\custom_price', 90, 2);
		add_filter('woocommerce_variation_prices_sale_price', 'JHWCSM\custom_price', 90, 2);
		add_filter('woocommerce_variation_prices_price', 'JHWCSM\custom_price', 90, 2);
		// Handle price caching
		add_filter( 'woocommerce_get_variation_prices_hash', 'JHWCSM\add_price_multiplier_to_variation_prices_hash', 90, 1 );
	}
}
function add_users_menu()
{
	add_users_page(
		'Asiakaskohtaiset kateprosentit',
		'Katteet',
		'edit_users',
		'kateasetukset',
		'JHWCSM\settings_page');
}

function print_feedback() {
//TODO: for some reason this is indented!
?>
	<div id="message" class="updated notice is-dismissible">
		<p><strong>
			<?php echo __('Settings saved.', 'woocommerce') ?>
		</strong></p>
	</div>
<?php
}
function settings_page()
{
	if ( !current_user_can( 'edit_users' ) ) {
		wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
	}

	if ( isset( $_POST['JHWCSM_nonce_field'] ) ) {
		if ( ! wp_verify_nonce( $_POST['JHWCSM_nonce_field'], 'JHWCSM_post' ) )
			wp_die( 'Sorry, your nonce did not verify.' );
		else save_data( $_POST[ opt_name ] ) && print_feedback();
	}
?>
	<h2>Asiakaskohtaiset kateprosentit</h2>
	<form method="post">
		<?php wp_nonce_field( 'JHWCSM_post', 'JHWCSM_nonce_field' ); ?>
		<table border=1 cellpadding=5>
			<thead><tr><th>Asiakas</th><th>Kate %</th></tr></thead>
			<?php foreach ( relevant_users() as $user ) { ?>
				<tr>
					<th><?php esc_html_e( $user->display_name ) ?></th>
					<td><input
						name="<?php echo opt_name .'['. $user->ID .']' ?>"
						type="text"
						value="<?php echo get_user_meta( $user->ID, opt_name, true ) ?>">
					</td>
				</tr>
			<?php } ?>
		</table>
		<p class="submit">
			<input type="submit" name="Submit" class="button-primary"
				value="<?php esc_attr_e('Save', 'woocommerce') ?>" />
		</p>
	</form>
<?php
}
