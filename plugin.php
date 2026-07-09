<?php
/**
 * Plugin Name:       GatherPress Tickets Block
 * Plugin URI:        https://github.com/carstingaxion/gatherpress-tickets
 * Description:       A block variation of core/button for GatherPress event tickets, with post meta integration and intelligent URL fallback.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Requires plugins:  gatherpress
 * Author:            Carsten Bach & WordPress Telex
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       telex-gatherpress-tickets
 * Domain Path:       /languages
 *
 * @package GatherPress_Tickets
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

// Constants.
define( 'GATHERPRESS_TICKETS_VERSION', current( get_file_data( __FILE__, array( 'Version' ), 'plugin' ) ) );
define( 'GATHERPRESS_TICKETS_CORE_PATH', __DIR__ );

/**
 * Adds the GatherPress_Tickets namespace to the GatherPress autoloader.
 *
 * Hooks into the 'gatherpress_autoloader' filter so that the GatherPress
 * core autoloader can resolve GatherPress_Tickets\* class names to files
 * under this plugin's includes/classes/ directory.
 *
 * @since 0.1.0
 *
 * @param array<string, string> $namespaces An associative array of namespaces and their root paths.
 * @return array<string, string> Modified array with the GatherPress_Tickets namespace added.
 */
function gatherpress_tickets_autoloader( array $namespaces ): array {
	$namespaces['GatherPress_Tickets'] = GATHERPRESS_TICKETS_CORE_PATH;

	return $namespaces;
}
add_filter( 'gatherpress_autoloader', 'gatherpress_tickets_autoloader' );

/**
 * Initializes the plugin once all plugins are loaded.
 *
 * Boots only when GatherPress core is active, identified by the presence
 * of the GATHERPRESS_VERSION constant.
 *
 * @since 0.1.0
 * @return void
 */
function gatherpress_tickets_setup(): void {
	if ( defined( 'GATHERPRESS_VERSION' ) ) {
		\GatherPress_Tickets\Setup::get_instance();
		\GatherPress_Tickets\Block::get_instance();
		\GatherPress_Tickets\Dashboard::get_instance();
	}
}
add_action( 'plugins_loaded', 'gatherpress_tickets_setup' );
