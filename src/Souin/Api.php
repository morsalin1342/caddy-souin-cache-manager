<?php

namespace morsalin1342\CaddySouinCacheManager\Souin;

use WP_Error;

/**
 * Thin client for the Souin HTTP API.
 *
 * How purge patterns travel to Souin
 * ----------------------------------
 * Souin takes the purge pattern from the raw request URI and never
 * percent-decodes it. The WordPress HTTP API, on the other hand,
 * percent-encodes characters such as `\ [ ] { } | ^` and spaces, and drops
 * everything after a `#`. A pattern containing any of those therefore
 * arrives at Souin as `%5C`, `%7B` … and silently matches nothing.
 * Encoding the pattern with rawurlencode() fails the same way.
 *
 * So every pattern this class sends is written in a small alphabet that
 * passes through unchanged: letters, digits, `- _ ~ / ! & ' , ; = : @`
 * (all literal in a regex) plus the operators `. * + ? ( ) $`. A literal
 * character outside that alphabet (`.`, `?`, `{`, `%` …) is sent as `.`,
 * which matches that character and, at worst, one other character in the
 * same position. That can widen a purge by a character; it can never
 * make one miss.
 *
 * Souin matches a pattern anywhere in a key (it is not anchored) and
 * ignores a pattern it cannot compile while still answering 204.
 */
class Api {
	/** Characters that are literal in a regex and reach Souin unchanged. */
	private const SAFE_LITERAL = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_~/!&\',;=:@';

	/** Regex operators that reach Souin unchanged. */
	private const SAFE_OPERATORS = '.*+?()$';

	/** Suffix Souin adds to the key of a response stored per Vary header. */
	private const VARY_SUFFIX = '(.-VARY-.*)?$';

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

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'invalid_response', 'The Souin API answered with status ' . $code . '.' );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'invalid_response', 'Could not decode cache keys from API response.' );
		}

		sort( $data );

		return $data;
	}

	public function purge_all(): bool|WP_Error {
		return $this->send_purge( 'flush', 15, 'Purge All failed with status: ' );
	}

	/**
	 * Purges one cache key, as listed by get_keys().
	 */
	public function purge_by_key( string $key ): bool|WP_Error {
		$pattern = self::key_pattern( $key );
		if ( $pattern === '' ) {
			return new WP_Error( 'invalid_key', 'The cache key is empty.' );
		}

		return $this->send_purge( $pattern, 15, 'Purge failed with status: ' );
	}

	/**
	 * Pattern matching one listed key. The listing shows a response stored
	 * per Vary header as "<key>{-VARY-}<headers>", but Souin matches purge
	 * patterns against "<key>" itself, and purging it removes every variant.
	 */
	public static function key_pattern( string $key ): string {
		$base = explode( '{-VARY-}', $key, 2 )[0];

		return $base === '' ? '' : self::literal_pattern( $base ) . self::VARY_SUFFIX;
	}

	/**
	 * Purges every key matching a user supplied regular expression.
	 */
	public function purge_by_regex( string $regex ): bool|WP_Error {
		$pattern = self::wire_regex( $regex );
		if ( is_wp_error( $pattern ) ) {
			return $pattern;
		}

		return $this->send_purge( $pattern, 15, 'Regex Purge failed with status: ' );
	}

	/**
	 * Purges the cached copies of one page (any method, scheme, host, Vary
	 * variant), without touching other URLs that share the prefix.
	 * Fire and forget: used by the automatic purge.
	 *
	 * @param string $path Site-relative path, for example "/" or "/?p=12".
	 */
	public function purge_page( string $path ): void {
		$this->send_purge_async( self::page_pattern( $path ) );
	}

	/**
	 * Purges every cached URL whose path starts with $path, for example a
	 * post together with its feed, pages and query string variants.
	 * Fire and forget: used by the automatic purge.
	 *
	 * @param string $path Site-relative path, for example "/hello-world/".
	 */
	public function purge_path_prefix( string $path ): void {
		$this->send_purge_async( self::prefix_pattern( $path ) );
	}

	/**
	 * Pattern matching a single page. Souin keys look like
	 * "GET-https-example.com-/path?query", optionally followed by
	 * "{-VARY-}…"; the "-" before the path stops "/a/" from matching "/b/a/".
	 */
	public static function page_pattern( string $path ): string {
		return '-' . self::literal_pattern( $path ) . self::VARY_SUFFIX;
	}

	/**
	 * Pattern matching every key whose path starts with $path.
	 */
	public static function prefix_pattern( string $path ): string {
		return '-' . self::literal_pattern( $path );
	}

	/**
	 * Turns a literal string into a pattern made only of characters that
	 * reach Souin unchanged (see the class comment).
	 */
	public static function literal_pattern( string $literal ): string {
		$out = '';
		foreach ( self::characters( $literal ) as $char ) {
			$out .= ( strlen( $char ) === 1 && str_contains( self::SAFE_LITERAL, $char ) ) ? $char : '.';
		}

		return $out;
	}

	/**
	 * Rewrites a user supplied regular expression into the alphabet that
	 * reaches Souin unchanged, or explains why it cannot be sent.
	 *
	 * - "\/", "\-" and other escaped safe characters become the character.
	 * - Escaped operators such as "\." or "\?" become "." (one character).
	 * - A leading "^" is dropped (Souin matches anywhere in the key anyway).
	 * - Literal characters that cannot travel ("#", "%", spaces …) become ".".
	 * - Character classes, "{n}", "|" and escapes like "\d" cannot be sent.
	 */
	public static function wire_regex( string $regex ): string|WP_Error {
		$chars = self::characters( $regex );
		$count = count( $chars );
		$out   = '';

		if ( $count === 0 ) {
			return new WP_Error( 'invalid_regex', 'The regex pattern is empty.' );
		}

		for ( $i = 0; $i < $count; $i++ ) {
			$char = $chars[ $i ];

			if ( $char === '\\' ) {
				$next = $chars[ ++$i ] ?? '';
				if ( $next === '' ) {
					return self::regex_error( 'it ends with a lone "\\"' );
				}
				if ( str_contains( self::SAFE_LITERAL, $next ) && ! ctype_alnum( $next ) ) {
					$out .= $next;
				} elseif ( strlen( $next ) === 1 && ctype_alnum( $next ) ) {
					return self::regex_error( 'escapes such as "\\' . $next . '" are not supported; use "." or spell the characters out' );
				} else {
					$out .= '.';
				}
				continue;
			}

			if ( $char === '^' && $i === 0 ) {
				continue;
			}

			if ( str_contains( '[]{}|^', $char ) ) {
				return self::regex_error( '"' . $char . '" cannot be sent to the Souin API (it would arrive percent-encoded); use "." to match any one character' );
			}

			if ( strlen( $char ) === 1 && ( str_contains( self::SAFE_LITERAL, $char ) || str_contains( self::SAFE_OPERATORS, $char ) ) ) {
				$out .= $char;
			} else {
				$out .= '.';
			}
		}

		if ( $out === '' ) {
			return new WP_Error( 'invalid_regex', 'The regex pattern is empty.' );
		}

		// Souin ignores a pattern it cannot compile but still reports success.
		if ( @preg_match( '#' . $out . '#', '' ) === false ) {
			return new WP_Error( 'invalid_regex', 'The regex pattern is not valid: ' . $regex );
		}

		return $out;
	}

	public function build_url( string $pattern ): string {
		return $this->api_url . self::avoid_path_cleaning( '/' . $pattern );
	}

	/**
	 * Caddy's admin server redirects a path with "//", "/./" or "/../" to a
	 * cleaned one, which would change the pattern. Wrap those parts in a
	 * group, which matches the same keys: "//" → "/(/)", "/./" → "/(.)/".
	 * Only the part before "?" is a path.
	 */
	public static function avoid_path_cleaning( string $url_path ): string {
		$split = strpos( $url_path, '?' );
		$path  = $split === false ? $url_path : substr( $url_path, 0, $split );
		$query = $split === false ? '' : substr( $url_path, $split );

		while ( str_contains( $path, '//' ) ) {
			$path = str_replace( '//', '/(/)', $path );
		}
		$path = preg_replace( '#(?<=/)(\.\.?)(?=/|$)#', '($1)', $path );

		return $path . $query;
	}

	private function send_purge( string $pattern, int $timeout, string $error_prefix ): bool|WP_Error {
		$response = wp_remote_request( $this->build_url( $pattern ), array(
			'method'  => 'PURGE',
			'timeout' => $timeout,
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code >= 200 && $code < 300 ) {
			return true;
		}

		return new WP_Error( 'purge_failed', $error_prefix . $code );
	}

	private function send_purge_async( string $pattern ): void {
		wp_remote_request( $this->build_url( $pattern ), array(
			'method'   => 'PURGE',
			'timeout'  => 5,
			'blocking' => false,
		) );
	}

	private static function regex_error( string $reason ): WP_Error {
		return new WP_Error( 'invalid_regex', 'This regex pattern cannot be used: ' . $reason . '.', array( 'status' => 400 ) );
	}

	/**
	 * Splits a string into UTF-8 characters (bytes, if it is not valid UTF-8).
	 *
	 * @return string[]
	 */
	private static function characters( string $value ): array {
		$chars = preg_split( '//u', $value, -1, PREG_SPLIT_NO_EMPTY );

		return is_array( $chars ) ? $chars : str_split( $value );
	}
}
