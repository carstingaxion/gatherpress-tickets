<?php
/**
 * Dashboard widget for setting ticket URLs on upcoming events.
 *
 * Displays a list of upcoming GatherPress events that have no ticket URL set,
 * with an inline form to save the URL directly from the WordPress dashboard.
 *
 * @package GatherPress_Tickets
 * @since   0.1.0
 */

declare(strict_types=1);

namespace GatherPress_Tickets;

use GatherPress\Core;
use WP_Query;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Registers and renders the Tickets dashboard widget.
 *
 * @since 0.1.0
 */
class Dashboard {

	use Core\Traits\Singleton;

	/**
	 * Transient key for caching the upcoming-events-without-ticket-URL query.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	private const TRANSIENT_KEY = 'gatherpress_tickets_upcoming_no_url';

	/**
	 * Transient lifetime: 1 hour.
	 *
	 * Short enough to reflect new events promptly; long enough to avoid
	 * hammering the database on busy dashboards.
	 *
	 * @since 0.1.0
	 * @var int
	 */
	private const TRANSIENT_EXPIRATION = HOUR_IN_SECONDS;

	/**
	 * AJAX nonce action.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	private const NONCE_ACTION = 'gatherpress_tickets_dashboard';

	/**
	 * AJAX action name for saving a ticket URL.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	private const AJAX_SAVE = 'gatherpress_tickets_save_url';

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
		add_action( 'wp_dashboard_setup', array( $this, 'register_widget' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_' . self::AJAX_SAVE, array( $this, 'ajax_save_url' ) );
		// Bust cache when the meta is updated outside the widget (e.g. post editor).
		add_action( 'updated_post_meta', array( $this, 'bust_cache_on_update' ), 10, 3 );
		add_action( 'deleted_post_meta', array( $this, 'bust_cache_on_delete' ), 10, 3 );
	}

	/**
	 * Registers the dashboard widget.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function register_widget(): void {
		wp_add_dashboard_widget(
			'gatherpress_tickets_widget',
			__( 'Upcoming Events Without Ticket URL', 'gatherpress-tickets' ),
			array( $this, 'render_widget' )
		);
	}

	/**
	 * Enqueues the inline script on the dashboard page only.
	 *
	 * @since 0.1.0
	 * @param string $hook Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_scripts( string $hook ): void {
		if ( 'index.php' !== $hook ) {
			return;
		}

		// Inline script — no separate asset file needed for this lightweight widget.
		wp_add_inline_script(
			'dashboard', // already enqueued on index.php .
			$this->get_inline_script()
		);

		wp_add_inline_style(
			'dashboard',
			$this->get_inline_style()
		);

		wp_localize_script(
			'dashboard',
			'gpTicketsDashboard',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( self::NONCE_ACTION ),
				'action'  => self::AJAX_SAVE,
				'i18n'    => array(
					'save'   => __( 'Save', 'gatherpress-tickets' ),
					'saving' => __( 'Saving…', 'gatherpress-tickets' ),
					'saved'  => __( 'Saved', 'gatherpress-tickets' ),
					'error'  => __( 'Error — try again', 'gatherpress-tickets' ),
					'edit'   => __( 'Edit URL', 'gatherpress-tickets' ),
				),
			)
		);
	}

	/**
	 * Renders the dashboard widget HTML.
	 *
	 * Mirrors the "Recent Posts" activity-block markup:
	 * one `<li>` per event with a date `<span>` and a plain linked title,
	 * followed by a compact URL input row when the user can edit.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function render_widget(): void {
		$query = $this->get_upcoming_events_without_url();

		if ( ! $query->have_posts() ) {
			echo '<div class="no-activity">'
				. '<p class="smiley" aria-hidden="true"></p>'
				. '<p>' . esc_html__( 'All upcoming events have a ticket URL!', 'gatherpress-tickets' ) . '</p>'
				. '</div>';
			wp_reset_postdata();
			return;
		}

		$can_edit = current_user_can( 'edit_posts' );

		echo '<div id="activity-widget">';
		echo '<div id="gp-tickets-events-list" class="activity-block">';
		echo '<ul>';

		while ( $query->have_posts() ) {
			$query->the_post();
			$event_id  = (int) get_the_ID();
			$edit_link = get_edit_post_link() ?? '#';
			$date      = $this->format_event_date( $event_id );
			$title     = get_the_title();

			echo '<li class="gp-tickets-event-item" data-event-id="' . esc_attr( (string) $event_id ) . '">';

			echo '<span class="gp-tickets-event-date">' . esc_html( $date ) . '</span>';

			// Body column: single stacking context.
			// The title row and the input row are both absolutely positioned
			// inside it so they occupy the same space; only one is visible at
			// a time. The <li> height is set by the body span's own height,
			// which never changes.
			echo '<span class="gp-tickets-event-body">';

			// Title row (always rendered; hidden via CSS while editing).
			echo '<span class="gp-tickets-title-row">';
			printf(
				'<a href="%s" aria-label="%s">%s</a>',
				esc_url( $edit_link ),
				esc_attr(
					sprintf(
					/* translators: %s: event title */
						__( 'Edit &#8220;%s&#8221;', 'gatherpress-tickets' ),
						$title
					) 
				),
				esc_html( $title )
			);
			if ( $can_edit ) {
				echo '<button type="button" class="button button-secondary button-small gp-tickets-edit-trigger">'
					. esc_html__( 'Add URL', 'gatherpress-tickets' )
					. '</button>';
			}
			echo '</span>'; // .gp-tickets-title-row

			// Input row (hidden; overlays the title row when editing).
			if ( $can_edit ) {
				echo '<span class="gp-tickets-url-row" aria-hidden="true">';
				echo '<input type="url" class="gp-tickets-url-input" '
					. 'tabindex="-1" '
					. 'placeholder="' . esc_attr__( 'https://…', 'gatherpress-tickets' ) . '" '
					. 'aria-label="' . esc_attr(
						sprintf(
						/* translators: %s: event title */
							__( 'Ticket URL for %s', 'gatherpress-tickets' ),
							$title
						) 
					) . '" />';
				echo '<button type="button" class="button button-primary button-small gp-tickets-save-btn" tabindex="-1">'
					. esc_html__( 'Save', 'gatherpress-tickets' )
					. '</button>';
				echo '</span>'; // .gp-tickets-url-row
			}

			echo '</span>'; // .gp-tickets-event-body
			echo '</li>';
		}

		echo '</ul>';
		echo '</div>'; // .activity-block
		echo '</div>'; // #activity-widget

		wp_reset_postdata();
	}

	/**
	 * AJAX handler — saves the ticket URL for a given event.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function ajax_save_url(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'gatherpress-tickets' ) ) );
		}

		$raw_id   = is_string( $_POST['event_id'] ?? null ) ? sanitize_text_field( $_POST['event_id'] ) : '';
		$event_id = absint( $raw_id );

		if ( ! $event_id || get_post_type( $event_id ) !== Setup::POST_TYPE ) {
			wp_send_json_error( array( 'message' => __( 'Invalid event ID.', 'gatherpress-tickets' ) ) );
		}

		$raw_url = is_string( $_POST['ticket_url'] ?? null ) ? sanitize_text_field( $_POST['ticket_url'] ) : '';
		$trimmed = trim( wp_unslash( $raw_url ) );

		// Validate against the raw value BEFORE esc_url_raw, which prepends
		// 'http://' to scheme-less strings like '123', causing FILTER_VALIDATE_URL
		// to accept them. Require a scheme + a dot-containing host at minimum.
		if ( '' === $trimmed || false === filter_var( $trimmed, FILTER_VALIDATE_URL ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid URL.', 'gatherpress-tickets' ) ) );
		}

		$url = esc_url_raw( $trimmed );

		update_post_meta( $event_id, Setup::META_KEY, $url );
		$this->bust_cache();

		wp_send_json_success(
			array(
				'message'  => __( 'Ticket URL saved.', 'gatherpress-tickets' ),
				'event_id' => $event_id,
				'url'      => $url,
			)
		);
	}

	/**
	 * Busts the cache when the ticket URL meta is updated.
	 *
	 * Hooked to `updated_post_meta`, which passes a single int meta ID.
	 *
	 * @since 0.1.0
	 * @param int    $meta_id   ID of the updated meta row (unused).
	 * @param int    $object_id Post ID (unused).
	 * @param string $meta_key  Meta key that was updated.
	 * @return void
	 */
	public function bust_cache_on_update( int $meta_id, int $object_id, string $meta_key ): void {
		if ( Setup::META_KEY === $meta_key ) {
			$this->bust_cache();
		}
	}

	/**
	 * Busts the cache when the ticket URL meta is deleted.
	 *
	 * Hooked to `deleted_post_meta`, which passes an array of meta IDs
	 * (not a single int like `updated_post_meta`).
	 *
	 * @since 0.1.0
	 * @param string[] $meta_ids  IDs of the deleted meta rows (unused).
	 * @param int      $object_id Post ID (unused).
	 * @param string   $meta_key  Meta key that was deleted.
	 * @return void
	 */
	public function bust_cache_on_delete( array $meta_ids, int $object_id, string $meta_key ): void {
		if ( Setup::META_KEY === $meta_key ) {
			$this->bust_cache();
		}
	}

	/**
	 * Deletes the transient cache.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	private function bust_cache(): void {
		delete_transient( self::TRANSIENT_KEY );
	}

	/**
	 * Queries upcoming events that have no ticket URL set.
	 *
	 * Results are cached in a transient for one hour.
	 *
	 * @since 0.1.0
	 * @return WP_Query
	 */
	private function get_upcoming_events_without_url(): WP_Query {
		$cached = get_transient( self::TRANSIENT_KEY );

		if ( $cached instanceof WP_Query ) {
			return $cached;
		}

		$query = new WP_Query(
			array(
				'post_type'               => Setup::POST_TYPE,
				'gatherpress_event_query' => 'upcoming',
				'posts_per_page'          => 20,
				'post_status'             => 'publish',
				'orderby'                 => 'datetime',
				'order'                   => 'ASC',
				'no_found_rows'           => true,
				'meta_query'              => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'relation' => 'OR',
						array( // phpcs:ignore Universal.Arrays.MixedKeyedUnkeyedArray.Found, Universal.Arrays.MixedArrayKeyTypes.ImplicitNumericKey
							'key'     => Setup::META_KEY,
							'compare' => 'NOT EXISTS',
						),
						array( // phpcs:ignore Universal.Arrays.MixedKeyedUnkeyedArray.Found
							'key'     => Setup::META_KEY,
							'compare' => '=',
							'value'   => '',
						),
					),
				),
			)
		);

		set_transient( self::TRANSIENT_KEY, $query, self::TRANSIENT_EXPIRATION );

		return $query;
	}

	/**
	 * Formats the event date for display in the widget.
	 *
	 * Uses GatherPress's own datetime meta when available,
	 * falling back to the post publication date.
	 *
	 * @since 0.1.0
	 * @param int $event_id Event post ID.
	 * @return string Human-readable date string.
	 */
	private function format_event_date( int $event_id ): string {
		$raw  = get_post_meta( $event_id, 'gatherpress_datetime_start', true );
		$time = is_string( $raw ) && '' !== $raw
			? (int) strtotime( $raw )
			: (int) get_the_date( 'U', $event_id );

		$today    = current_time( 'Y-m-d' );
		$tomorrow = current_datetime()->modify( '+1 day' )->format( 'Y-m-d' );
		$year     = current_time( 'Y' );
		$date     = gmdate( 'Y-m-d', $time );

		if ( $date === $today ) {
			return __( 'Today', 'default' );
		}

		if ( $date === $tomorrow ) {
			return __( 'Tomorrow', 'default' );
		}

		if ( gmdate( 'Y', $time ) !== $year ) {
			/* translators: Date format for events in a different year, see https://www.php.net/manual/datetime.format.php */
			return date_i18n( __( 'M jS Y', 'default' ), $time );
		}

		/* translators: Date format for events in the current year, see https://www.php.net/manual/datetime.format.php */
		return date_i18n( __( 'M jS', 'default' ), $time );
	}

	/**
	 * Returns the inline JavaScript for the dashboard widget.
	 *
	 * @since 0.1.0
	 * @return string
	 */
	private function get_inline_script(): string {
		return <<<'JS'
document.addEventListener('DOMContentLoaded', function () {
	var cfg = window.gpTicketsDashboard;
	if (!cfg) return;

	function openForm(row) {
		var urlRow = row.querySelector('.gp-tickets-url-row');
		var input  = row.querySelector('.gp-tickets-url-input');
		var btn    = row.querySelector('.gp-tickets-save-btn');
		row.classList.add('gp-tickets-editing');
		urlRow.removeAttribute('aria-hidden');
		input.removeAttribute('tabindex');
		btn.removeAttribute('tabindex');
		input.focus();
	}

	function closeForm(row) {
		var urlRow  = row.querySelector('.gp-tickets-url-row');
		var input   = row.querySelector('.gp-tickets-url-input');
		var btn     = row.querySelector('.gp-tickets-save-btn');
		var trigger = row.querySelector('.gp-tickets-edit-trigger');
		row.classList.remove('gp-tickets-editing');
		urlRow.setAttribute('aria-hidden', 'true');
		input.setAttribute('tabindex', '-1');
		btn.setAttribute('tabindex', '-1');
		btn.textContent = cfg.i18n.save;
		btn.disabled    = false;
		if (trigger) trigger.focus();
	}

	// Trigger: open the form.
	document.querySelectorAll('.gp-tickets-edit-trigger').forEach(function (trigger) {
		trigger.addEventListener('click', function () {
			openForm(trigger.closest('.gp-tickets-event-item'));
		});
	});

	// Escape key or click outside: close the open form.
	function closeIfOutside(target) {
		var editing = document.querySelector('.gp-tickets-event-item.gp-tickets-editing');
		if (editing && !editing.contains(target)) closeForm(editing);
	}
	document.addEventListener('keydown', function (e) {
		if (e.key !== 'Escape') return;
		var editing = document.querySelector('.gp-tickets-event-item.gp-tickets-editing');
		if (editing) closeForm(editing);
	});
	document.addEventListener('mousedown', function (e) {
		closeIfOutside(e.target);
	});

	// Save button.
	document.querySelectorAll('.gp-tickets-save-btn').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var row    = btn.closest('.gp-tickets-event-item');
			var input  = row.querySelector('.gp-tickets-url-input');
			var url    = input ? input.value.trim() : '';

			if (!url) { input && input.focus(); return; }

			btn.disabled    = true;
			btn.textContent = cfg.i18n.saving;

			var data = new URLSearchParams();
			data.append('action',     cfg.action);
			data.append('nonce',      cfg.nonce);
			data.append('event_id',   row.dataset.eventId);
			data.append('ticket_url', url);

			fetch(cfg.ajaxUrl, { method: 'POST', body: data })
				.then(function (r) { return r.json(); })
				.then(function (res) {
					if (res.success) {
						row.style.transition = 'opacity .4s';
						row.style.opacity    = '0';
						setTimeout(function () { row.remove(); }, 420);
					} else {
						btn.textContent = cfg.i18n.error;
						btn.disabled    = false;
					}
				})
				.catch(function () {
					btn.textContent = cfg.i18n.error;
					btn.disabled    = false;
				});
		});
	});
});
JS;
	}

	/**
	 * Returns the inline CSS for the dashboard widget.
	 *
	 * Mirrors the grid layout of core's #future-posts / #published-posts
	 * list items: two columns (fixed date, fluid title), alternating
	 * row background, and no extra chrome.
	 *
	 * @since 0.1.0
	 * @return string
	 */
	private function get_inline_style(): string {
		return '
/* ── List ──────────────────────────────────────────────────────────── */
#gp-tickets-events-list ul { margin: 0; padding: 0; }
#gp-tickets-events-list li.gp-tickets-event-item {
	display: grid;
	grid-template-columns: clamp(60px, calc(2vw + 50px), 80px) auto;
	column-gap: 10px;
	align-items: center;
	color: #646970;
	padding: 4px 12px;
	margin: 0 -12px;
}
#gp-tickets-events-list li.gp-tickets-event-item:nth-child(odd) {
	background-color: #f6f7f7;
}

/* ── Date column ────────────────────────────────────────────────────── */
.gp-tickets-event-date {
	color: #646970;
	white-space: nowrap;
	font-variant-numeric: tabular-nums;
}

/* ── Body column: stacking context, sized by the title row ──────────── */
/* Both .gp-tickets-title-row and .gp-tickets-url-row are absolutely   */
/* positioned so they share the same space. The body span itself takes  */
/* its height from the title row (in-flow), keeping the <li> stable.   */
.gp-tickets-event-body {
	position: relative;
	min-width: 0;
}

/* ── Title row ────────────────────────────────────────────────────── */
.gp-tickets-title-row {
	display: flex;
	align-items: center;
	gap: 6px;
	min-width: 0;
}
.gp-tickets-title-row > a {
	font-weight: 400;
	white-space: nowrap;
	overflow: hidden;
	flex: 1;
	text-overflow: ellipsis;
}
.gp-tickets-edit-trigger { flex-shrink: 0; }

/* ── Input row: absolute overlay, hidden until editing ────────────── */
.gp-tickets-url-row {
	position: absolute;
	inset: 0;
	display: flex;
	align-items: center;
	gap: 4px;
	visibility: hidden;
	pointer-events: none;
}
.gp-tickets-editing .gp-tickets-title-row {
	visibility: hidden;
}
.gp-tickets-editing .gp-tickets-url-row {
	visibility: visible;
	pointer-events: auto;
}
/* Input fills the row; Save button sits naturally at its button-small size */
.gp-tickets-url-input { flex: 1; min-width: 0; }
';
	}
}
