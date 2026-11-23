<?php

namespace Tests;

use Brain\Monkey\Functions;
use morsalin1342\CaddySouinCacheManager\Souin\Api;
use morsalin1342\CaddySouinCacheManager\WordPress\Hooks;
use WP_Post;

/**
 * Drives Hooks through the same action sequence WordPress core fires for
 * each change, with a fake post store, and records what gets purged.
 */
class HooksTest extends TestCase {
	private const HOME = 'https://example.com';

	/** @var array<int, WP_Post> */
	private array $posts = array();

	private array $options = array( 'auto_purge' => '1' );

	/** @var string[] */
	private array $purged = array();

	private Hooks $hooks;

	protected function setUp(): void {
		parent::setUp();

		$this->posts   = array();
		$this->purged  = array();
		$this->options = array( 'auto_purge' => '1' );

		Functions\when( 'get_option' )->alias( function ( $name, $default = false ) {
			return match ( $name ) {
				'cscm_settings'  => $this->options,
				'show_on_front'  => 'posts',
				'page_for_posts' => 0,
				default          => $default,
			};
		} );
		Functions\when( 'get_post' )->alias( function ( $post ) {
			$id = $post instanceof WP_Post ? $post->ID : (int) $post;

			return isset( $this->posts[ $id ] ) ? clone $this->posts[ $id ] : null;
		} );
		Functions\when( 'wp_is_post_revision' )->justReturn( false );
		Functions\when( 'wp_is_post_autosave' )->justReturn( false );
		Functions\when( 'is_post_type_viewable' )->alias( static fn( $type ) => in_array( $type, array( 'post', 'page', 'product' ), true ) );
		Functions\when( 'home_url' )->alias( static fn( $path = '' ) => self::HOME . $path );
		Functions\when( 'wp_make_link_relative' )->alias( static fn( $url ) => preg_replace( '#^https?://[^/]+#', '', $url ) );
		// Like core: only a published post has a pretty permalink.
		Functions\when( 'get_permalink' )->alias( function ( $post ) {
			$post = $post instanceof WP_Post ? $post : ( $this->posts[ $post ] ?? null );
			if ( ! $post ) {
				return false;
			}

			return $post->post_status === 'publish'
				? self::HOME . '/' . $post->post_name . '/'
				: self::HOME . '/?p=' . $post->ID;
		} );
		Functions\when( 'get_feed_link' )->justReturn( self::HOME . '/feed/' );
		Functions\when( 'get_post_type_archive_link' )->alias( static fn( $type ) => $type === 'product' ? self::HOME . '/shop/' : false );
		Functions\when( 'get_object_taxonomies' )->alias( static fn( $type ) => $type === 'post'
			? array( (object) array( 'name' => 'category', 'public' => true ), (object) array( 'name' => 'internal', 'public' => false ) )
			: array() );
		Functions\when( 'get_the_terms' )->alias( static fn( $post, $taxonomy ) => $taxonomy === 'category'
			? array( (object) array( 'slug' => 'news' ) )
			: array( (object) array( 'slug' => 'secret' ) ) );
		Functions\when( 'get_term_link' )->alias( static fn( $term ) => self::HOME . '/category/' . $term->slug . '/' );
		Functions\when( 'post_type_supports' )->alias( static fn( $type, $feature ) => $type === 'post' && $feature === 'author' );
		Functions\when( 'get_author_posts_url' )->alias( static fn( $id ) => self::HOME . '/author/user' . $id . '/' );

		$api = \Mockery::mock( Api::class );
		$api->shouldReceive( 'purge_page' )->andReturnUsing( function ( $path ) {
			$this->purged[] = "page $path";
		} );
		$api->shouldReceive( 'purge_path_prefix' )->andReturnUsing( function ( $path ) {
			$this->purged[] = "prefix $path";
		} );

		$this->hooks = new Hooks( $api );
	}

	private function add_post( array $fields ): WP_Post {
		$post                       = new WP_Post( $fields + array( 'ID' => 7, 'post_name' => 'hello-world', 'post_author' => 3 ) );
		$this->posts[ $post->ID ]   = $post;

		return $post;
	}

	/**
	 * What wp_update_post() → wp_insert_post() fires for a status change.
	 */
	private function wp_update_status( int $id, string $new_status, ?string $new_slug = null ): void {
		$this->hooks->before_post_update( $id );                  // pre_post_update

		$old                         = $this->posts[ $id ]->post_status;
		$this->posts[ $id ]->post_status = $new_status;
		if ( $new_slug !== null ) {
			$this->posts[ $id ]->post_name = $new_slug;
		}
		if ( $new_status === 'trash' ) {
			$this->posts[ $id ]->post_name .= '__trashed';
		}

		$this->hooks->on_status_transition( $new_status, $old, clone $this->posts[ $id ] );
	}

	private function wp_trash_post( int $id ): void {
		$this->hooks->before_post_removed( $id );                 // wp_trash_post
		$this->wp_update_status( $id, 'trash' );
	}

	private function wp_delete_post( int $id ): void {
		$this->hooks->before_post_removed( $id );                 // before_delete_post
		unset( $this->posts[ $id ] );                             // gone by deleted_post
	}

	private function shutdown(): array {
		$this->hooks->flush();
		sort( $this->purged );

		return $this->purged;
	}

	private const POST_URLS = array(
		'page /',
		'prefix /author/user3/',
		'prefix /category/news/',
		'prefix /feed/',
		'prefix /hello-world/',
		'prefix /page/',
	);

	public function test_registers_the_hooks(): void {
		$this->hooks->add_hooks();

		$this->assertNotFalse( has_action( 'pre_post_update', array( $this->hooks, 'before_post_update' ) ) );
		$this->assertNotFalse( has_action( 'transition_post_status', array( $this->hooks, 'on_status_transition' ) ) );
		$this->assertNotFalse( has_action( 'wp_trash_post', array( $this->hooks, 'before_post_removed' ) ) );
		$this->assertNotFalse( has_action( 'before_delete_post', array( $this->hooks, 'before_post_removed' ) ) );
		$this->assertNotFalse( has_action( 'wp_update_comment_count', array( $this->hooks, 'auto_purge_on_comment' ) ) );
		$this->assertFalse( has_action( 'deleted_post' ), 'the permalink is already gone by deleted_post' );
	}

	public function test_updating_a_published_post_purges_it_once(): void {
		$this->add_post( array( 'post_status' => 'publish' ) );
		$this->wp_update_status( 7, 'publish' );
		$this->hooks->auto_purge_on_comment( 7 );

		$this->assertSame( self::POST_URLS, $this->shutdown() );
		$this->assertNotFalse( has_action( 'shutdown', array( $this->hooks, 'flush' ) ) );
	}

	/**
	 * @dataProvider unpublished_statuses
	 */
	public function test_unpublishing_purges_the_public_url( string $status ): void {
		$this->add_post( array( 'post_status' => 'publish' ) );
		$this->wp_update_status( 7, $status );

		$purged = $this->shutdown();
		$this->assertSame( self::POST_URLS, $purged );
		$this->assertNotContains( 'page /?p=7', $purged );
	}

	public static function unpublished_statuses(): array {
		return array( array( 'draft' ), array( 'private' ), array( 'pending' ), array( 'future' ) );
	}

	public function test_unpublishing_without_pre_post_update_still_finds_the_public_url(): void {
		// For example a plugin calling wp_transition_post_status() directly.
		$this->add_post( array( 'post_status' => 'draft' ) );
		$this->hooks->on_status_transition( 'draft', 'publish', clone $this->posts[7] );

		$this->assertSame( self::POST_URLS, $this->shutdown() );
	}

	public function test_trashing_a_published_post_purges_its_url_not_the_trashed_one(): void {
		$this->add_post( array( 'post_status' => 'publish' ) );
		$this->wp_trash_post( 7 );

		$purged = $this->shutdown();
		$this->assertSame( self::POST_URLS, $purged );
		$this->assertNotContains( 'prefix /hello-world__trashed/', $purged );
	}

	public function test_deleting_a_published_post_purges_its_url(): void {
		$this->add_post( array( 'post_status' => 'publish' ) );
		$this->wp_delete_post( 7 );

		$this->assertSame( self::POST_URLS, $this->shutdown() );
	}

	public function test_deleting_from_the_trash_purges_the_original_url(): void {
		$this->add_post( array( 'post_status' => 'trash', 'post_name' => 'hello-world__trashed' ) );
		$this->wp_delete_post( 7 );

		$this->assertSame( self::POST_URLS, $this->shutdown() );
	}

	public function test_changing_the_slug_purges_old_and_new_urls(): void {
		$this->add_post( array( 'post_status' => 'publish' ) );
		$this->wp_update_status( 7, 'publish', 'new-slug' );

		$purged = $this->shutdown();
		$this->assertContains( 'prefix /hello-world/', $purged );
		$this->assertContains( 'prefix /new-slug/', $purged );
	}

	public function test_publishing_a_draft_purges_the_new_url(): void {
		$this->add_post( array( 'post_status' => 'draft' ) );
		$this->wp_update_status( 7, 'publish' );

		$this->assertSame( self::POST_URLS, $this->shutdown() );
	}

	public function test_changes_that_never_touch_publish_purge_nothing(): void {
		$this->add_post( array( 'post_status' => 'draft' ) );
		$this->wp_update_status( 7, 'pending' );
		$this->wp_trash_post( 7 );
		$this->hooks->auto_purge_on_comment( 7 );

		$this->assertSame( array(), $this->shutdown() );
		$this->assertFalse( has_action( 'shutdown' ) );
	}

	public function test_nothing_is_purged_when_auto_purge_is_off(): void {
		$this->options = array( 'auto_purge' => '0' );
		$this->add_post( array( 'post_status' => 'publish' ) );
		$this->wp_trash_post( 7 );

		$this->assertSame( array(), $this->shutdown() );
	}

	public function test_post_types_without_public_urls_are_ignored(): void {
		$this->add_post( array( 'post_status' => 'publish', 'post_type' => 'wp_global_styles' ) );
		$this->wp_update_status( 7, 'draft' );

		$this->assertSame( array(), $this->shutdown() );
	}

	public function test_revisions_are_ignored(): void {
		Functions\when( 'wp_is_post_revision' )->justReturn( 6 );
		$this->add_post( array( 'post_status' => 'publish' ) );
		$this->wp_update_status( 7, 'publish' );

		$this->assertSame( array(), $this->shutdown() );
	}

	public function test_other_post_types_purge_their_archive(): void {
		$this->add_post( array( 'post_status' => 'publish', 'post_type' => 'product', 'post_name' => 'mug' ) );
		$this->wp_update_status( 7, 'draft' );

		$this->assertSame( array( 'page /', 'prefix /mug/', 'prefix /shop/' ), $this->shutdown() );
	}

	public function test_the_front_page_is_never_purged_as_a_prefix(): void {
		// A static front page's permalink is the home URL itself.
		Functions\when( 'get_permalink' )->justReturn( self::HOME . '/' );
		$this->add_post( array( 'post_status' => 'publish', 'post_type' => 'page' ) );
		$this->wp_update_status( 7, 'publish' );

		$this->assertSame( array( 'page /' ), $this->shutdown() );
	}

	public function test_flush_sends_each_purge_once(): void {
		$this->add_post( array( 'post_status' => 'publish' ) );
		$this->wp_update_status( 7, 'publish' );
		$this->shutdown();
		$this->purged = array();

		$this->assertSame( array(), $this->shutdown(), 'the queue is emptied' );
	}
}
