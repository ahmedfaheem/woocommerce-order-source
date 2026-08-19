<?php
/**
 * Configuration: source whitelist, meta keys, cookie/session settings.
 *
 * @package WC_Order_Source
 */

namespace WC_Order_Source;

defined( 'ABSPATH' ) || exit;

class Config {

	// ── Source whitelist ──────────────────────────────────────────
	// Allowed values for _order_source.
	// tiktok   : visitor arrived via a TikTok Ad (utm_source=tiktok)
	// facebook : visitor arrived via a Facebook Ad (utm_source=facebook)
	// website  : direct / organic / unknown traffic — the default fallback
	//
	// Note: 'facebook_form' was used in v1.0.0 and may exist on historical
	// orders. It is intentionally absent from this whitelist so it cannot
	// be written to new orders. Old orders that carry 'facebook_form' will
	// display as "Website Direct" (the renderer's safe fallback). Do NOT
	// automatically migrate those records, as the correct attribution is
	// unknown.
	const SOURCES = [ 'tiktok', 'facebook', 'website' ];

	// ── Order meta keys ───────────────────────────────────────────
	const META_SOURCE       = '_order_source';
	const META_UTM_SOURCE   = '_order_utm_source';
	const META_UTM_MEDIUM   = '_order_utm_medium';
	const META_UTM_CAMPAIGN = '_order_utm_campaign';
	const META_UTM_CONTENT  = '_order_utm_content';
	const META_UTM_TERM     = '_order_utm_term';

	// ── Cookie / session keys ─────────────────────────────────────
	const COOKIE_SOURCE = 'wcos_source';
	const COOKIE_UTM    = 'wcos_utm';
	const SESSION_KEY   = 'wcos_attribution';

	// ── Attribution window (days) ─────────────────────────────────
	const ATTRIBUTION_DAYS = 30;

	// ── UTM max lengths (characters) ─────────────────────────────
	const UTM_MAX_LENGTH = 255;

	/**
	 * Returns the attribution window in seconds.
	 *
	 * @return int
	 */
	public static function attribution_window(): int {
		$days = (int) apply_filters( 'wc_order_source_attribution_days', self::ATTRIBUTION_DAYS );
		return max( 1, $days ) * DAY_IN_SECONDS;
	}

	/**
	 * Validate a source value against the whitelist.
	 *
	 * @param  string $source
	 * @return bool
	 */
	public static function is_valid_source( string $source ): bool {
		return in_array( $source, self::SOURCES, true );
	}

	/**
	 * Return all UTM field meta-key => label pairs.
	 *
	 * @return array<string,string>
	 */
	public static function utm_meta_map(): array {
		return [
			self::META_UTM_SOURCE   => __( 'UTM Source',   'wc-order-source' ),
			self::META_UTM_MEDIUM   => __( 'UTM Medium',   'wc-order-source' ),
			self::META_UTM_CAMPAIGN => __( 'UTM Campaign', 'wc-order-source' ),
			self::META_UTM_CONTENT  => __( 'UTM Content',  'wc-order-source' ),
			self::META_UTM_TERM     => __( 'UTM Term',     'wc-order-source' ),
		];
	}
}
