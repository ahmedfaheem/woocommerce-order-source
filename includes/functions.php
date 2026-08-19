<?php
/**
 * Public helper functions – read-only external API for the plugin.
 *
 * The plugin is fully automatic: source attribution is detected from UTM
 * parameters without any manual integration code. These helpers are provided
 * for reading order source data in themes, other plugins, or custom code.
 *
 * @package WC_Order_Source
 */

defined( 'ABSPATH' ) || exit;

// ─────────────────────────────────────────────────────────────────────────────
// Core API
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Set the source for a WooCommerce order.
 *
 * Under normal operation you do NOT need to call this function. The plugin
 * automatically assigns the source when an order is created, based on the
 * visitor's UTM attribution cookie.
 *
 * This function is retained for advanced edge cases (e.g. correcting a
 * mis-attributed order, scripted data imports, WP-CLI migration tasks).
 *
 * Allowed source values:
 *   tiktok    — visitor came via a TikTok Ad (utm_source=tiktok)
 *   facebook  — visitor came via a Facebook Ad (utm_source=facebook)
 *   website   — direct / organic / unknown traffic
 *
 * @param  int    $order_id  WooCommerce order ID.
 * @param  string $source    One of: tiktok | facebook | website.
 * @param  bool   $force     Pass true to overwrite an existing source.
 * @return bool              True on success, false on failure.
 */
function my_wc_set_order_source( int $order_id, string $source, bool $force = false ): bool {
	if ( ! WC_Order_Source\Config::is_valid_source( $source ) ) {
		_doing_it_wrong(
			__FUNCTION__,
			sprintf(
				/* translators: %s = the invalid source value */
				esc_html__( 'Invalid order source value: "%s". Allowed: tiktok, facebook, website.', 'wc-order-source' ),
				esc_html( $source )
			),
			'1.0.0'
		);
		return false;
	}

	$order = wc_get_order( $order_id );

	if ( ! $order ) {
		return false;
	}

	// Permission check: only privileged users / cron / CLI may call this.
	if ( ! current_user_can( 'edit_shop_orders' ) && ! wp_doing_cron() && ! defined( 'WP_CLI' ) ) {
		return false;
	}

	// Overwrite protection: do not silently overwrite an existing valid source.
	if ( ! $force ) {
		$existing = $order->get_meta( WC_Order_Source\Config::META_SOURCE, true );
		if ( '' !== $existing && WC_Order_Source\Config::is_valid_source( $existing ) ) {
			return true; // Already set; nothing changed but the call is not an error.
		}
	}

	$order->update_meta_data( WC_Order_Source\Config::META_SOURCE, $source );
	$order->save();

	return true;
}

/**
 * Get the source for a WooCommerce order.
 *
 * Returns 'website' if no source is stored or the stored value is not
 * in the current whitelist (e.g. legacy 'facebook_form' from v1.0.0).
 *
 * @param  int $order_id
 * @return string  One of: tiktok | facebook | website.
 */
function my_wc_get_order_source( int $order_id ): string {
	return WC_Order_Source\Renderer::get_source( $order_id );
}

/**
 * Render the canonical source UI (icon + label) for an order.
 *
 * Returns an HTML string safe to echo directly.
 *
 * @param  int|\WC_Order $order_or_id
 * @return string
 */
function my_wc_render_order_source( $order_or_id ): string {
	return WC_Order_Source\Renderer::render( $order_or_id );
}

// ─────────────────────────────────────────────────────────────────────────────
// Optional helpers
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Get the human-readable label for a source slug.
 *
 * @param  string $source
 * @return string
 */
function my_wc_get_order_source_label( string $source ): string {
	return WC_Order_Source\Renderer::get_label( $source );
}

/**
 * Get the SVG icon HTML for a source slug.
 *
 * @param  string $source
 * @return string  Raw HTML, safe to echo.
 */
function my_wc_get_order_source_icon( string $source ): string {
	return WC_Order_Source\Renderer::get_icon_html( $source );
}
