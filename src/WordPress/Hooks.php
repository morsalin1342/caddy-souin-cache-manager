<?php

namespace morsalin1342\CaddySouinCacheManager\WordPress;

use morsalin1342\CaddySouinCacheManager\Souin\Api;
use WP_Post;

/**
 * Automatic purging when content changes.
 *
 * URLs are collected while WordPress handles the request and purged once,
 * on shutdown. Collecting first means a post that is updated, has its
 * terms saved and its comment count changed in one request is purged once,
 * after everything is written, rather than three times part way through.
 *
 * A published post's URLs are captured *before* WordPress changes it
 * (pre_post_update, wp_trash_post, before_delete_post), because afterwards
 * they are gone: a draft's permalink is "?p=123", a trashed post's slug
 * gets "__trashed", and a deleted post has no permalink at all.
 */
class Hooks {
	private Api $api;

	/** @var array<string, string> Path => 'page' (exact) or 'prefix'. */
	private array $queue = array();

	private bool $shutdown_hooked = false;

	public function __construct( Api $api ) {
		$this->api = $api;
	}

	public function add_hooks(): void {
		add_action( 'pre_post_update', array( $this, 'before_post_update' ), 10, 1 );
		add_action( 'transition_post_status', array( $this, 'on_status_transition' ), 10, 3 );
		add_action( 'wp_trash_post', array( $this, 'before_post_removed' ), 10, 1 );
		add_action( 'before_delete_post', array( $this, 'before_post_removed' ), 10, 1 );
		add_action( 'wp_update_comment_count', array( $this, 'auto_purge_on_comment' ), 10, 1 );
	}

	public function is_auto_purge_enabled(): bool {
		$options = get_option( 'cscm_settings' );

		return ! isset( $options['auto_purge'] ) || $options['auto_purge'] == 1;
	}

	/**
	 * Before an existing post is updated: if it is published now, remember
	 * its current URLs. Covers unpublishing and slug changes.
	 */
	public function before_post_update( $post_id ): void {
		$post = $this->get_purgeable_post( $post_id );
		if ( $post && $post->post_status === 'publish' ) {
			$this->queue_post( $post );
		}
	}

	/**
	 * Any status change into or out of "publish", including an update that
	 * keeps a post published.
	 */
	public function on_status_transition( $new_status, $old_status, $post ): void {
		if ( $new_status !== 'publish' && $old_status !== 'publish' ) {
			return;
		}

		$post = $this->get_purgeable_post( $post );
		if ( ! $post ) {
			return;
		}

		// Leaving "publish": the post's own URL is no longer its public one,
		// so work out what it was. Usually pre_post_update already did.
		$this->queue_post( $new_status === 'publish' ? $post : $this->as_published( $post ) );
	}

	/**
	 * Before a post is trashed or deleted, while its URLs still exist.
	 */
	public function before_post_removed( $post_id ): void {
		$post = $this->get_purgeable_post( $post_id );
		if ( ! $post ) {
			return;
		}

		if ( $post->post_status === 'publish' ) {
			$this->queue_post( $post );
		} elseif ( $post->post_status === 'trash' ) {
			// Deleting from the trash: purge again in case the copy was cached
			// before it was trashed (for example by an older version).
			$this->queue_post( $this->as_published( $post ) );
		}
	}

	public function auto_purge_on_comment( $post_id ): void {
		$post = $this->get_purgeable_post( $post_id );
		if ( $post && $post->post_status === 'publish' ) {
			$this->queue_post( $post );
		}
	}

	/**
	 * Sends the collected purges. Runs on shutdown.
	 */
	public function flush(): void {
		$queue       = $this->queue;
		$this->queue = array();

		foreach ( $queue as $path => $mode ) {
			if ( $mode === 'prefix' ) {
				$this->api->purge_path_prefix( $path );
			} else {
				$this->api->purge_page( $path );
			}
		}
	}

	/**
	 * @return array<string, string> The purges waiting for shutdown.
	 */
	public function get_queue(): array {
		return $this->queue;
	}

	private function get_purgeable_post( $post ): ?WP_Post {
		if ( ! $this->is_auto_purge_enabled() ) {
			return null;
		}

		$post = get_post( $post );
		if ( ! $post instanceof WP_Post ) {
			return null;
		}

		if ( wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) ) {
			return null;
		}

		if ( ! is_post_type_viewable( $post->post_type ) ) {
			return null;
		}

		return $post;
	}

	/**
	 * A copy of the post as it looked while published, so get_permalink()
	 * returns its public URL rather than "?p=123" or "slug__trashed".
	 */
	private function as_published( WP_Post $post ): WP_Post {
		$copy              = clone $post;
		$copy->post_status = 'publish';
		$copy->post_name   = preg_replace( '/__trashed(-\d+)?$/', '', (string) $copy->post_name );

		return $copy;
	}

	/**
	 * Queues the post's own URL and the pages that list it.
	 */
	private function queue_post( WP_Post $post ): void {
		$permalink = get_permalink( $post );
		if ( $permalink ) {
			$this->queue_url( $permalink, 'auto' );
		}

		// Home page (exact) and, when it lists posts, its pagination.
		$this->queue_url( home_url( '/' ), 'page' );
		if ( $post->post_type === 'post' ) {
			if ( get_option( 'show_on_front' ) === 'posts' ) {
				$this->queue_url( home_url( '/page/' ), 'prefix' );
			}
			$posts_page = (int) get_option( 'page_for_posts' );
			if ( $posts_page ) {
				$link = get_permalink( $posts_page );
				if ( $link ) {
					$this->queue_url( $link, 'auto' );
				}
			}
			$this->queue_url( get_feed_link(), 'auto' );
		} else {
			$archive = get_post_type_archive_link( $post->post_type );
			if ( $archive ) {
				$this->queue_url( $archive, 'auto' );
			}
		}

		// Category, tag and other public term archives.
		foreach ( get_object_taxonomies( $post->post_type, 'objects' ) as $taxonomy ) {
			if ( empty( $taxonomy->public ) ) {
				continue;
			}
			$terms = get_the_terms( $post, $taxonomy->name );
			if ( ! is_array( $terms ) ) {
				continue;
			}
			foreach ( $terms as $term ) {
				$link = get_term_link( $term );
				if ( is_string( $link ) ) {
					$this->queue_url( $link, 'auto' );
				}
			}
		}

		if ( post_type_supports( $post->post_type, 'author' ) && $post->post_author ) {
			$this->queue_url( get_author_posts_url( (int) $post->post_author ), 'auto' );
		}

		if ( ! $this->shutdown_hooked ) {
			add_action( 'shutdown', array( $this, 'flush' ) );
			$this->shutdown_hooked = true;
		}
	}

	/**
	 * @param string $mode 'page' (exact), 'prefix', or 'auto': a path ending
	 *                     in "/" also covers what is below it (feed, pages,
	 *                     query strings); any other path is purged exactly.
	 */
	private function queue_url( string $url, string $mode ): void {
		$path = wp_make_link_relative( $url );
		if ( $path === '' ) {
			$path = '/';
		}

		if ( $mode === 'auto' ) {
			$mode = str_ends_with( $path, '/' ) ? 'prefix' : 'page';
		}

		// Everything on the site sits below the home path, so purging it as a
		// prefix would flush the whole site.
		$home_path = wp_make_link_relative( home_url( '/' ) );
		if ( $path === ( $home_path === '' ? '/' : $home_path ) ) {
			$mode = 'page';
		}

		// A prefix purge already covers the exact page; never downgrade it.
		if ( ( $this->queue[ $path ] ?? '' ) === 'prefix' ) {
			return;
		}

		$this->queue[ $path ] = $mode;
	}
}
