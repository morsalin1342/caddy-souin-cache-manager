<?php

namespace morsalin1342\CaddySouinCacheManager\Souin;

use Predis\Client;
use Exception;
use WP_Error;

class RedisClient {
	private ?Client $client = null;
	private array $config;

	public function __construct( array $config ) {
		$this->config = array_merge( [
			'host'     => '127.0.0.1',
			'port'     => 6379,
			'password' => null,
			'database' => 0,
		], $config );
	}

	private function get_client(): Client {
		if ( $this->client === null ) {
			$parameters = [
				'host' => $this->config['host'],
				'port' => $this->config['port'],
			];

			if ( ! empty( $this->config['password'] ) ) {
				$parameters['password'] = $this->config['password'];
			}

			if ( isset( $this->config['database'] ) ) {
				$parameters['database'] = $this->config['database'];
			}

			$this->client = new Client( $parameters );
		}

		return $this->client;
	}

	public function test_connection(): bool|WP_Error {
		try {
			$client = $this->get_client();
			$client->connect();
			$client->ping();

			return true;
		} catch ( Exception $e ) {
			return new WP_Error( 'redis_connection_failed', $e->getMessage() );
		}
	}

	public function search_keys( string $pattern = '*', int $limit = 100 ): array|WP_Error {
		try {
			$client = $this->get_client();
			$keys   = [];
			$cursor = '0';

			// Ensure pattern has wildcards if it's a simple search
			if ( $pattern !== '*' && strpos( $pattern, '*' ) === false ) {
				$pattern = '*' . $pattern . '*';
			}

			do {
				$result = $client->scan( $cursor, [ 'MATCH' => $pattern, 'COUNT' => 100 ] );
				$cursor = $result[0];
				$keys   = array_merge( $keys, $result[1] );

				if ( count( $keys ) >= $limit ) {
					$keys = array_slice( $keys, 0, $limit );
					break;
				}
			} while ( $cursor !== '0' );

			sort( $keys );

			return $keys;
		} catch ( Exception $e ) {
			return new WP_Error( 'redis_search_failed', $e->getMessage() );
		}
	}
}
