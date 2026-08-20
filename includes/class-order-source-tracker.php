<?php
/**
 * Front-end tracker: captures UTM parameters from URL, persists them
 * in a cookie (30-day window), and writes attribution to new orders.
 *
 * Attribution model: LAST-TOUCH
 * ─────────────────────────────
 * Every time a visitor arrives via a recognised UTM source, the cookie is
 * overwritten with the new attribution data. This means if a customer clicks
 * a TikTok ad on Day 1 and a Facebook ad on Day 2 before ordering, the order
 * will be attributed to Facebook (the most recent ad click).
 *
 * This is the most appropriate model for paid advertising attribution, since
 * the last ad is the one that converted the customer.
 *
 * @package WC_Order_Source
 */

namespace WC_Order_Source;

defined( 'ABSPATH' ) || exit;

class Tracker {

	/** @var self|null */
	private static ?self $instance = null;

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Capture UTM on every front-end request (init is early enough).
		add_action( 'init', [ $this, 'capture_utm' ], 1 );

		// Write source to order on creation – works with both classic and block checkout.
		add_action( 'woocommerce_checkout_order_created', [ $this, 'assign_source_to_order' ], 10, 1 );
		// Fallback for REST / programmatic order creation paths.
		add_action( 'woocommerce_new_order', [ $this, 'assign_source_to_new_order' ], 10, 2 );
	}

	// ─────────────────────────────────────────────────────────────
	// UTM capture
	// ─────────────────────────────────────────────────────────────

	/**
	 * Read UTM params from the current URL and persist them in a cookie.
	 *
	 * Only acts when utm_source is present in the URL query string AND maps
	 * to a recognised paid source (tiktok, facebook).
	 *
	 * LAST-TOUCH: overwrites any existing cookie so the most recent ad click
	 * always wins.
	 */
	public function capture_utm(): void {
		if ( is_admin() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$raw_utm_source = isset( $_GET['utm_source'] ) ? sanitize_text_field( wp_unslash( $_GET['utm_source'] ) ) : '';



		// Fallback: If no UTM is provided, check click IDs and HTTP Referer.
		if ( '' === $raw_utm_source ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( isset( $_GET['fbclid'] ) ) {
				$raw_utm_source = 'facebook';
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			} elseif ( isset( $_GET['ttclid'] ) ) {
				$raw_utm_source = 'tiktok';
			} elseif ( isset( $_SERVER['HTTP_REFERER'] ) ) {
				$referer = sanitize_text_field( wp_unslash( $_SERVER['HTTP_REFERER'] ) );
				if ( strpos( $referer, 'facebook.com' ) !== false || strpos( $referer, 'instagram.com' ) !== false ) {
					$raw_utm_source = 'facebook';
				} elseif ( strpos( $referer, 'tiktok.com' ) !== false ) {
					$raw_utm_source = 'tiktok';
				}
			}
		}

		if ( '' === $raw_utm_source ) {
			return; // Nothing to capture this request.
		}

		$utm = $this->sanitize_utm( [
			'utm_source'   => $raw_utm_source,
			'utm_medium'   => isset( $_GET['utm_medium'] )   ? wp_unslash( $_GET['utm_medium'] )   : '', // phpcs:ignore
			'utm_campaign' => isset( $_GET['utm_campaign'] ) ? wp_unslash( $_GET['utm_campaign'] ) : '', // phpcs:ignore
			'utm_content'  => isset( $_GET['utm_content'] )  ? wp_unslash( $_GET['utm_content'] )  : '', // phpcs:ignore
			'utm_term'     => isset( $_GET['utm_term'] )     ? wp_unslash( $_GET['utm_term'] )     : '', // phpcs:ignore
		] );

		// Determine source from UTM.
		$source = $this->detect_source_from_utm( $utm );

		// Only persist recognised paid sources.
		// 'website' is the fallback for unrecognised traffic and never needs a cookie.
		if ( 'website' === $source ) {
			return;
		}

		$expiry = time() + Config::attribution_window();

		// Encode as JSON — all UTM params in a single cookie.
		$payload = wp_json_encode( [
			'source'   => $source,
			'utm'      => $utm,
			'captured' => time(),
		] );

		// LAST-TOUCH: always overwrite the existing cookie.
		setcookie(
			Config::COOKIE_SOURCE,
			$payload,
			[
				'expires'  => $expiry,
				'path'     => '/',
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			]
		);

		// Also update $_COOKIE so same-request reads reflect the new value.
		$_COOKIE[ Config::COOKIE_SOURCE ] = $payload;
	}

	// ─────────────────────────────────────────────────────────────
	// Order hooks
	// ─────────────────────────────────────────────────────────────

	/**
	 * Hook: woocommerce_checkout_order_created  (WC 7.2+ / block checkout)
	 *
	 * @param \WC_Order $order
	 */
	public function assign_source_to_order( \WC_Order $order ): void {
		$this->maybe_write_attribution( $order );
	}

	/**
	 * Hook: woocommerce_new_order – fallback for programmatic / REST creation.
	 *
	 * @param int       $order_id
	 * @param \WC_Order $order
	 */
	public function assign_source_to_new_order( int $order_id, \WC_Order $order ): void {
		$this->maybe_write_attribution( $order );
	}

	// ─────────────────────────────────────────────────────────────
	// Core attribution logic
	// ─────────────────────────────────────────────────────────────

	/**
	 * Writes source and UTM meta to an order if no source is already stored.
	 *
	 * Never overwrites an existing valid source — prevents double-writing if
	 * both woocommerce_checkout_order_created and woocommerce_new_order fire
	 * for the same order.
	 *
	 * @param \WC_Order $order
	 */
	public function maybe_write_attribution( \WC_Order $order ): void {
		// If a valid source is already on the order, respect it.
		$existing = $order->get_meta( Config::META_SOURCE, true );
		if ( '' !== $existing && Config::is_valid_source( $existing ) ) {
			return;
		}

		$attribution = $this->read_attribution_cookie();

		if ( null !== $attribution ) {
			$source = $attribution['source'];
			$utm    = $attribution['utm'] ?? [];
		} else {
			$source = 'website';
			$utm    = [];
		}

		// Write source.
		$order->update_meta_data( Config::META_SOURCE, $source );

		// Write UTM fields (only those that have a value).
		$utm_map = [
			'utm_source'   => Config::META_UTM_SOURCE,
			'utm_medium'   => Config::META_UTM_MEDIUM,
			'utm_campaign' => Config::META_UTM_CAMPAIGN,
			'utm_content'  => Config::META_UTM_CONTENT,
			'utm_term'     => Config::META_UTM_TERM,
		];

		foreach ( $utm_map as $key => $meta_key ) {
			if ( ! empty( $utm[ $key ] ) ) {
				$order->update_meta_data( $meta_key, $utm[ $key ] );
			}
		}

		$order->save();
	}

	// ─────────────────────────────────────────────────────────────
	// Helpers
	// ─────────────────────────────────────────────────────────────

	/**
	 * Read and validate the attribution cookie.
	 *
	 * @return array|null  Associative array with 'source' and 'utm', or null.
	 */
	private function read_attribution_cookie(): ?array {
		if ( empty( $_COOKIE[ Config::COOKIE_SOURCE ] ) ) {
			return null;
		}

		$raw  = sanitize_text_field( wp_unslash( $_COOKIE[ Config::COOKIE_SOURCE ] ) );
		$data = json_decode( $raw, true );

		if ( ! is_array( $data ) || empty( $data['source'] ) ) {
			return null;
		}

		if ( ! Config::is_valid_source( $data['source'] ) ) {
			return null;
		}

		// Check attribution window has not expired.
		$captured = isset( $data['captured'] ) ? (int) $data['captured'] : 0;
		if ( $captured > 0 && ( time() - $captured ) > Config::attribution_window() ) {
			return null;
		}

		$utm = isset( $data['utm'] ) && is_array( $data['utm'] ) ? $this->sanitize_utm( $data['utm'] ) : [];

		return [
			'source' => $data['source'],
			'utm'    => $utm,
		];
	}

	/**
	 * Detect the recognised source from UTM parameters.
	 *
	 * Supported mappings:
	 *   utm_source=tiktok   → 'tiktok'
	 *   utm_source=facebook → 'facebook'
	 *   anything else       → 'website' (fallback — no cookie is written)
	 *
	 * To add a new paid source in future, add a case here and add the slug
	 * to Config::SOURCES. Nothing else needs to change.
	 *
	 * @param  array $utm  Sanitized UTM parameter array.
	 * @return string
	 */
	private function detect_source_from_utm( array $utm ): string {
		$src = strtolower( $utm['utm_source'] ?? '' );

		switch ( $src ) {
			case 'tiktok':
			case 'tt':
				return 'tiktok';
			case 'facebook':
			case 'fb':
			case 'ig':
			case 'instagram':
			case 'an': // Meta Audience Network
				return 'facebook';
			default:
				return 'website';
		}
	}

	/**
	 * Sanitize raw UTM values: text-sanitise, max-length, strip HTML.
	 *
	 * @param  array $raw
	 * @return array
	 */
	private function sanitize_utm( array $raw ): array {
		$keys  = [ 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term' ];
		$clean = [];

		foreach ( $keys as $key ) {
			if ( ! empty( $raw[ $key ] ) ) {
				$val           = sanitize_text_field( wp_unslash( (string) $raw[ $key ] ) );
				$val           = wp_strip_all_tags( $val );
				$val           = substr( $val, 0, Config::UTM_MAX_LENGTH );
				$clean[ $key ] = $val;
			} else {
				$clean[ $key ] = '';
			}
		}

		return $clean;
	}
}
