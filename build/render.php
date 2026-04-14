<?php
/**
 * Server-side rendering for the GatherPress Tickets block.
 *
 * Implements a Singleton renderer class that handles the dynamic
 * output of the ticket button, including URL fallback logic and
 * proper sanitization of all output.
 *
 * @package TelexGatherpressTickets
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block default content.
 * @var WP_Block $block      Block instance.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Telex_Gatherpress_Tickets_Renderer' ) ) {

	/**
	 * Renderer class for the GatherPress Tickets block.
	 *
	 * Uses the Singleton pattern to ensure a single instance handles
	 * all rendering operations. Provides URL resolution with fallback
	 * logic and generates properly escaped HTML output.
	 *
	 * @since 0.1.0
	 */
	class Telex_Gatherpress_Tickets_Renderer {

		/**
		 * The single instance of this renderer class.
		 *
		 * @since 0.1.0
		 * @var Telex_Gatherpress_Tickets_Renderer|null
		 */
		private static ?Telex_Gatherpress_Tickets_Renderer $instance = null;

		/**
		 * Returns the single instance of the renderer.
		 *
		 * @since 0.1.0
		 *
		 * @return Telex_Gatherpress_Tickets_Renderer The singleton instance.
		 */
		public static function get_instance(): Telex_Gatherpress_Tickets_Renderer {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Private constructor to prevent direct instantiation.
		 *
		 * @since 0.1.0
		 */
		private function __construct() {}

		/**
		 * Renders the GatherPress Tickets block.
		 *
		 * Resolves the ticket URL with fallback logic, builds inline
		 * styles from attributes, and outputs the appropriate HTML element.
		 *
		 * @since 0.1.0
		 *
		 * @param array    $attributes Block attributes.
		 * @param string   $content    Block default content.
		 * @param WP_Block $block      Block instance with context.
		 *
		 * @return string The rendered block HTML.
		 */
		public function render( array $attributes, string $content, WP_Block $block ): string {
			$post_id = $block->context['postId'] ?? get_the_ID();

			if ( ! $post_id ) {
				return '';
			}

			$label      = ! empty( $attributes['label'] ) ? $attributes['label'] : __( 'Get Tickets', 'telex-gatherpress-tickets' );
			$ticket_url = $this->resolve_ticket_url( $post_id );
			$text_align = ! empty( $attributes['textAlign'] ) ? $attributes['textAlign'] : 'center';
			$styles     = $this->build_inline_styles( $attributes );

			$wrapper_attributes = get_block_wrapper_attributes(
				array(
					'class' => 'gatherpress-tickets gatherpress-tickets--align-' . esc_attr( $text_align ),
				)
			);

			ob_start();

			echo '<div ' . $wrapper_attributes . '>';

			if ( ! empty( $ticket_url ) ) {
				printf(
					'<a href="%s" class="gatherpress-tickets__button" style="%s" target="_blank" rel="noopener noreferrer"><span class="gatherpress-tickets__button-label">%s</span></a>',
					esc_url( $ticket_url ),
					esc_attr( $styles ),
					wp_kses_post( $label )
				);
			} else {
				printf(
					'<span class="gatherpress-tickets__button" style="%s" role="button" tabindex="0"><span class="gatherpress-tickets__button-label">%s</span></span><span class="gatherpress-tickets__fallback-message">%s</span>',
					esc_attr( $styles ),
					wp_kses_post( $label ),
					esc_html__( 'Get tickets at the venue', 'telex-gatherpress-tickets' )
				);
			}

			echo '</div>';

			return ob_get_clean();
		}

		/**
		 * Resolves the ticket URL with fallback logic.
		 *
		 * Priority order:
		 * 1. gatherpress_tickets_url post meta
		 * 2. GatherPress venue website URL from _gatherpress_venue meta
		 * 3. Empty string (no URL available)
		 *
		 * @since 0.1.0
		 *
		 * @param int $post_id The current post ID.
		 *
		 * @return string The resolved URL, or empty string if none found.
		 */
		private function resolve_ticket_url( int $post_id ): string {
			$ticket_url = get_post_meta( $post_id, 'gatherpress_tickets_url', true );

			if ( ! empty( $ticket_url ) && filter_var( $ticket_url, FILTER_VALIDATE_URL ) ) {
				return $ticket_url;
			}

			$venue_meta = get_post_meta( $post_id, '_gatherpress_venue', true );

			if ( ! empty( $venue_meta ) ) {
				$venue_data = is_string( $venue_meta ) ? json_decode( $venue_meta, true ) : $venue_meta;

				if ( is_array( $venue_data ) && ! empty( $venue_data['website'] ) ) {
					$venue_url = $venue_data['website'];

					if ( filter_var( $venue_url, FILTER_VALIDATE_URL ) ) {
						return $venue_url;
					}
				}
			}

			return '';
		}

		/**
		 * Builds the inline CSS style string from block attributes.
		 *
		 * Converts block attributes into a semicolon-separated CSS
		 * property string suitable for use in the style attribute.
		 *
		 * @since 0.1.0
		 *
		 * @param array $attributes The block attributes.
		 *
		 * @return string The inline CSS style string.
		 */
		private function build_inline_styles( array $attributes ): string {
			$styles = array();

			$property_map = array(
				'backgroundColor' => 'background-color',
				'textColor'       => 'color',
				'fontSize'        => 'font-size',
				'fontFamily'      => 'font-family',
				'fontWeight'      => 'font-weight',
				'textTransform'   => 'text-transform',
				'letterSpacing'   => 'letter-spacing',
				'lineHeight'      => 'line-height',
				'borderColor'     => 'border-color',
				'borderWidth'     => 'border-width',
				'borderStyle'     => 'border-style',
				'paddingTop'      => 'padding-top',
				'paddingRight'    => 'padding-right',
				'paddingBottom'   => 'padding-bottom',
				'paddingLeft'     => 'padding-left',
				'width'           => 'width',
			);

			foreach ( $property_map as $attr_key => $css_property ) {
				if ( ! empty( $attributes[ $attr_key ] ) ) {
					$styles[] = $css_property . ':' . $attributes[ $attr_key ];
				}
			}

			if ( ! empty( $attributes['borderRadius'] ) && is_array( $attributes['borderRadius'] ) ) {
				$radius = $attributes['borderRadius'];
				$radius_map = array(
					'topLeft'     => 'border-top-left-radius',
					'topRight'    => 'border-top-right-radius',
					'bottomLeft'  => 'border-bottom-left-radius',
					'bottomRight' => 'border-bottom-right-radius',
				);

				foreach ( $radius_map as $corner => $css_prop ) {
					if ( ! empty( $radius[ $corner ] ) ) {
						$styles[] = $css_prop . ':' . $radius[ $corner ];
					}
				}
			}

			$styles[] = 'display:inline-block';
			$styles[] = 'text-decoration:none';
			$styles[] = 'cursor:pointer';
			$styles[] = 'box-sizing:border-box';

			if ( ! empty( $attributes['borderWidth'] ) && empty( $attributes['borderStyle'] ) ) {
				$styles[] = 'border-style:solid';
			}

			return implode( ';', $styles );
		}
	}
}

$renderer = Telex_Gatherpress_Tickets_Renderer::get_instance();
echo $renderer->render( $attributes, $content, $block );