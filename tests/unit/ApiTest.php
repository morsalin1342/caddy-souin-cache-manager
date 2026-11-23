<?php

namespace Tests;

use Brain\Monkey\Functions;
use morsalin1342\CaddySouinCacheManager\Souin\Api;
use WP_Error;

class ApiTest extends TestCase {
	private const BASE = 'http://caddy:2019/souin-api/souin';

	/** @var array<int, array{0: string, 1: array}> */
	private array $requests = array();

	protected function setUp(): void {
		parent::setUp();
		$this->requests = array();
		Functions\when( 'wp_remote_request' )->alias( function ( $url, $args ) {
			$this->requests[] = array( $url, $args );

			return array( 'response' => array( 'code' => 204 ) );
		} );
		Functions\when( 'wp_remote_retrieve_response_code' )->alias( static fn( $r ) => $r['response']['code'] );
	}

	/**
	 * Characters the WordPress HTTP API would percent-encode or cut off,
	 * which Souin never decodes, must not appear in a purge URL.
	 */
	private function assertTravelsUnchanged( string $url ): void {
		$this->assertDoesNotMatchRegularExpression( '/[\\\\\[\]{}|^#%\s"<>`]/', $url, "URL would be altered in transit: $url" );
		$this->assertMatchesRegularExpression( '/^[\x21-\x7e]+$/', $url, "URL has non-ASCII characters: $url" );
	}

	private function assertSouinWouldMatch( string $pattern, string $key ): void {
		$this->assertSame( 1, preg_match( '#' . $pattern . '#', $key ), "Pattern $pattern should match $key" );
	}

	private function assertSouinWouldNotMatch( string $pattern, string $key ): void {
		$this->assertSame( 0, preg_match( '#' . $pattern . '#', $key ), "Pattern $pattern should not match $key" );
	}

	public function test_literal_pattern_keeps_safe_characters_and_replaces_the_rest_with_dot(): void {
		$this->assertSame( 'GET-https-example.com:443-/a_b~c/', Api::literal_pattern( 'GET-https-example.com:443-/a_b~c/' ) );
		$this->assertSame( '/.s=a.b&c=.', Api::literal_pattern( '/?s=a.b&c=#' ) );
		$this->assertSame( '/caf./', Api::literal_pattern( '/café/' ), 'one dot per character, not per byte' );
		$this->assertSame( '/.....', Api::literal_pattern( '/{}[]|' ) );
	}

	public function test_purge_by_key_matches_only_that_key_and_its_vary_variants(): void {
		$api = new Api( self::BASE . '/' );
		$key = 'GET-https-example.com-/?s=a.b{-VARY-}Accept-Encoding:gzip';

		$this->assertTrue( $api->purge_by_key( $key ) );
		$this->assertCount( 1, $this->requests );
		[ $url, $args ] = $this->requests[0];

		$this->assertSame( 'PURGE', $args['method'] );
		$this->assertSame( self::BASE . '/GET-https-example.com-/.s=a.b(.-VARY-.*)?$', $url );
		$this->assertTravelsUnchanged( $url );

		$pattern = substr( $url, strlen( self::BASE ) + 1 );
		$this->assertSouinWouldMatch( $pattern, 'GET-https-example.com-/?s=a.b' );
		$this->assertSouinWouldMatch( $pattern, 'GET-https-example.com-/?s=a.b{-VARY-}Accept-Encoding:br' );
		$this->assertSouinWouldNotMatch( $pattern, 'GET-https-example.com-/?s=a.bc' );
		$this->assertSouinWouldNotMatch( $pattern, 'GET-https-example.com-/' );
		$this->assertSouinWouldNotMatch( $pattern, 'GET-https-example.com-/?s=a' );
	}

	public function test_purge_by_key_rejects_an_empty_key(): void {
		$api = new Api( self::BASE );

		$this->assertInstanceOf( WP_Error::class, $api->purge_by_key( '' ) );
		$this->assertInstanceOf( WP_Error::class, $api->purge_by_key( '{-VARY-}x' ) );
		$this->assertSame( array(), $this->requests );
	}

	public function test_purge_by_key_reports_a_failed_status(): void {
		Functions\when( 'wp_remote_request' )->justReturn( array( 'response' => array( 'code' => 500 ) ) );
		$result = ( new Api( self::BASE ) )->purge_by_key( 'GET-http-x-/' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertStringContainsString( '500', $result->get_error_message() );
	}

	public function test_page_pattern_matches_the_page_but_not_pages_below_it(): void {
		$home = Api::page_pattern( '/' );
		$this->assertSame( '-/(.-VARY-.*)?$', $home );
		$this->assertSouinWouldMatch( $home, 'GET-https-example.com-/' );
		$this->assertSouinWouldMatch( $home, 'HEAD-https-example.com-/' );
		$this->assertSouinWouldMatch( $home, 'GET-https-example.com-/{-VARY-}Accept-Encoding:' );
		$this->assertSouinWouldNotMatch( $home, 'GET-https-example.com-/hello-world/' );
		$this->assertSouinWouldNotMatch( $home, 'GET-https-example.com-/?s=x' );

		$plain = Api::page_pattern( '/?p=12' );
		$this->assertSouinWouldMatch( $plain, 'GET-https-example.com-/?p=12' );
		$this->assertSouinWouldNotMatch( $plain, 'GET-https-example.com-/?p=123' );
	}

	public function test_prefix_pattern_matches_the_page_and_everything_below_it(): void {
		$pattern = Api::prefix_pattern( '/hello-world/' );

		$this->assertSouinWouldMatch( $pattern, 'GET-https-example.com-/hello-world/' );
		$this->assertSouinWouldMatch( $pattern, 'GET-https-example.com-/hello-world/feed/' );
		$this->assertSouinWouldMatch( $pattern, 'GET-https-example.com-/hello-world/?amp=1' );
		$this->assertSouinWouldNotMatch( $pattern, 'GET-https-example.com-/2024/hello-world/' );
		$this->assertSouinWouldNotMatch( $pattern, 'GET-https-example.com-/hello-world-2/' );
	}

	public function test_automatic_purges_are_fire_and_forget(): void {
		$api = new Api( self::BASE );
		$api->purge_page( '/' );
		$api->purge_path_prefix( '/a.b/' );

		$this->assertSame( self::BASE . '/-/(.-VARY-.*)?$', $this->requests[0][0] );
		$this->assertSame( self::BASE . '/-/a.b/', $this->requests[1][0] );
		foreach ( $this->requests as [ $url, $args ] ) {
			$this->assertFalse( $args['blocking'] );
			$this->assertSame( 'PURGE', $args['method'] );
			$this->assertTravelsUnchanged( $url );
		}
	}

	public function test_purge_all_uses_flush(): void {
		$this->assertTrue( ( new Api( self::BASE ) )->purge_all() );
		$this->assertSame( self::BASE . '/flush', $this->requests[0][0] );
	}

	/**
	 * @dataProvider sendable_regexes
	 */
	public function test_wire_regex_rewrites_patterns_into_characters_that_travel( string $regex, string $expected ): void {
		$this->assertSame( $expected, Api::wire_regex( $regex ) );
	}

	public static function sendable_regexes(): array {
		return array(
			'escaped slashes'        => array( '.*\/product\/cool-widget\/.*', '.*/product/cool-widget/.*' ),
			'escaped dot'            => array( 'example\.com', 'example.com' ),
			'escaped question mark'  => array( '\/\?s=', '/.s=' ),
			'leading caret dropped'  => array( '^GET-', 'GET-' ),
			'operators kept'         => array( '(?i)/blog/(page/)?.+$', '(?i)/blog/(page/)?.+$' ),
			'hash and space to dot'  => array( 'a#b c', 'a.b.c' ),
			'percent to dot'         => array( 'caf%C3%A9', 'caf.C3.A9' ),
			'escaped backslash'      => array( 'a\\\\b', 'a.b' ),
		);
	}

	/**
	 * @dataProvider unsendable_regexes
	 */
	public function test_wire_regex_refuses_patterns_that_cannot_be_sent( string $regex ): void {
		$result = Api::wire_regex( $regex );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_regex', $result->get_error_code() );
	}

	public static function unsendable_regexes(): array {
		return array(
			'character class' => array( '[a-z]+' ),
			'alternation'     => array( 'a|b' ),
			'repetition'      => array( 'a{2}' ),
			'caret inside'    => array( 'a^b' ),
			'class escape'    => array( '\d+' ),
			'trailing slash'  => array( 'abc\\' ),
			'unbalanced'      => array( '(abc' ),
			'empty'           => array( '' ),
			'only a caret'    => array( '^' ),
		);
	}

	public function test_purge_by_regex_sends_the_rewritten_pattern(): void {
		$api = new Api( self::BASE );

		$this->assertTrue( $api->purge_by_regex( '.*\/product\/.*' ) );
		$this->assertSame( self::BASE . '/.*/product/.*', $this->requests[0][0] );

		$this->assertInstanceOf( WP_Error::class, $api->purge_by_regex( '[a-z]' ) );
		$this->assertCount( 1, $this->requests, 'an unsendable pattern is not sent' );
	}

	public function test_urls_avoid_path_cleaning_redirects(): void {
		$api = new Api( self::BASE );

		$this->assertSame( self::BASE . '/(/)product/', $api->build_url( '/product/' ) );
		$this->assertSame( self::BASE . '/a/(/)b', $api->build_url( 'a//b' ) );
		$this->assertSame( self::BASE . '/a/(.)/b/(..)', $api->build_url( 'a/./b/..' ) );
		$this->assertSame( self::BASE . '/a.b/?x=//', $api->build_url( 'a.b/?x=//' ) );
	}

	public function test_get_keys_reports_http_errors(): void {
		Functions\when( 'wp_remote_get' )->justReturn( array( 'response' => array( 'code' => 404 ), 'body' => '[]' ) );
		Functions\when( 'wp_remote_retrieve_body' )->alias( static fn( $r ) => $r['body'] );

		$this->assertInstanceOf( WP_Error::class, ( new Api( self::BASE ) )->get_keys() );
	}

	public function test_get_keys_returns_sorted_keys(): void {
		Functions\when( 'wp_remote_get' )->justReturn( array( 'response' => array( 'code' => 200 ), 'body' => '["b","a"]' ) );
		Functions\when( 'wp_remote_retrieve_body' )->alias( static fn( $r ) => $r['body'] );

		$this->assertSame( array( 'a', 'b' ), ( new Api( self::BASE ) )->get_keys() );
	}
}
