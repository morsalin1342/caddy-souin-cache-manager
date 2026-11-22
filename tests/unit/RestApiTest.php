<?php

namespace Tests;

use Brain\Monkey\Functions;
use morsalin1342\CaddySouinCacheManager\Souin\Api;
use morsalin1342\CaddySouinCacheManager\WordPress\RestApi;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

class RestApiTest extends TestCase {
	private array $stored = array();

	/** @var \Mockery\MockInterface&Api */
	private $api;

	private RestApi $rest;

	protected function setUp(): void {
		parent::setUp();
		$this->stored = array();
		Functions\when( 'get_option' )->alias( fn( $name, $default = false ) => $name === 'cscm_settings' && $this->stored ? $this->stored : $default );
		Functions\when( 'update_option' )->alias( function ( $name, $value ) {
			$this->stored = $value;

			return true;
		} );
		Functions\when( 'esc_url_raw' )->returnArg();
		Functions\when( 'sanitize_text_field' )->alias( static fn( $s ) => trim( strip_tags( (string) $s ) ) );

		$this->api  = \Mockery::mock( Api::class );
		$this->rest = new RestApi( $this->api );
	}

	private function save( array $params ): WP_REST_Response {
		return $this->rest->update_settings( new WP_REST_Request( $params + array( 'api_url' => 'http://caddy:2019/souin-api/souin' ) ) );
	}

	public function test_the_saved_password_is_never_sent_to_the_browser(): void {
		$this->stored = array( 'redis_password' => 's3cret' );

		$data = $this->rest->get_settings()->get_data();

		$this->assertSame( RestApi::PASSWORD_PLACEHOLDER, $data['redis_password'] );
		$this->assertTrue( $data['redis_password_set'] );
		$this->assertStringNotContainsString( 's3cret', json_encode( $data ) );
	}

	public function test_no_placeholder_when_no_password_is_saved(): void {
		$data = $this->rest->get_settings()->get_data();

		$this->assertSame( '', $data['redis_password'] );
		$this->assertFalse( $data['redis_password_set'] );
	}

	public function test_the_password_is_stored_exactly_as_typed(): void {
		$password = ' p@ss <zebra> %20 "q" ';
		$response = $this->save( array( 'redis_password' => $password ) );

		$this->assertSame( $password, $this->stored['redis_password'] );
		$this->assertStringNotContainsString( 'zebra', json_encode( $response->get_data() ), 'not echoed back either' );
	}

	public function test_the_placeholder_keeps_the_saved_password(): void {
		$this->stored = array( 'redis_password' => 's3cret' );
		$this->save( array( 'redis_password' => RestApi::PASSWORD_PLACEHOLDER ) );

		$this->assertSame( 's3cret', $this->stored['redis_password'] );
	}

	public function test_a_missing_password_field_keeps_the_saved_password(): void {
		$this->stored = array( 'redis_password' => 's3cret' );
		$this->save( array( 'redis_host' => 'redis' ) );

		$this->assertSame( 's3cret', $this->stored['redis_password'] );
	}

	public function test_clearing_the_field_removes_the_password(): void {
		$this->stored = array( 'redis_password' => 's3cret' );
		$this->save( array( 'redis_password' => '' ) );

		$this->assertSame( '', $this->stored['redis_password'] );
	}

	public function test_a_non_string_password_is_stored_as_a_string(): void {
		$this->save( array( 'redis_password' => 1234 ) );
		$this->assertSame( '1234', $this->stored['redis_password'] );

		$this->save( array( 'redis_password' => array( 'x' ) ) );
		$this->assertSame( '', $this->stored['redis_password'] );
	}
}
