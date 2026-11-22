<?php

namespace Tests;

/**
 * The version is written in several files; they must agree, and the
 * changelog must have an entry for it.
 */
class VersionTest extends \PHPUnit\Framework\TestCase {
	private static function root(): string {
		return dirname( __DIR__, 2 );
	}

	private static function match( string $file, string $regex ): string {
		$contents = file_get_contents( self::root() . '/' . $file );
		self::assertSame( 1, preg_match( $regex, $contents, $m ), "No version found in $file" );

		return $m[1];
	}

	public function test_every_file_has_the_same_version(): void {
		$header = self::match( 'caddy-souin-cache-manager.php', '/^\s*\*\s*Version:\s*(\S+)/m' );

		$this->assertMatchesRegularExpression( '/^\d+\.\d+\.\d+$/', $header );
		$this->assertSame( $header, self::match( 'src/CaddySouinCacheManager.php', "/\\\$this->version\s*=\s*'([^']+)'/" ), 'CaddySouinCacheManager::$version' );
		$this->assertSame( $header, json_decode( file_get_contents( self::root() . '/composer.json' ), true )['version'], 'composer.json' );
		$this->assertSame( $header, json_decode( file_get_contents( self::root() . '/package.json' ), true )['version'], 'package.json' );
		$this->assertSame( $header, json_decode( file_get_contents( self::root() . '/package-lock.json' ), true )['version'], 'package-lock.json' );
		$this->assertSame( $header, self::match( 'readme.txt', '/^Stable tag:\s*(\S+)/m' ), 'readme.txt Stable tag' );
		$this->assertSame( $header, self::match( 'changelog.txt', '/^= (\S+) =$/m' ), 'latest changelog.txt entry' );
	}

	public function test_readme_has_at_most_five_tags(): void {
		$tags = array_map( 'trim', explode( ',', self::match( 'readme.txt', '/^Tags:\s*(.+)$/m' ) ) );

		$this->assertLessThanOrEqual( 5, count( $tags ) );
	}
}
