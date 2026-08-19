<?php
/**
 * Plugin Name:       WooCommerce Order Source Tracker
 * Plugin URI:        tel:201099492053
 * Description:       Tracks and displays the advertising/source channel (TikTok Ads, Facebook Ads, Website Direct) for every WooCommerce order. Fully automatic UTM-based attribution. HPOS-compatible.
 * Version:           1.1.0
 * Author:            Ahmed Faheem
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wc-order-source
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * WC requires at least: 7.0
 * WC tested up to:   9.9
 *
 * @package WC_Order_Source
 */

defined( 'ABSPATH' ) || exit;

// Plugin constants.
define( 'WC_ORDER_SOURCE_VERSION', '1.1.0' );
define( 'WC_ORDER_SOURCE_FILE',    __FILE__ );
define( 'WC_ORDER_SOURCE_DIR',     plugin_dir_path( __FILE__ ) );
define( 'WC_ORDER_SOURCE_URL',     plugin_dir_url( __FILE__ ) );

// Declare HPOS compatibility BEFORE WooCommerce loads.
add_action( 'before_woocommerce_init', function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
			'custom_order_tables',
			__FILE__,
			true
		);
	}
} );

// Bootstrap.
add_action( 'plugins_loaded', 'wc_order_source_init', 20 );

function wc_order_source_init() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'wc_order_source_missing_wc_notice' );
		return;
	}

	require_once WC_ORDER_SOURCE_DIR . 'includes/class-order-source-config.php';
	require_once WC_ORDER_SOURCE_DIR . 'includes/class-order-source-tracker.php';
	require_once WC_ORDER_SOURCE_DIR . 'includes/class-order-source-renderer.php';
	require_once WC_ORDER_SOURCE_DIR . 'includes/class-order-source-admin.php';
	require_once WC_ORDER_SOURCE_DIR . 'includes/functions.php';

	WC_Order_Source\Tracker::get_instance();
	WC_Order_Source\Admin::get_instance();
}

function wc_order_source_missing_wc_notice() {
	echo '<div class="notice notice-error"><p>'
		. esc_html__( 'WooCommerce Order Source Tracker requires WooCommerce to be installed and active.', 'wc-order-source' )
		. '</p></div>';
}
