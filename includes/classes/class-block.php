<?php
/**
 * Block variation assets and render filter for GatherPress Tickets.
 *
 * @package GatherPress_Tickets
 * @since   0.1.0
 */

declare(strict_types=1);

namespace GatherPress_Tickets;

use GatherPress\Core;
use WP_HTML_Tag_Processor;
use WP_Term;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Enqueues the editor script and filters the core/button render output.
 *
 * @since 0.1.0
 */
class Block {

	use Core\Traits\Singleton;

	/**
	 * The CSS class that identifies the block variation.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	public const VARIATION_CLASS = 'is-style-gatherpress-tickets';

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
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
		add_filter( 'render_block_core/button', array( $this, 'filter_button_render' ), 10, 2 );
	}

	/**
	 * Enqueues the editor script that registers the block variation
	 * and adds the inspector controls.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function enqueue_editor_assets(): void {
		$asset_file = plugin_dir_path( __DIR__ . '/../../plugin.php' ) . 'build/index.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		/**
		 * The asset file is expected to return an array with 'dependencies' and 'version' keys.
		 *
		 * @var array{dependencies: string[], version: string} $asset
		 */
		$asset = require $asset_file; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable

		wp_enqueue_script(
			'gatherpress-tickets-editor',
			plugins_url( 'build/index.js', __DIR__ . '/../../plugin.php' ),
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_set_script_translations(
			'gatherpress-tickets-editor',
			'gatherpress-tickets'
		);
	}

	/**
	 * Filters the rendered output of core/button blocks that use
	 * the GatherPress Tickets variation.
	 *
	 * Replaces the placeholder URL with the resolved ticket URL
	 * (post meta → venue website → venue term archive → no-link fallback).
	 *
	 * @since 0.1.0
	 * @param string               $block_content The rendered block HTML.
	 * @param array<string, mixed> $block         The parsed block data.
	 * @return string The modified block HTML.
	 */
	public function filter_button_render( string $block_content, array $block ): string {
		$attrs      = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array();
		$class_name = is_string( $attrs['className'] ?? null ) ? $attrs['className'] : '';

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
			$processor = new WP_HTML_Tag_Processor( $block_content );

			if ( $processor->next_tag( array( 'tag_name' => 'A' ) ) ) {
				$processor->set_attribute( 'href', esc_url( $ticket_url ) );
				$processor->set_attribute( 'target', '_blank' );
				$processor->set_attribute( 'rel', 'noopener noreferrer' );
			}

			$block_content = $processor->get_updated_html();

			// If the resolved URL came from a fallback (not from post meta),
			// update the button label to the fallback message.
			$raw_direct = get_post_meta( $post_id, Setup::META_KEY, true );
			$direct_url = is_string( $raw_direct ) ? $raw_direct : '';

			if ( '' === $direct_url || false === filter_var( $direct_url, FILTER_VALIDATE_URL ) ) {
				$block_content = $this->replace_button_label( $block_content, $fallback_label );
			}
		} else {
			$block_content = $this->replace_button_label( $block_content, $fallback_label );
			$block_content = $this->convert_anchor_to_span( $block_content );
		}

		return $block_content;
	}

	/**
	 * Replaces the button label text inside the anchor element.
	 *
	 * @since 0.1.0
	 * @param string $block_content The block HTML.
	 * @param string $new_label     The new label text.
	 * @return string The modified block HTML.
	 */
	private function replace_button_label( string $block_content, string $new_label ): string {
		$processor = new WP_HTML_Tag_Processor( $block_content );

		if ( ! $processor->next_tag( array( 'tag_name' => 'A' ) ) ) {
			return $block_content;
		}

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
	 * Converts the anchor element to a non-interactive span element.
	 *
	 * Transfers all attributes except href, target and rel, and adds
	 * role="button" tabindex="0" for accessibility.
	 *
	 * @since 0.1.0
	 * @param string $block_content The block HTML containing an <a> element.
	 * @return string The modified HTML with the <a> converted to <span>.
	 */
	private function convert_anchor_to_span( string $block_content ): string {
		$processor = new WP_HTML_Tag_Processor( $block_content );

		if ( ! $processor->next_tag( array( 'tag_name' => 'A' ) ) ) {
			return $block_content;
		}

		$skip_attrs = array( 'href', 'target', 'rel' );
		$names      = $processor->get_attribute_names_with_prefix( '' );
		$attributes = array();

		if ( is_array( $names ) ) {
			foreach ( $names as $name ) {
				if ( ! is_string( $name ) || in_array( $name, $skip_attrs, true ) ) {
					continue;
				}

				$value = $processor->get_attribute( $name );

				if ( null !== $value ) {
					$attributes[ $name ] = $value;
				}
			}
		}

		$span_attrs = '';

		foreach ( $attributes as $name => $value ) {
			$span_attrs .= true === $value
				? ' ' . $name
				: ' ' . $name . '="' . esc_attr( (string) $value ) . '"';
		}

		$span_attrs .= ' role="button" tabindex="0"';

		$a_pos = strpos( $block_content, '<a' );

		if ( false === $a_pos ) {
			return $block_content;
		}

		$open_pos  = strpos( $block_content, '>', $a_pos );
		$close_pos = strpos( $block_content, '</a>' );

		if ( false === $open_pos || false === $close_pos || $close_pos <= $open_pos ) {
			return $block_content;
		}

		$inner  = substr( $block_content, $open_pos + 1, $close_pos - $open_pos - 1 );
		$before = substr( $block_content, 0, $a_pos );
		$after  = substr( $block_content, $close_pos + 4 );

		return $before . '<span' . $span_attrs . '>' . $inner . '</span>' . $after;
	}

	/**
	 * Returns the fallback label for when no direct ticket URL is set.
	 *
	 * @since 0.1.0
	 * @return string The fallback label.
	 */
	private function get_fallback_label(): string {
		return __( 'Get tickets at the venue', 'gatherpress-tickets' );
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
	 * @param int $post_id The current post ID.
	 * @return string The resolved URL, or empty string if none found.
	 */
	private function resolve_ticket_url( int $post_id ): string {
		$raw_ticket = get_post_meta( $post_id, Setup::META_KEY, true );
		$ticket_url = is_string( $raw_ticket ) ? $raw_ticket : '';

		if ( '' !== $ticket_url && false !== filter_var( $ticket_url, FILTER_VALIDATE_URL ) ) {
			return $ticket_url;
		}

		$raw_venue_meta = get_post_meta( $post_id, '_gatherpress_venue', true );

		if ( is_string( $raw_venue_meta ) && '' !== $raw_venue_meta ) {
			$venue_data = json_decode( $raw_venue_meta, true );

			if ( is_array( $venue_data ) && is_string( $venue_data['website'] ?? null ) ) {
				$venue_url = $venue_data['website'];

				if ( false !== filter_var( $venue_url, FILTER_VALIDATE_URL ) ) {
					return $venue_url;
				}
			}
		}

		$venue_terms = get_the_terms( $post_id, '_gatherpress_venue' );

		if ( is_array( $venue_terms ) && array() !== $venue_terms ) {
			$term_link = get_term_link( $venue_terms[0] );

			if ( is_string( $term_link ) ) {
				return $term_link;
			}
		}

		return '';
	}
}
