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
			'<span class="wc-order-source wc-order-source--%s" title="%s">%s</span>',
			esc_attr( $source ),
			$label,
			$icon
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
			'tiktok'   => __( 'تيك توك',        'wc-order-source' ),
			'facebook' => __( 'فيسبوك',          'wc-order-source' ),
			'google'   => __( 'جوجل',            'wc-order-source' ),
			'website'  => __( 'مباشر من الموقع', 'wc-order-source' ),
		];

		return $labels[ $source ] ?? __( 'مباشر من الموقع', 'wc-order-source' );
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
			case 'google':
				return self::icon_google();
			default:
				$site_icon = get_site_icon_url( 32 );
				if ( $site_icon ) {
					return sprintf( '<img src="%s" class="wc-order-source__icon" aria-hidden="true" style="width:20px;height:20px;border-radius:4px;" alt="" />', esc_url( $site_icon ) );
				}
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

	private static function icon_google(): string {
		// Google 'G' brand mark — four-colour official SVG paths.
		return '<svg class="wc-order-source__icon" aria-hidden="true" focusable="false"
			xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
			<path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92
				c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
			<path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77
				c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84
				C3.99 20.53 7.7 23 12 23z"/>
			<path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09
				V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z"/>
			<path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15
				C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84
				c.87-2.6 3.3-4.53 6.16-4.53z"/>
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
