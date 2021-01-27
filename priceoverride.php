<?php
/**
 * Plugin Name:       Customer/Product Price Override for WooCommerce
 * Plugin URI:        https://janih.eu/
 * Description:       Override a product price for customer
 * Version:           0.01-alpha
 * Author:            Jani Huumonen
 * Author URI:        https://janih.eu/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

/* Copyright 2020 Jani Huumonen */

#namespace JHWCPO;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

$priceoverride_db_version = '1.0';
//TODO: use define()? didn't work with variable(uninstall is run in function context)
$priceoverride_table_name = "priceoverride_data"; //TODO: hardcoded in uninstall()

// Run install when plugin is activated
register_activation_hook( __FILE__, 'priceoverride_install' );

// Run uninstall when plugin is removed
register_uninstall_hook( __FILE__, 'priceoverride_uninstall' );

// Upgrade database if version has changed
add_action( 'plugins_loaded', 'priceoverride_update_db_check' );

add_action('admin_menu', 'priceoverride_add_users_menu');

add_action( 'wp_ajax_JHWooPricing_getprices', 'getprices_ajax_handler' );
add_action( 'wp_ajax_JHWooPricing_setprices', 'setprices_ajax_handler' );

add_action( 'init', 'priceoverride_add_filters');

function priceoverride_add_filters()
{
	if ( in_array( role_name, wp_get_current_user()->roles ) ) {
		// Simple, grouped and external products
		add_filter('woocommerce_product_get_regular_price', 'priceoverride_custom_price', 95, 2);
		add_filter('woocommerce_product_get_sale_price', 'priceoverride_custom_price', 95, 2);
		add_filter('woocommerce_product_get_price', 'priceoverride_custom_price', 95, 2);
		// Variations
		add_filter('woocommerce_product_variation_get_regular_price', 'priceoverride_custom_price', 95, 2);
		add_filter('woocommerce_product_variation_get_sale_price', 'priceoverride_custom_price', 95, 2);
		add_filter('woocommerce_product_variation_get_price', 'priceoverride_custom_price', 95, 2);
		// Variable (price range)
		add_filter('woocommerce_variation_prices_regular_price', 'priceoverride_custom_price', 95, 2);
		add_filter('woocommerce_variation_prices_sale_price', 'priceoverride_custom_price', 95, 2);
		add_filter('woocommerce_variation_prices_price', 'priceoverride_custom_price', 95, 2);
		// Handle price caching
//TODO
//		add_filter( 'woocommerce_get_variation_prices_hash', 'JHWCPM\add_price_multiplier_to_variation_prices_hash', 90, 1 );
	}
}
$overrideprices;
add_action( 'wp', function() {
		global $overrideprices;
		$prices = priceoverride_select(get_current_user_id());
//		error_log(print_r($prices,true));
//		error_log(get_current_user_id());
		$b = [];
		foreach ( $prices as $a )
			$b[$a['product_id']] = $a['price'];
		$overrideprices = $b;
});
function priceoverride_custom_price( $price, $product ) {
	global $overrideprices;
	$op = 0;
	if (!empty($overrideprices) && array_key_exists($product->get_id(),$overrideprices)) {
		$op = $overrideprices[$product->get_id()];
		//error_log(print_r($op,true));
	}
	error_log(
		print_r($product->get_name(),true)
		.' '.print_r($product->get_id(),true)
		.' '.print_r($op,true));
	return $op ? $op : $price;
}

function priceoverride_add_users_menu()
{
	add_users_page(
		'Asiakaskohtaiset Sopimushinnat',
		'Sopimushinnat',
		'edit_users',
		'sopimushinnat',
		'priceoverride_settings_page');
}

function priceoverride_install() {
	global $wpdb;
	global $priceoverride_db_version;
	global $priceoverride_table_name;
	$installed_ver = get_option( 'priceoverride_db_version' );
	
	if ( $installed_ver != $priceoverride_db_version ) {
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$wpdb->prefix}$priceoverride_table_name (
			`customer_id` bigint(20) unsigned NOT NULL,
			`product_id` bigint(20) unsigned NOT NULL,
			`price` DECIMAL(10,2) UNSIGNED,
			PRIMARY KEY  (customer_id,product_id)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
		update_option( "priceoverride_db_version", $priceoverride_db_version );
	}
}

function priceoverride_uninstall() {
	global $wpdb;
//	global $priceoverride_table_name;
	return delete_option( "priceoverride_db_version" ) &&
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}priceoverride_data" );
}

# TODO: separate db install/upgrade?
function priceoverride_update_db_check() {
	global $priceoverride_db_version;
	if ( get_option( 'priceoverride_db_version' ) != $priceoverride_db_version ) {
		priceoverride_install();
	}
}

function priceoverride_insert($values) {
	global $wpdb;
	global $priceoverride_table_name;
	return insertOrUpdateOne(
		$wpdb->prefix . $priceoverride_table_name,
		[ 'customer_id',	'product_id',	'price'	],
		[ '%d',				'%d',			'%f'	],
		$values,
		2
	);
}

function priceoverride_delete($nulls) {
	global $wpdb;
	global $priceoverride_table_name;
	return deleteMany(
		$wpdb->prefix . $priceoverride_table_name,
		[ 'customer_id',	'product_id'	],
		[ '%d',				'%d'			],
		$nulls
	);
}

function priceoverride_select($customer) {
	global $wpdb;
	global $priceoverride_table_name;
$q = <<<EOQ
SELECT * FROM {$wpdb->prefix}$priceoverride_table_name 
WHERE `customer_id` = %d
EOQ;
	$sql = $wpdb->prepare($q, $customer);
	$result = $wpdb->get_results($sql, ARRAY_A);

	if ( is_array($result) && empty($wpdb->last_error) ) {
		return $result;
	} else {
		$wpdb->print_error();
		return [];
	}
}

const role_name = 'customer';
function relevant_users()
{
	return get_users('orderby=nicename&role=' . role_name);
}

// DANGER, Will Robinson! ajax handlers are run in admin context!
function getprices_ajax_handler() {
	check_ajax_referer( 'getprices' );
	$res = priceoverride_select( $_POST['cid'] );
	//error_log( print_r( $res, true ) );
	wp_send_json( $res );
}

// DANGER, Will Robinson! ajax handlers are run in admin context!
function setprices_ajax_handler() {
	check_ajax_referer( 'setprices' );
	foreach ($_POST as $c => $a)
		if (is_numeric($c) && is_int($c+0) && is_array($a))
			foreach ($a as $p => $v) {
				if (empty($v)) $nulls[] = [$c, $p];
				else $values[] = [ $c, $p, $v ];
			}
	error_log(print_r($_POST,true));
	if (!empty($values))
		$ires = priceoverride_insert($values);
	if (!empty($nulls))
		$dres = priceoverride_delete($nulls);
	if ($ires) clearProductsTransients($values);
	if ($dres) clearProductsTransients($nulls);
	wp_send_json( $ires.','.$dres );
}
function clearProductsTransients($rows) {
		foreach ($rows as $a)
			error_log( wc_delete_product_transients($a[1]) );
}

// (string, [string], ['%s'|'%d'|'%f'], [[]], uint)
function insertOrUpdateOne($table, $keys, $placeholders, $values, $update_index) {
    global $wpdb;
	$ph		= implode(',', $placeholders);
	$pstr	= implode(',', array_fill(0, count($values), "($ph)")); // repeat string w/ separator
	$kstr	= implode('`,`', $keys);
	$key	= $keys[$update_index];
	$q = "INSERT INTO $table (`$kstr`) VALUES $pstr ON DUPLICATE KEY UPDATE $key=VALUES($key)";
error_log($q);
	$sql = $wpdb->prepare($q, array_merge(...$values)); // flatten 2d array
error_log($sql);
//	return $sql; 
	return $wpdb->query($sql);
}

// (string, [string], ['%s'|'%d'|'%f'], [[]])
function deleteMany($table, $keys, $placeholders, $values) {
    global $wpdb;
	$ph		= implode(',', $placeholders);
	$pstr	= implode(',', array_fill(0, count($values), "($ph)")); // repeat string w/ separator
	$kstr	= implode('`,`', $keys);
	$q = "DELETE FROM $table WHERE (`$kstr`) IN ($pstr)";
error_log($q);
	$sql = $wpdb->prepare($q, array_merge(...$values)); // flatten 2d array
error_log($sql);
//	return $sql; 
	return $wpdb->query($sql);
}

function priceoverride_settings_page()
{
	if ( !current_user_can( 'edit_users' ) )  {
		wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
	}

/*
//load
	foreach ( priceoverride_select($customer_id) as $result ) {
		$product_id = $result['product_id'];
		$price = $result['price'];
		//$customer_id = $result['customer_id'];
		$product = wc_get_product( $product_id );
		$pname = esc_html( $product->get_name() );
	}
*/
?>
<h2>Sopimushinnat</h2>
<form id="JHWooPricing" action="" method="POST">

	<p class="updated" style="display:none">
		<strong><?php echo __('Settings saved.', 'woocommerce') ?></strong>
	</p>
	<label>Asiakas: </label>
<select name="aa">
		<option value="0" selected="selected">Select...</option>
<?php
		foreach ( relevant_users() as $user ) {
			echo '<option value="'.$user->ID.'">'.$user->display_name.'</option>';
		}
?>
	</select>
	<button type="button">add</button>
	<table border=1 cellpadding=5>
		<thead>
			<tr><th colspan=2>Tuote</th><th rowspan=2>Hinta</th><th rowspan=2>Poista</th></tr>
			<tr><th>Nimi</th><th>ID</th></tr>
		</thead>
		<tbody></tbody>
		<tfoot></tfoot>
	</table>
	<input type="submit" class="button-primary" value="<?php esc_attr_e('Save', 'woocommerce') ?>" />
	<template><tr>
		<td>nimi</td>
		<td></td>
		<td><input type="text"></td>
		<td><button type="button">X</button></td>
	</tr></template>
	<template><tr>
		<td>nimi</td>
		<td><input type="text"></td>
		<td><input type="text"></td>
		<td><button type="button">X</button></td>
	</tr></template>
</form>

<script>
'use strict';
jQuery(function($) {		// runs on DOMContentLoaded
	const form	= document.getElementById('JHWooPricing');
	const table	= form.querySelector('table');
	const tbody	= table.tBodies[0];
	const sel	= form.querySelector('select');
	const abtn	= form.querySelector('button[type="button"]');
	const info	= form.querySelector('p.updated');
	const tmps	= form.getElementsByTagName('template');
	let prices = [];
	let db = {
		prune: function (prices) {
console.log(prices);
console.log(this);
			return prices.map( (rows,cid) => {
				const	d = [],
						c = rows.reduce((o,v,i)=> (i && v && (o[i]=v), o), {});
				for (const [k,v] of this.values[cid])
					if (!c[k]) d.push([k,'']);
					else if (c[k] == v) delete c[k];
				return d.concat(Object.entries(c));
			} );
		},
		values: []
	};

	function ajax_get(cb,id) {
		$.post( ajaxurl, {
				_ajax_nonce: '<?php echo wp_create_nonce( 'getprices' ); ?>',
				action: 'JHWooPricing_getprices',
				cid: id
			}, cb);
	}
	function ajax_set(cb, formdata) {
		Object.entries({
			_ajax_nonce: '<?php echo wp_create_nonce( 'setprices' ); ?>',
			action: 'JHWooPricing_setprices'
		}).forEach(([k,v]) => formdata.set(k,v));
		$.ajax({
			url: ajaxurl,
			method: 'POST',
			processData: false,
			contentType: false,
			cache: false,
			data: formdata
		}).done( cb );
	}

	$(sel).change(function() {
		let cid = this.value;
		let prev = $(this).data('prev');
		validate_cid(prev) &&
			prep( prev );
		$(this).data('prev', cid);

		info.style.display = 'none';
		tbody.innerHTML = '';

		validate_cid(cid)
		&& prices[cid]
		? prices[cid].forEach( (v,i) =>(
			add_product(i,v,tmps[0]) ))
		: ajax_get(
			function(data) {
				console.log(data);
				db.values[cid] = data.map( v => [v.product_id, v.price] );
				data.forEach( v =>
					add_product(v.product_id, v.price, tmps[0]) );
			}, cid);
	});

	form.onsubmit = function(e){
		e.preventDefault();
		prep( sel.selectedOptions[0].value );
		let f = new FormData(this);
		db.prune(prices)
		.forEach( (p,cid) => 
			p.forEach( ([pid,v]) =>
//			p.forEach( (v,pid) =>
				f.set(cid+'['+pid+']', v)
			) );
		ajax_set(function(data) {
/*e*/		console.log(data);
			info.style.display = '';
			setTimeout(_=>info.style.display = 'none', 5000);
		}, f);
		return false;
	};
		
	abtn.addEventListener('click', _=>
			add_product('', '', tmps[1], true)
	);
	
	const validate_cid = cid =>
		!isNaN(parseInt(cid)) && parseInt(cid);

	const prep = cid => {
		prices[cid] = [];
		tbody.querySelectorAll('tr').forEach(v=>{
			let t = v.querySelectorAll('td')
			let id = t[1].firstElementChild;
			let pr = t[2].firstElementChild;
			let pid = id ? id.value : t[1].textContent;
			prices[cid][pid] = pr.value;
		});
	};

	function add_product(pid, price, tmpl, editable){
		let row = tmpl.content.cloneNode(true);
		let tds = row.querySelectorAll('td');
		if (editable) tds[1].firstElementChild.value = pid;
		else tds[1].textContent = pid;
		tds[2].firstElementChild.value = price;
		tds[3].firstElementChild.addEventListener('click', e=> {
			let tr = e.target.parentElement.parentElement;
			table.deleteRow(tr.rowIndex);
		});
		tbody.appendChild(row);
	}

//			pr.name = cid+'['+pid+']';

	// TODO
	function ProductNames(arr) {
		// searchable list of product names
	}
	// TODO
	function namefield() {
		// is a textfield.
		// onfocus opens a 'tooltip' list of products.
		// on text entry filters visible products choices.
		// fuzzy search filter?
	}

	function val(tr, a) {
		let pid = a ? a[0] : false;
		let t = tr.querySelectorAll('td');
		let id = t[1].firstElementChild;
		let pr = t[2].firstElementChild;
		pid
		? (id ? (id.value = pid) : (t[1].textContent = pid), pr.value = a[1])
		: (pid = id ? id.value : t[1].textContent)
		return [pid, pr.value];
	}
	function set_values(tr, [pid, val]) {
		let t = tr.querySelectorAll('td')
		let id = t[1].firstElementChild;
		t[2].firstElementChild.value = val;
		id ? id.value = pid : t[1].textContent = pid;
	}
	function get_values(tr) {
		let t = tr.querySelectorAll('td')
		let id = t[1].firstElementChild;
		let pr = t[2].firstElementChild;
		let pid = id ? id.value : t[1].textContent;
		return [pid, pr.value];
	}
});

</script>

<?php
}
