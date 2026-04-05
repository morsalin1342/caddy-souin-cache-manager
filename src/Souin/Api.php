<?php

namespace morsalin1342\CaddySouinCacheManager\Souin;

use WP_Error;

class Api {
	private string $api_url;

	public function __construct( string $api_url ) {
		$this->api_url = untrailingslashit( $api_url );
	}

	public function get_keys(): array|WP_Error {
		$url      = $this->api_url . '/';
		$response = wp_remote_get( $url, array( 'timeout' => 10 ) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'invalid_response', 'Could not decode cache keys from API response.' );
		}

		sort( $data );

		return $data;
	}

	public function purge_all(): bool|WP_Error {
		$url      = $this->api_url . '/flush';
		$response = wp_remote_request( $url, array( 'method' => 'PURGE', 'timeout' => 15 ) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code >= 200 && $code < 300 ) {
			return true;
		}

		return new WP_Error( 'purge_failed', 'Purge All failed with status: ' . $code );
	}

	public function purge_by_key( string $key ): bool|WP_Error {
		$url = $this->api_url . '/' . $key . '$';

		$response = wp_remote_request( $url, array( 'method' => 'PURGE', 'timeout' => 15 ) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code >= 200 && $code < 300 ) {
			return true;
		}

		return new WP_Error( 'purge_failed', "Purge failed with status: $code" );
	}

	public function purge_by_regex( string $regex ): bool|WP_Error {
		$url      = $this->api_url . '/' . $regex;
		$response = wp_remote_request( $url, array( 'method' => 'PURGE', 'timeout' => 15 ) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code >= 200 && $code < 300 ) {
			return true;
		}

		return new WP_Error( 'purge_failed', 'Regex Purge failed with status: ' . $code );
	}

	public function purge_url( $path ): void {
		$regex = '.*' . preg_quote( $path, '/' ) . '.*';
		if ( $path === '/' ) {
			$regex = '.*' . preg_quote( $path, '/' ) . '$';
		}

		$url = $this->api_url . '/' . $regex;

		wp_remote_request( $url, array(
			'method'   => 'PURGE',
			'timeout'  => 5,
			'blocking' => false,
		) );
	}
}
