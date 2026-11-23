<?php
/**
 * Plugin Name: Caddy Souin Cache Manager
 * Plugin URI:  https://github.com/morsalin1342/caddy-souin-cache-manager
 * Description: Manage Souin cache for Caddy server within WP Admin. View cache keys and purge cache.
 * Version:     1.8.15
 * Requires PHP: 8.1
 * Author:      MD Morsalin
 * Author URI:  https://morsalin.online
 * License:     GPLv2 or later
 */

use morsalin1342\CaddySouinCacheManager\CaddySouinCacheManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	// Installed from a source checkout rather than a release zip: say so
	// instead of silently doing nothing.
	add_action( 'admin_notices', static function () {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__( 'Caddy Souin Cache Manager is not running: its vendor/ directory is missing. Install the release zip, or run "composer install --no-dev" in the plugin directory.', 'caddy-souin-cache-manager' )
		);
	} );

	return;
}

require_once __DIR__ . '/vendor/autoload.php';

$caddy_souin_cache_manager = new CaddySouinCacheManager();
$caddy_souin_cache_manager->init();
