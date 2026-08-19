<?php
/**
 * Admin integration: orders column, source filter, order meta-box,
 * order preview hook, and asset enqueuing.
 *
 * @package WC_Order_Source
 */

namespace WC_Order_Source;

defined( 'ABSPATH' ) || exit;

class Admin {

	/** @var self|null */
	private static ?self $instance = null;

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Assets.
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );

		// ── Orders list (classic WC admin table) ──────────────────
		add_filter( 'manage_woocommerce_page_wc-orders_columns',       [ $this, 'add_source_column' ] );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', [ $this, 'render_source_column' ], 10, 2 );
		// Legacy shop_order post type (fallback / non-HPOS).
		add_filter( 'manage_edit-shop_order_columns',       [ $this, 'add_source_column' ] );
		add_action( 'manage_shop_order_posts_custom_column', [ $this, 'render_source_column_legacy' ], 10, 2 );

		// ── Source filter ─────────────────────────────────────────
		// HPOS orders page.
		add_action( 'woocommerce_order_list_table_restrict_manage_orders', [ $this, 'render_source_filter' ] );
		add_filter( 'woocommerce_order_list_table_prepare_items_query_args', [ $this, 'apply_source_filter_hpos' ] );
		// Legacy post-based orders page.
		add_action( 'restrict_manage_posts',  [ $this, 'render_source_filter_legacy' ] );
		add_filter( 'request',                [ $this, 'apply_source_filter_legacy' ] );

		// ── Full order page meta-box ──────────────────────────────
		add_action( 'add_meta_boxes', [ $this, 'add_source_meta_box' ] );
		// HPOS-compatible meta-box registration.
		add_action( 'woocommerce_shop_order_list_table_custom_column', [ $this, 'add_source_meta_box_hpos' ], 10, 2 );

		// ── Order Preview / Quick View ────────────────────────────
		// Inject source into the preview modal HTML (fires inside the modal template).
		add_action( 'woocommerce_admin_order_preview_start', [ $this, 'render_source_in_order_preview' ] );
		// Also inject source data via the AJAX filter (works in all WC versions).
		add_filter( 'woocommerce_admin_order_preview_get_order_details', [ $this, 'inject_source_in_preview_data' ], 10, 2 );
	}

	// ─────────────────────────────────────────────────────────────
	// Assets
	// ─────────────────────────────────────────────────────────────

	public function enqueue_assets( string $hook ): void {
		// Only load on WooCommerce order pages.
		$order_pages = [
			'woocommerce_page_wc-orders', // HPOS.
			'edit.php',                   // Legacy list.
			'post.php',                   // Legacy single order.
			'post-new.php',               // New order.
		];

		$is_wc_orders_page = in_array( $hook, $order_pages, true );

		// Also check for legacy shop_order post type on edit.php / post.php.
		$is_legacy_order = isset( $_GET['post_type'] ) && 'shop_order' === $_GET['post_type']; // phpcs:ignore
		$is_legacy_post  = isset( $_GET['post'] ) && 'shop_order' === get_post_type( absint( $_GET['post'] ) ); // phpcs:ignore

		if ( ! $is_wc_orders_page && ! $is_legacy_order && ! $is_legacy_post ) {
			return;
		}

		wp_enqueue_style(
			'wc-order-source-admin',
			WC_ORDER_SOURCE_URL . 'assets/css/admin.css',
			[],
			WC_ORDER_SOURCE_VERSION
		);
	}

	// ─────────────────────────────────────────────────────────────
	// Orders list column – HPOS
	// ─────────────────────────────────────────────────────────────

	/**
	 * @param  array $columns
	 * @return array
	 */
	public function add_source_column( array $columns ): array {
		$new = [];
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			// Insert after 'order_status'.
			if ( 'order_status' === $key ) {
				$new['order_source'] = esc_html__( 'المصدر', 'wc-order-source' );
			}
		}
		// Fallback: if 'order_status' wasn't found, append.
		if ( ! isset( $new['order_source'] ) ) {
			$new['order_source'] = esc_html__( 'المصدر', 'wc-order-source' );
		}
		return $new;
	}

	/**
	 * HPOS column render.
	 *
	 * @param string    $column
	 * @param \WC_Order $order
	 */
	public function render_source_column( string $column, \WC_Order $order ): void {
		if ( 'order_source' !== $column ) {
			return;
		}
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo Renderer::render( $order );
	}

	/**
	 * Legacy (post-based) column render.
	 *
	 * @param string $column
	 * @param int    $post_id
	 */
	public function render_source_column_legacy( string $column, int $post_id ): void {
		if ( 'order_source' !== $column ) {
			return;
		}
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo Renderer::render( $post_id );
	}

	// ─────────────────────────────────────────────────────────────
	// Source filter – HPOS
	// ─────────────────────────────────────────────────────────────

	/**
	 * Render the <select> filter in the HPOS orders page.
	 *
	 * @param string $order_type
	 */
	public function render_source_filter( string $order_type ): void {
		if ( 'shop_order' !== $order_type ) {
			return;
		}
		$this->output_source_filter_select();
	}

	/**
	 * Apply filter query args for HPOS.
	 *
	 * @param  array $args
	 * @return array
	 */
	public function apply_source_filter_hpos( array $args ): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$source = isset( $_GET['_order_source_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['_order_source_filter'] ) ) : '';

		if ( '' === $source || ! Config::is_valid_source( $source ) ) {
			return $args;
		}

		$args['meta_query'][] = [
			'key'   => Config::META_SOURCE,
			'value' => $source,
		];

		return $args;
	}

	// ─────────────────────────────────────────────────────────────
	// Source filter – Legacy (post-based)
	// ─────────────────────────────────────────────────────────────

	public function render_source_filter_legacy(): void {
		global $typenow;
		if ( 'shop_order' !== $typenow ) {
			return;
		}
		$this->output_source_filter_select();
	}

	/**
	 * @param  array $query_vars
	 * @return array
	 */
	public function apply_source_filter_legacy( array $query_vars ): array {
		global $typenow;

		if ( 'shop_order' !== $typenow ) {
			return $query_vars;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$source = isset( $_GET['_order_source_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['_order_source_filter'] ) ) : '';

		if ( '' === $source || ! Config::is_valid_source( $source ) ) {
			return $query_vars;
		}

		$query_vars['meta_key']   = Config::META_SOURCE;
		$query_vars['meta_value'] = $source;

		return $query_vars;
	}

	// ─────────────────────────────────────────────────────────────
	// Shared filter <select> markup
	// ─────────────────────────────────────────────────────────────

	private function output_source_filter_select(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current = isset( $_GET['_order_source_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['_order_source_filter'] ) ) : '';

		$options = [
			''         => __( 'كل قنوات المبيعات',   'wc-order-source' ),
			'tiktok'   => __( 'تيك توك',         'wc-order-source' ),
			'facebook' => __( 'فيسبوك',       'wc-order-source' ),
			'website'  => __( 'مباشر من الموقع', 'wc-order-source' ),
		];

		echo '<select name="_order_source_filter" id="wcos-source-filter">';
		foreach ( $options as $value => $label ) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $value ),
				selected( $current, $value, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
	}

	// ─────────────────────────────────────────────────────────────
	// Full order page meta-box
	// ─────────────────────────────────────────────────────────────

	public function add_source_meta_box(): void {
		$screens = [ 'shop_order' ]; // Legacy.

		// If HPOS is active, also register for the HPOS order screen.
		if ( class_exists( '\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' ) ) {
			try {
				/** @var \Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController $ctrl */
				$ctrl = wc_get_container()->get( \Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class );
				if ( $ctrl->custom_orders_table_usage_is_enabled() ) {
					$screens[] = wc_get_page_screen_id( 'shop-order' );
				}
			} catch ( \Exception $e ) {
				// Silently ignore – HPOS may not be available.
			}
		}

		foreach ( $screens as $screen ) {
			add_meta_box(
				'wc-order-source-info',
				__( 'مصدر الطلب', 'wc-order-source' ),
				[ $this, 'render_source_meta_box' ],
				$screen,
				'side',
				'high'
			);
		}
	}

	// Placeholder – meta-box is registered via add_meta_boxes, not this hook.
	public function add_source_meta_box_hpos( string $column, \WC_Order $order ): void {}

	/**
	 * Meta-box callback: render source + UTM data.
	 *
	 * @param \WP_Post|\WC_Order $post_or_order
	 */
	public function render_source_meta_box( $post_or_order ): void {
		$order = $post_or_order instanceof \WC_Order
			? $post_or_order
			: wc_get_order( $post_or_order->ID );

		if ( ! $order ) {
			return;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<div class="wcos-meta-box">';
		echo '<div class="wcos-meta-box__source">';
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo Renderer::render( $order );
		echo '</div>';

		// UTM rows.
		$utm_map = Config::utm_meta_map();
		$has_utm = false;

		$utm_rows = '';
		foreach ( $utm_map as $meta_key => $label ) {
			$val = $order->get_meta( $meta_key, true );
			if ( '' !== $val ) {
				$has_utm   = true;
				$utm_rows .= sprintf(
					'<tr><th>%s</th><td>%s</td></tr>',
					esc_html( $label ),
					esc_html( $val )
				);
			}
		}

		if ( $has_utm ) {
			echo '<table class="wcos-utm-table"><tbody>' . $utm_rows . '</tbody></table>'; // phpcs:ignore
		}

		echo '</div>';
	}

	// ─────────────────────────────────────────────────────────────
	// Order Preview
	// ─────────────────────────────────────────────────────────────

	/**
	 * Hook: woocommerce_admin_order_preview_start
	 *
	 * Renders source inside the order preview modal HTML template.
	 * This hook fires when the Backbone template is generated on page load,
	 * NOT during the AJAX request. Therefore, we output Backbone tags
	 * `{{{ data.wcos_source_html }}}` which will be populated from the AJAX data.
	 *
	 * @param int $order_id  Passed by WC (typically 0 or empty for the template).
	 */
	public function render_source_in_order_preview( $order_id = 0 ): void {
		?>
		<# if ( data.wcos_source_html ) { #>
			<div class="wc-order-preview-source" style="display:flex; align-items:center; gap:8px; padding:8px 16px; border-top:1px solid #f0f0f0; font-size:13px; margin-bottom: 8px;">
				<strong style="flex: 0 0 80px; color: #646970; font-weight: 500;"><?php esc_html_e( 'المصدر', 'wc-order-source' ); ?></strong>
				{{{ data.wcos_source_html }}}
			</div>
		<# } #>
		<?php
	}

	/**
	 * Filter: woocommerce_admin_order_preview_get_order_details
	 *
	 * Injects source HTML into the AJAX response data used to populate
	 * the preview modal. This ensures compatibility with all WC versions.
	 *
	 * @param  array     $data   The preview data array.
	 * @param  \WC_Order $order  The order object.
	 * @return array
	 */
	public function inject_source_in_preview_data( array $data, \WC_Order $order ): array {
		$data['wcos_source_html'] = Renderer::render( $order );
		$data['wcos_source_label'] = esc_html__( 'المصدر', 'wc-order-source' );
		return $data;
	}
}
