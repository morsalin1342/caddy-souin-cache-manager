<?php

namespace morsalin1342\CaddySouinCacheManager;

use morsalin1342\CaddySouinCacheManager\Souin\Api;
use WP_CLI;

class CaddySouinCacheManagerCli {
	private Api $api;

	public function __construct() {
		// In a more advanced setup, we would inject this dependency.
		// For now, we create it here based on the saved options.
		$options         = get_option( 'cscm_settings' );
		$default_api_url = 'http://127.0.0.1:2019/souin-api/souin';
		$api_url         = ! empty( $options['api_url'] ) ? $options['api_url'] : $default_api_url;
		$this->api       = new Api( $api_url );
	}

	/**
	 * Displays the current status and configuration of the Souin Cache Manager.
	 */
	public function status( $args, $assoc_args ): void {
		$options    = get_option( 'cscm_settings' );
		$api_url    = $options['api_url'] ?? 'http://127.0.0.1:2019/souin-api/souin';
		$auto_purge = ( ! isset( $options['auto_purge'] ) || $options['auto_purge'] == 1 ) ? 'Enabled' : 'Disabled';

		WP_CLI::line( 'Caddy Souin Cache Manager Status' );
		WP_CLI::line( '--------------------------------' );
		WP_CLI::line( "Souin API URL: " . $api_url );
		WP_CLI::line( "Automatic Purge: " . $auto_purge );
	}

	/**
	 * Lists all keys currently in the Souin cache.
	 */
	public function list( $args, $assoc_args ): void {
		$keys = $this->api->get_keys();

		if ( is_wp_error( $keys ) ) {
			WP_CLI::error( "Failed to get cache keys: " . $keys->get_error_message() );
		}

		if ( empty( $keys ) ) {
			WP_CLI::line( "No cache keys found." );

			return;
		}

		$formatted_items = array_map( static function ( $key ) {
			return [ 'key' => $key ];
		}, $keys );

		WP_CLI\Utils\format_items( $assoc_args['format'] ?? 'table', $formatted_items, array( 'key' ) );
	}

	/**
	 * Purges the entire Souin cache.
	 */
	public function purge_all( $args, $assoc_args ): void {
		WP_CLI::confirm( "Are you sure you want to purge the entire cache?" );

		$result = $this->api->purge_all();

		if ( is_wp_error( $result ) ) {
			WP_CLI::error( "Failed to purge all: " . $result->get_error_message() );
		} else {
			WP_CLI::success( "Souin cache completely purged." );
		}
	}

	/**
	 * Purges one or more specific keys from the Souin cache.
	 */
	public function purge_key( $args, $assoc_args ): void {
		if ( empty( $args ) ) {
			WP_CLI::error( "Please provide at least one key to purge." );
		}

		$count         = count( $args );
		$progress      = WP_CLI\Utils\make_progress_bar( "Purging $count keys", $count );
		$success_count = 0;
		$fail_count    = 0;

		foreach ( $args as $key ) {
			$result = $this->api->purge_by_key( $key );
			if ( is_wp_error( $result ) ) {
				$fail_count ++;
			} else {
				$success_count ++;
			}
			$progress->tick();
		}
		$progress->finish();

		WP_CLI::success( "Bulk purge complete. Succeeded: $success_count, Failed: $fail_count." );
	}

	/**
	 * Purges keys from the Souin cache that match a regex pattern.
	 */
	public function purge_regex( $args, $assoc_args ): void {
		if ( empty( $args[0] ) ) {
			WP_CLI::error( "Please provide a regex pattern." );
		}
		$pattern = $args[0];

		$result = $this->api->purge_by_regex( $pattern );

		if ( is_wp_error( $result ) ) {
			WP_CLI::error( "Regex purge failed: " . $result->get_error_message() );
		} else {
			WP_CLI::success( "Successfully purged keys matching pattern: " . $pattern );
		}
	}
}
