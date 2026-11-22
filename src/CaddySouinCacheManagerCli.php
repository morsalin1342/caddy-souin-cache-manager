<?php

namespace morsalin1342\CaddySouinCacheManager;

use morsalin1342\CaddySouinCacheManager\Souin\Api;
use WP_CLI;

class CaddySouinCacheManagerCli {
	private const DEFAULT_API_URL = 'http://127.0.0.1:2019/souin-api/souin';

	private Api $api;
	private string $api_url;

	public function __construct( ?Api $api = null ) {
		$options       = get_option( 'cscm_settings' );
		$this->api_url = ! empty( $options['api_url'] ) ? $options['api_url'] : self::DEFAULT_API_URL;
		$this->api     = $api ?? new Api( $this->api_url );
	}

	/**
	 * Shows the configuration and tests the connection to the Souin API.
	 *
	 * Exits with status 1 when the Souin API cannot be reached.
	 */
	public function status( $args, $assoc_args ): void {
		$options    = get_option( 'cscm_settings' );
		$auto_purge = ( ! isset( $options['auto_purge'] ) || $options['auto_purge'] == 1 ) ? 'Enabled' : 'Disabled';
		$keys       = $this->api->get_keys();
		$connection = is_wp_error( $keys )
			? 'FAILED: ' . $keys->get_error_message()
			: 'OK, ' . count( $keys ) . ' keys cached';

		WP_CLI\Utils\format_items( 'table', array(
			array( 'Key' => 'Souin API URL', 'Value' => $this->api_url ),
			array( 'Key' => 'Auto Purge', 'Value' => $auto_purge ),
			array( 'Key' => 'API Connection', 'Value' => $connection ),
		), array( 'Key', 'Value' ) );

		if ( is_wp_error( $keys ) ) {
			WP_CLI::error( 'Could not reach the Souin API at ' . $this->api_url . '.' );
		}
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
		$errors        = array();

		foreach ( $args as $key ) {
			$result = $this->api->purge_by_key( $key );
			if ( is_wp_error( $result ) ) {
				$errors[] = $key . ': ' . $result->get_error_message();
			} else {
				$success_count ++;
			}
			$progress->tick();
		}
		$progress->finish();

		$fail_count = count( $errors );
		if ( $fail_count > 0 ) {
			WP_CLI::error( "Purged $success_count, failed $fail_count:\n" . implode( "\n", $errors ) );
		}

		WP_CLI::success( "Bulk purge complete. Succeeded: $success_count, Failed: 0." );
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
