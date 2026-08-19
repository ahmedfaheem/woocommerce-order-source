<?php
/**
 * Renderer: produces the canonical HTML representation of an order source.
 * Used everywhere (orders list, order page, order preview).
 *
 * @package WC_Order_Source
 */

namespace WC_Order_Source;

defined( 'ABSPATH' ) || exit;

class Renderer {

	// ─────────────────────────────────────────────────────────────
	// Public API
	// ─────────────────────────────────────────────────────────────

	/**
	 * Return the HTML markup for an order's source.
	 *
	 * @param  int|\WC_Order $order_or_id
	 * @return string  Escaped HTML, safe to echo.
	 */
	public static function render( $order_or_id ): string {
		$source = self::get_source( $order_or_id );
		return self::render_source( $source );
	}

	/**
	 * Return the stored source value for an order.
	 *
	 * Falls back to 'website' if:
	 *   - The order does not exist.
	 *   - No _order_source meta is stored (historical orders pre-plugin).
	 *   - The stored value is not in the current whitelist, e.g. the legacy
	 *     'facebook_form' value from plugin v1.0.0. Those orders are displayed
	 *     as Website Direct. They are NOT auto-migrated because the correct
	 *     attribution cannot be determined retroactively.
	 *
	 * @param  int|\WC_Order $order_or_id
	 * @return string
	 */
	public static function get_source( $order_or_id ): string {
		if ( $order_or_id instanceof \WC_Order ) {
			$order = $order_or_id;
		} else {
			$order = wc_get_order( (int) $order_or_id );
		}

		if ( ! $order ) {
			return 'website';
		}

		$source = $order->get_meta( Config::META_SOURCE, true );

		if ( '' === $source || ! Config::is_valid_source( $source ) ) {
			return 'website';
		}

		return $source;
	}

	/**
	 * Build the icon+text HTML for a given source slug.
	 *
	 * @param  string $source  One of Config::SOURCES.
	 * @return string
	 */
	public static function render_source( string $source ): string {
		if ( ! Config::is_valid_source( $source ) ) {
			$source = 'website';
		}

		$icon  = self::get_icon_html( $source );
		$label = esc_html( self::get_label( $source ) );

		return sprintf(
			'<span class="wc-order-source wc-order-source--%s">%s<span class="wc-order-source__label">%s</span></span>',
			esc_attr( $source ),
			$icon,
			$label
		);
	}

	/**
	 * Return the human-readable label for a source.
	 *
	 * @param  string $source
	 * @return string
	 */
	public static function get_label( string $source ): string {
		$labels = [
			'tiktok'   => __( 'TikTok',        'wc-order-source' ),
			'facebook' => __( 'Facebook',       'wc-order-source' ),
			'website'  => __( 'Website Direct', 'wc-order-source' ),
		];

		return $labels[ $source ] ?? __( 'Website Direct', 'wc-order-source' );
	}

	/**
	 * Return the icon HTML (<svg aria-hidden="true">…</svg>) for a source.
	 *
	 * @param  string $source
	 * @return string  Raw HTML (SVG), safe to echo – already escaped internally.
	 */
	public static function get_icon_html( string $source ): string {
		switch ( $source ) {
			case 'tiktok':
				return self::icon_tiktok();
			case 'facebook':
				return self::icon_facebook();
			default:
				return self::icon_globe();
		}
	}

	// ─────────────────────────────────────────────────────────────
	// SVG icons  (inline, lightweight, ~16-18px, aria-hidden)
	// ─────────────────────────────────────────────────────────────

	private static function icon_tiktok(): string {
		// Simplified TikTok music-note "T" logotype path, recognisable brand shape.
		return '<svg class="wc-order-source__icon" aria-hidden="true" focusable="false"
			xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
			<path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-2.88 2.5
				2.89 2.89 0 0 1-2.89-2.89 2.89 2.89 0 0 1 2.89-2.89c.28 0 .54.04.79.1V9.01
				a6.33 6.33 0 0 0-.79-.05 6.34 6.34 0 0 0-6.34 6.34 6.34 6.34 0 0 0 6.34 6.34
				6.34 6.34 0 0 0 6.33-6.34V8.93a8.17 8.17 0 0 0 4.78 1.52V7.01
				a4.85 4.85 0 0 1-1.01-.32z"/>
		</svg>';
	}

	private static function icon_facebook(): string {
		// Classic Facebook 'f' lettermark.
		return '<svg class="wc-order-source__icon" aria-hidden="true" focusable="false"
			xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
			<path d="M22 12c0-5.52-4.48-10-10-10S2 6.48 2 12c0 4.99 3.66 9.12 8.44 9.88
				v-6.99H7.9V12h2.54V9.8c0-2.51 1.49-3.89 3.78-3.89 1.09 0 2.24.2 2.24.2v2.46h-1.26
				c-1.24 0-1.63.77-1.63 1.56V12h2.78l-.44 2.89h-2.34v6.99C18.34 21.12 22 16.99 22 12z"/>
		</svg>';
	}

	private static function icon_globe(): string {
		// Simple globe / web icon using standard Heroicons outline path.
		return '<svg class="wc-order-source__icon" aria-hidden="true" focusable="false"
			xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
			stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
			<circle cx="12" cy="12" r="10"/>
			<line x1="2" y1="12" x2="22" y2="12"/>
			<path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10
				      15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
		</svg>';
	}
}
