<?php
/**
 * Plugin Name: Caddy Souin Cache Manager
 * Plugin URI:  https://github.com/morsalin1342/caddy-souin-cache-manager
 * Description: Manage Souin cache for Caddy server within WP Admin. View cache keys and purge cache.
  * Version:     1.8.13
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
	return;
}

require_once __DIR__ . '/vendor/autoload.php';

$caddy_souin_cache_manager = new CaddySouinCacheManager();
$caddy_souin_cache_manager->init();
