<?php
/**
 * Core setup for the GatherPress Tickets block variation.
 *
 * Handles post meta registration and the admin list-table column
 * for the ticket URL on gatherpress_event posts.
 *
 * @package GatherPress_Tickets
 * @since   0.1.0
 */

declare(strict_types=1);

namespace GatherPress_Tickets;

use GatherPress\Core;
use WP_Screen;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Bootstraps meta registration and admin columns.
 *
 * @since 0.1.0
 */
class Setup {

	use Core\Traits\Singleton;

	/**
	 * The GatherPress event post type slug.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	public const POST_TYPE = 'gatherpress_event';

	/**
	 * The post meta key for the ticket URL.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	public const META_KEY = 'gatherpress_tickets_url';

	/**
	 * Constructor — registers hooks.
	 *
	 * @since 0.1.0
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Registers all hooks.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_action( 'init', array( $this, 'register_meta' ) );
		add_action( 'admin_init', array( $this, 'register_admin_columns' ) );
	}

	/**
	 * Registers the gatherpress_tickets_url post meta field.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function register_meta(): void {
		register_post_meta(
			self::POST_TYPE,
			self::META_KEY,
			array(
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
				'sanitize_callback' => 'esc_url_raw',
				'auth_callback'     => static function ( bool $allowed, string $meta_key, int $post_id ): bool {
					return current_user_can( 'edit_post', $post_id );
				},
			)
		);
	}

	/**
	 * Registers admin list table columns for the gatherpress_event post type.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function register_admin_columns(): void {
		$post_types = array( self::POST_TYPE );

		foreach ( $post_types as $post_type ) {
			add_filter( "manage_{$post_type}_posts_columns", array( $this, 'add_ticket_column' ) );
			add_action( "manage_{$post_type}_posts_custom_column", array( $this, 'render_ticket_column' ), 10, 2 );
		}

		add_action( 'admin_head', array( $this, 'admin_column_styles' ) );
	}

	/**
	 * Outputs inline CSS to keep the ticket column narrow.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function admin_column_styles(): void {
		$screen = get_current_screen();

		if ( ! $screen instanceof WP_Screen || 'edit' !== $screen->base || self::POST_TYPE !== $screen->post_type ) {
			return;
		}

		echo '<style>'
			. 'td.column-gatherpress_tickets, th.column-gatherpress_tickets{width:3em;text-align:center;}'
			. '.column-gatherpress_tickets a{color:#00a32a;text-decoration:none;}'
			. '.column-gatherpress_tickets a:hover{color:#007017;}'
			. '</style>';
	}

	/**
	 * Adds the ticket URL column to the post list table after the title column.
	 *
	 * @since 0.1.0
	 * @param array<string, string> $columns Existing columns.
	 * @return array<string, string> Modified columns.
	 */
	public function add_ticket_column( array $columns ): array {
		$new_columns = array();

		foreach ( $columns as $key => $label ) {
			$new_columns[ $key ] = $label;

			if ( 'title' === $key ) {
				$new_columns['gatherpress_tickets'] = '<span class="dashicons dashicons-tickets-alt" style="font-size:16px;" title="'
					. esc_attr__( 'Ticket URL', 'gatherpress-tickets' )
					. '"></span><span class="screen-reader-text">'
					. esc_html__( 'Ticket URL', 'gatherpress-tickets' )
					. '</span>';
			}
		}

		return $new_columns;
	}

	/**
	 * Renders the content of the ticket URL column.
	 *
	 * Displays a green check mark linked to the ticket URL when one is set,
	 * or an em-dash when none is available.
	 *
	 * @since 0.1.0
	 * @param string $column_name The name of the current column.
	 * @param int    $post_id     The current post ID.
	 * @return void
	 */
	public function render_ticket_column( string $column_name, int $post_id ): void {
		if ( 'gatherpress_tickets' !== $column_name ) {
			return;
		}

		$raw_meta   = get_post_meta( $post_id, self::META_KEY, true );
		$ticket_url = is_string( $raw_meta ) ? $raw_meta : '';

		if ( '' !== $ticket_url && false !== filter_var( $ticket_url, FILTER_VALIDATE_URL ) ) {
			echo '<a href="' . esc_url( $ticket_url ) . '" target="_blank" rel="noopener noreferrer" title="' . esc_attr( $ticket_url ) . '">';
			echo '<span style="font-size:18px;" aria-hidden="true">&#10003;</span>';
			echo '<span class="screen-reader-text">' . esc_html__( 'Has ticket URL', 'gatherpress-tickets' ) . '</span>';
			echo '</a>';
		} else {
			echo '<span aria-hidden="true" style="color:#999;">&mdash;</span>';
			echo '<span class="screen-reader-text">' . esc_html__( 'No ticket URL', 'gatherpress-tickets' ) . '</span>';
		}
	}
}
