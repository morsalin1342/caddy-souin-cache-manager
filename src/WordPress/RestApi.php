<?php

namespace morsalin1342\CaddySouinCacheManager\WordPress;

use morsalin1342\CaddySouinCacheManager\Souin\Api;
use morsalin1342\CaddySouinCacheManager\Souin\RedisClient;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Class RestApi
 *
 * Handles the registration and callbacks for the plugin's WordPress REST API endpoints.
 * This class serves as the bridge between the Vue.js frontend and the PHP backend.
 */
class RestApi {
	private Api $api;
	private string $namespace = 'cscm/v1';

	public function __construct( Api $api ) {
		$this->api = $api;
	}

	/**
	 * Adds the WordPress action to register the REST API routes.
	 */
	public function add_hooks(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Registers all the REST API routes for the plugin.
	 */
	public function register_routes(): void {
		// Endpoint to get all cache keys from the Souin API.
		register_rest_route( $this->namespace, '/keys', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_keys' ),
			'permission_callback' => array( $this, 'permission_check' ),
		) );

		// Endpoint to search for keys directly in Redis.
		register_rest_route( $this->namespace, '/redis/search', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'search_redis_keys' ),
			'permission_callback' => array( $this, 'permission_check' ),
		) );

		// Endpoint to handle all cache purging actions.
		register_rest_route( $this->namespace, '/purge', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'handle_purge' ),
			'permission_callback' => array( $this, 'permission_check' ),
		) );

		// Endpoints for getting and updating plugin settings.
		register_rest_route( $this->namespace, '/settings', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_settings' ),
				'permission_callback' => array( $this, 'permission_check' ),
			),
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'update_settings' ),
				'permission_callback' => array( $this, 'permission_check' ),
			),
		) );

		// Endpoint to test the connection to the Souin API.
		register_rest_route( $this->namespace, '/test-connection', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'test_connection' ),
			'permission_callback' => array( $this, 'permission_check' ),
		) );

		// Endpoint to test the direct connection to Redis.
		register_rest_route( $this->namespace, '/redis/test-connection', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'test_redis_connection' ),
			'permission_callback' => array( $this, 'permission_check' ),
		) );
	}

	/**
	 * Permission check for all REST API endpoints.
	 *
	 * @return bool True if the user has the 'manage_options' capability, false otherwise.
	 */
	public function permission_check(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Retrieves all cache keys from the Souin API.
	 *
	 * @return WP_REST_Response|WP_Error A response containing the cache keys or a WP_Error on failure.
	 */
	public function get_keys(): WP_REST_Response|WP_Error {
		$keys = $this->api->get_keys();

		if ( is_wp_error( $keys ) ) {
			return $keys;
		}

		return new WP_REST_Response( $keys, 200 );
	}

	/**
	 * Searches for keys in Redis using a given pattern.
	 *
	 * @param WP_REST_Request $request The REST API request object.
	 *
	 * @return WP_REST_Response|WP_Error A response containing the found keys or a WP_Error on failure.
	 */
	public function search_redis_keys( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$pattern = $request->get_param( 'pattern' ) ?? '*';
		$options = get_option( 'cscm_settings', [] );

		if ( empty( $options['redis_enabled'] ) ) {
			return new WP_Error( 'redis_disabled', 'Redis is not enabled in settings.', array( 'status' => 400 ) );
		}

		$redis = new RedisClient( [
			'host'     => $options['redis_host'] ?? '127.0.0.1',
			'port'     => $options['redis_port'] ?? 6379,
			'password' => $options['redis_password'] ?? null,
			'database' => $options['redis_db'] ?? 0,
		] );

		$keys = $redis->search_keys( $pattern );

		if ( is_wp_error( $keys ) ) {
			return $keys;
		}

		return new WP_REST_Response( $keys, 200 );
	}

	/**
	 * Handles various cache purging operations based on the request payload.
	 *
	 * @param WP_REST_Request $request The REST API request object.
	 *
	 * @return WP_REST_Response|WP_Error A success response or a WP_Error on failure.
	 */
	public function handle_purge( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$params = $request->get_json_params();
		$type   = $params['type'] ?? 'all';

		$result = null;

		switch ( $type ) {
			case 'all':
				$result = $this->api->purge_all();
				break;
			case 'keys':
				$keys = $params['keys'] ?? [];
				if ( empty( $keys ) ) {
					return new WP_Error( 'no_keys_provided', 'No keys provided for purging.', array( 'status' => 400 ) );
				}
				$errors = [];
				foreach ( $keys as $key ) {
					$purge_result = $this->api->purge_by_key( $key );
					if ( is_wp_error( $purge_result ) ) {
						$errors[] = $purge_result->get_error_message();
					}
				}
				if ( ! empty( $errors ) ) {
					return new WP_Error( 'purge_failed', implode( ', ', $errors ), array( 'status' => 500 ) );
				}
				$result = true;
				break;
			case 'regex':
				$regex = $params['regex'] ?? '';
				if ( empty( $regex ) ) {
					return new WP_Error( 'no_regex_provided', 'No regex pattern provided.', array( 'status' => 400 ) );
				}
				$result = $this->api->purge_by_regex( $regex );
				break;
		}


		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( array( 'success' => true ), 200 );
	}

	/**
	 * Retrieves the plugin's settings, merging defaults with saved options.
	 *
	 * @return WP_REST_Response A response containing the plugin settings.
	 */
	public function get_settings(): WP_REST_Response {
		$options = get_option( 'cscm_settings', [] );
		$defaults = [
			'api_url'        => 'http://127.0.0.1:2019/souin-api/souin',
			'auto_purge'     => '1',
			'redis_enabled'  => '0',
			'redis_host'     => '127.0.0.1',
			'redis_port'     => '6379',
			'redis_password' => '',
			'redis_db'       => '0',
		];

		$settings = array_merge( $defaults, $options );
		return new WP_REST_Response( $settings, 200 );
	}

	/**
	 * Updates the plugin's settings.
	 *
	 * @param WP_REST_Request $request The REST API request object.
	 *
	 * @return WP_REST_Response A success response containing the updated settings.
	 */
	public function update_settings( WP_REST_Request $request ): WP_REST_Response {
		$params = $request->get_json_params();
		$options = get_option( 'cscm_settings', [] );

		// Sanitize and update each setting.
		if ( isset( $params['api_url'] ) ) {
			$options['api_url'] = esc_url_raw( $params['api_url'] );
		}

		if ( isset( $params['auto_purge'] ) ) {
			$options['auto_purge'] = $params['auto_purge'] ? '1' : '0';
		}

		$options['redis_enabled']  = ! empty( $params['redis_enabled'] ) ? '1' : '0';
		$options['redis_host']     = sanitize_text_field( $params['redis_host'] ?? '127.0.0.1' );
		$options['redis_port']     = intval( $params['redis_port'] ?? 6379 );
		$options['redis_password'] = sanitize_text_field( $params['redis_password'] ?? '' );
		$options['redis_db']       = intval( $params['redis_db'] ?? 0 );

		update_option( 'cscm_settings', $options );

		return new WP_REST_Response( array( 'success' => true, 'settings' => $options ), 200 );
	}

	/**
	 * Tests the connection to the Souin API using a provided URL.
	 *
	 * @param WP_REST_Request $request The REST API request object.
	 *
	 * @return WP_REST_Response|WP_Error A success response or a WP_Error on failure.
	 */
	public function test_connection( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$params = $request->get_json_params();
		$url = $params['api_url'] ?? '';

		if ( empty( $url ) ) {
			return new WP_Error( 'no_url', 'No API URL provided.', array( 'status' => 400 ) );
		}

		// Use a temporary Api instance to test the connection without saving the URL.
		$temp_api = new Api( $url );
		$result = $temp_api->get_keys();

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( array( 'success' => true, 'message' => 'Connection successful! Found ' . count($result) . ' keys.' ), 200 );
	}

	/**
	 * Tests the direct connection to Redis using provided credentials.
	 *
	 * @param WP_REST_Request $request The REST API request object.
	 *
	 * @return WP_REST_Response|WP_Error A success response or a WP_Error on failure.
	 */
	public function test_redis_connection( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$params = $request->get_json_params();

		// Use a temporary RedisClient instance to test the connection.
		$redis = new RedisClient( [
			'host'     => $params['redis_host'] ?? '127.0.0.1',
			'port'     => $params['redis_port'] ?? 6379,
			'password' => $params['redis_password'] ?? null,
			'database' => $params['redis_db'] ?? 0,
		] );

		$result = $redis->test_connection();

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( array( 'success' => true, 'message' => 'Redis connection successful!' ), 200 );
	}
}
