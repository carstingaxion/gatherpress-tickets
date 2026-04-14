<?php
/**
 * Plugin Name:       GatherPress Tickets Block
 * Description:       A block variation of core/button for GatherPress event tickets, with post meta integration and intelligent URL fallback.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            Carsten Bach & WordPress Telex
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       telex-gatherpress-tickets
 *
 * @package TelexGatherpressTickets
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Telex_Gatherpress_Tickets' ) ) {

	/**
	 * Main plugin class for the GatherPress Tickets block variation.
	 *
	 * Implements the Singleton pattern. Handles post meta registration,
	 * editor asset enqueuing, and frontend render filtering for the
	 * core/button variation.
	 *
	 * @since 0.1.0
	 */
	class Telex_Gatherpress_Tickets {

		/**
		 * The single instance of this class.
		 *
		 * @since 0.1.0
		 * @var Telex_Gatherpress_Tickets|null
		 */
		private static ?Telex_Gatherpress_Tickets $instance = null;

		/**
		 * The post meta key for the ticket URL.
		 *
		 * @since 0.1.0
		 * @var string
		 */
		const META_KEY = 'gatherpress_tickets_url';

		/**
		 * The CSS class that identifies the variation.
		 *
		 * @since 0.1.0
		 * @var string
		 */
		const VARIATION_CLASS = 'is-style-gatherpress-tickets';

		/**
		 * Returns the single instance of this class.
		 *
		 * @since 0.1.0
		 *
		 * @return Telex_Gatherpress_Tickets The singleton instance.
		 */
		public static function get_instance(): Telex_Gatherpress_Tickets {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Constructor. Registers all hooks.
		 *
		 * @since 0.1.0
		 */
		private function __construct() {
			add_action( 'init', array( $this, 'register_meta' ) );
			add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
			add_filter( 'render_block_core/button', array( $this, 'filter_button_render' ), 10, 2 );
			add_action( 'admin_init', array( $this, 'register_admin_columns' ) );
		}

		/**
		 * Registers admin list table columns for all public post types.
		 *
		 * Hooks into the manage_{post_type}_posts_columns and
		 * manage_{post_type}_posts_custom_column filters/actions
		 * for each public post type to display a ticket URL indicator.
		 *
		 * @since 0.1.0
		 *
		 * @return void
		 */
		public function register_admin_columns(): void {
			/** @var array<string, string> $post_types */
			$post_types = get_post_types( array( 'public' => true ), 'names' );

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
		 *
		 * @return void
		 */
		public function admin_column_styles(): void {
			$screen = get_current_screen();

			if ( ! $screen instanceof \WP_Screen || 'edit' !== $screen->base ) {
				return;
			}

			echo '<style>'
				. '.column-gatherpress_tickets{width:3em;text-align:center;}'
				. '.column-gatherpress_tickets a{color:#00a32a;text-decoration:none;}'
				. '.column-gatherpress_tickets a:hover{color:#007017;}'
				. '</style>';
		}

		/**
		 * Adds the ticket URL column to the post list table.
		 *
		 * Inserts a narrow column with a ticket icon header after the title column.
		 *
		 * @since 0.1.0
		 *
		 * @param array<string, string> $columns The existing columns.
		 *
		 * @return array<string, string> The modified columns.
		 */
		public function add_ticket_column( array $columns ): array {
			/** @var array<string, string> $new_columns */
			$new_columns = array();

			foreach ( $columns as $key => $label ) {
				$new_columns[ $key ] = $label;

				if ( 'title' === $key ) {
					$new_columns['gatherpress_tickets'] = '<span class="dashicons dashicons-tickets-alt" style="font-size:16px;" title="'
						. esc_attr__( 'Ticket URL', 'telex-gatherpress-tickets' )
						. '"></span><span class="screen-reader-text">'
						. esc_html__( 'Ticket URL', 'telex-gatherpress-tickets' )
						. '</span>';
				}
			}

			return $new_columns;
		}

		/**
		 * Renders the content of the ticket URL column.
		 *
		 * Displays a green check mark if the post has a valid ticket URL
		 * stored in the gatherpress_tickets_url post meta field.
		 *
		 * @since 0.1.0
		 *
		 * @param string $column_name The name of the current column.
		 * @param int    $post_id     The current post ID.
		 *
		 * @return void
		 */
		public function render_ticket_column( string $column_name, int $post_id ): void {
			if ( 'gatherpress_tickets' !== $column_name ) {
				return;
			}

			$raw_meta = get_post_meta( $post_id, self::META_KEY, true );

			/** @var string $ticket_url */
			$ticket_url = is_string( $raw_meta ) ? $raw_meta : '';

			if ( '' !== $ticket_url && false !== filter_var( $ticket_url, FILTER_VALIDATE_URL ) ) {
				echo '<a href="' . esc_url( $ticket_url ) . '" target="_blank" rel="noopener noreferrer" title="' . esc_attr( $ticket_url ) . '">';
				echo '<span style="font-size:18px;" aria-hidden="true">&#10003;</span>';
				echo '<span class="screen-reader-text">' . esc_html__( 'Has ticket URL', 'telex-gatherpress-tickets' ) . '</span>';
				echo '</a>';
			} else {
				echo '<span aria-hidden="true" style="color:#999;">&mdash;</span>';
				echo '<span class="screen-reader-text">' . esc_html__( 'No ticket URL', 'telex-gatherpress-tickets' ) . '</span>';
			}
		}

		/**
		 * Registers the gatherpress_tickets_url post meta field.
		 *
		 * @since 0.1.0
		 *
		 * @return void
		 */
		public function register_meta(): void {
			register_post_meta(
				'',
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
		 * Enqueues the editor script that registers the block variation
		 * and adds the inspector controls.
		 *
		 * @since 0.1.0
		 *
		 * @return void
		 */
		public function enqueue_editor_assets(): void {
			$asset_file = plugin_dir_path( __FILE__ ) . 'build/index.asset.php';

			if ( ! file_exists( $asset_file ) ) {
				return;
			}

			/** @var mixed $asset */
			$asset = include $asset_file;

			if ( ! is_array( $asset ) || ! isset( $asset['dependencies'], $asset['version'] ) ) {
				return;
			}

			/** @var array{dependencies: string[], version: string} $asset */

			wp_enqueue_script(
				'telex-gatherpress-tickets-editor',
				plugins_url( 'build/index.js', __FILE__ ),
				$asset['dependencies'],
				(string) $asset['version'],
				true
			);

			wp_set_script_translations(
				'telex-gatherpress-tickets-editor',
				'telex-gatherpress-tickets'
			);
		}

		/**
		 * Filters the rendered output of core/button blocks that use
		 * the GatherPress Tickets variation.
		 *
		 * Replaces the placeholder URL with the resolved ticket URL
		 * (post meta -> venue website -> venue term archive -> no-link fallback).
		 *
		 * @since 0.1.0
		 *
		 * @param string               $block_content The rendered block HTML.
		 * @param array<string, mixed> $block         The parsed block data.
		 *
		 * @return string The modified block HTML.
		 */
		public function filter_button_render( string $block_content, array $block ): string {
			$class_name = '';

			if ( isset( $block['attrs']['className'] ) && is_string( $block['attrs']['className'] ) ) {
				$class_name = $block['attrs']['className'];
			}

			if ( false === strpos( $class_name, self::VARIATION_CLASS ) ) {
				return $block_content;
			}

			$post_id = get_the_ID();

			if ( false === $post_id ) {
				return $block_content;
			}

			$ticket_url     = $this->resolve_ticket_url( $post_id );
			$fallback_label = $this->get_fallback_label();

			if ( '' !== $ticket_url ) {
				// Replace the placeholder href with the real ticket URL.
				$processor = new \WP_HTML_Tag_Processor( $block_content );

				if ( $processor->next_tag( 'a' ) ) {
					$processor->set_attribute( 'href', esc_url( $ticket_url ) );
					$processor->set_attribute( 'target', '_blank' );
					$processor->set_attribute( 'rel', 'noopener noreferrer' );
				}

				$block_content = $processor->get_updated_html();

				// If the resolved URL came from a fallback (not from post meta),
				// update the button label to the fallback message.
				$raw_direct = get_post_meta( $post_id, self::META_KEY, true );
				$direct_url = is_string( $raw_direct ) ? $raw_direct : '';

				if ( '' === $direct_url || false === filter_var( $direct_url, FILTER_VALIDATE_URL ) ) {
					$block_content = $this->replace_button_label( $block_content, $fallback_label );
				}
			} else {
				// No URL available at all: convert link to a span with fallback label.
				$block_content = $this->replace_button_label( $block_content, $fallback_label );
				$block_content = $this->convert_anchor_to_span( $block_content );
			}

			return $block_content;
		}

		/**
		 * Replaces the button label text inside the anchor element.
		 *
		 * Uses WP_HTML_Tag_Processor to locate the <a> tag and then
		 * replaces only the text content between the opening and closing tags.
		 *
		 * @since 0.1.0
		 *
		 * @param string $block_content The block HTML.
		 * @param string $new_label     The new label text.
		 *
		 * @return string The modified block HTML.
		 */
		private function replace_button_label( string $block_content, string $new_label ): string {
			$processor = new \WP_HTML_Tag_Processor( $block_content );

			// Find the <a> tag to verify it exists.
			if ( ! $processor->next_tag( 'a' ) ) {
				return $block_content;
			}

			/*
			 * WP_HTML_Tag_Processor can modify attributes but not inner text.
			 * Use a targeted string replacement between >(text)</a>.
			 *
			 * Find the first <a...> and its matching </a>,
			 * then replace only the inner content.
			 */
			$a_pos = strpos( $block_content, '<a' );

			if ( false === $a_pos ) {
				return $block_content;
			}

			$open_pos  = strpos( $block_content, '>', $a_pos );
			$close_pos = strpos( $block_content, '</a>' );

			if ( false === $open_pos || false === $close_pos || $close_pos <= $open_pos ) {
				return $block_content;
			}

			return substr( $block_content, 0, $open_pos + 1 )
				. esc_html( $new_label )
				. substr( $block_content, $close_pos );
		}

		/**
		 * Converts an anchor element to a non-interactive span element.
		 *
		 * Uses WP_HTML_Tag_Processor to read all attributes from the <a> tag,
		 * then rebuilds the element as a <span> with the same attributes
		 * (excluding href, target, rel) plus role="button" and tabindex="0".
		 *
		 * @since 0.1.0
		 *
		 * @param string $block_content The block HTML containing an <a> element.
		 *
		 * @return string The modified HTML with the <a> converted to <span>.
		 */
		private function convert_anchor_to_span( string $block_content ): string {
			$processor = new \WP_HTML_Tag_Processor( $block_content );

			if ( ! $processor->next_tag( 'a' ) ) {
				return $block_content;
			}

			/*
			 * Collect all attributes from the <a> tag that should transfer
			 * to the <span>. Skip link-specific attributes.
			 */
			$skip_attrs = array( 'href', 'target', 'rel' );

			/** @var array<string, string|true> $attributes */
			$attributes = array();

			// Get attribute names via prefix inspection.
			$names = $processor->get_attribute_names_with_prefix( '' );

			if ( is_array( $names ) ) {
				foreach ( $names as $name ) {
					if ( ! is_string( $name ) ) {
						continue;
					}

					if ( in_array( $name, $skip_attrs, true ) ) {
						continue;
					}

					$value = $processor->get_attribute( $name );

					if ( null === $value ) {
						continue;
					}

					$attributes[ $name ] = $value;
				}
			}

			// Build the <span> opening tag.
			$span_attrs = '';

			foreach ( $attributes as $name => $value ) {
				if ( true === $value ) {
					// Boolean attribute.
					$span_attrs .= ' ' . $name;
				} else {
					$span_attrs .= ' ' . $name . '="' . esc_attr( (string) $value ) . '"';
				}
			}

			$span_attrs .= ' role="button" tabindex="0"';

			// Extract the inner content between <a...> and </a>.
			$a_pos = strpos( $block_content, '<a' );

			if ( false === $a_pos ) {
				return $block_content;
			}

			$open_pos  = strpos( $block_content, '>', $a_pos );
			$close_pos = strpos( $block_content, '</a>' );

			if ( false === $open_pos || false === $close_pos || $close_pos <= $open_pos ) {
				return $block_content;
			}

			$inner_content = substr( $block_content, $open_pos + 1, $close_pos - $open_pos - 1 );
			$before        = substr( $block_content, 0, $a_pos );
			$after         = substr( $block_content, $close_pos + 4 ); // 4 = strlen('</a>').

			return $before . '<span' . $span_attrs . '>' . $inner_content . '</span>' . $after;
		}

		/**
		 * Returns the fallback label for when no direct ticket URL is set.
		 *
		 * @since 0.1.0
		 *
		 * @return string The fallback label.
		 */
		private function get_fallback_label(): string {
			return __( 'Get tickets at the venue', 'telex-gatherpress-tickets' );
		}

		/**
		 * Resolves the ticket URL with fallback logic.
		 *
		 * Priority order:
		 * 1. gatherpress_tickets_url post meta
		 * 2. GatherPress venue website URL from _gatherpress_venue meta
		 * 3. Venue term archive URL from _gatherpress_venue taxonomy
		 * 4. Empty string (no URL available)
		 *
		 * @since 0.1.0
		 *
		 * @param int $post_id The current post ID.
		 *
		 * @return string The resolved URL, or empty string if none found.
		 */
		private function resolve_ticket_url( int $post_id ): string {
			// 1. Check direct ticket URL from post meta.
			$raw_ticket = get_post_meta( $post_id, self::META_KEY, true );
			$ticket_url = is_string( $raw_ticket ) ? $raw_ticket : '';

			if ( '' !== $ticket_url && false !== filter_var( $ticket_url, FILTER_VALIDATE_URL ) ) {
				return $ticket_url;
			}

			// 2. Check GatherPress venue website from venue meta.
			$raw_venue_meta = get_post_meta( $post_id, '_gatherpress_venue', true );

			if ( is_string( $raw_venue_meta ) && '' !== $raw_venue_meta ) {
				/** @var mixed $venue_data */
				$venue_data = json_decode( $raw_venue_meta, true );

				if ( is_array( $venue_data ) && isset( $venue_data['website'] ) && is_string( $venue_data['website'] ) ) {
					$venue_url = $venue_data['website'];

					if ( false !== filter_var( $venue_url, FILTER_VALIDATE_URL ) ) {
						return $venue_url;
					}
				}
			}

			// 3. Check for a venue term in the _gatherpress_venue taxonomy.
			$venue_terms = get_the_terms( $post_id, '_gatherpress_venue' );

			if ( is_array( $venue_terms ) && array() !== $venue_terms ) {
				$venue_term = reset( $venue_terms );

				if ( $venue_term instanceof \WP_Term ) {
					$term_link = get_term_link( $venue_term );

					if ( is_string( $term_link ) ) {
						return $term_link;
					}
				}
			}

			// 4. No URL available.
			return '';
		}
	}

	Telex_Gatherpress_Tickets::get_instance();
}
