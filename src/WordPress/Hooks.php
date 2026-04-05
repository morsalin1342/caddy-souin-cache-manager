<?php

namespace morsalin1342\CaddySouinCacheManager\WordPress;

use morsalin1342\CaddySouinCacheManager\Souin\Api;

class Hooks {
	private Api $api;

	public function __construct( Api $api ) {
		$this->api = $api;
	}

	public function add_hooks(): void {
		add_action( 'save_post', array( $this, 'auto_purge_on_save' ), 10, 2 );
		add_action( 'deleted_post', array( $this, 'auto_purge_on_delete' ) );
		add_action( 'wp_update_comment_count', array( $this, 'auto_purge_on_comment' ) );
	}

	public function is_auto_purge_enabled(): bool {
		$options = get_option( 'cscm_settings' );

		return ! isset( $options['auto_purge'] ) || $options['auto_purge'] == 1;
	}

	public function auto_purge_on_save( $post_id, $post ): void {
		if ( ! $this->is_auto_purge_enabled() ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( $post->post_status !== 'publish' ) {
			return;
		}

		$this->purge_post_and_related( $post_id );
	}

	public function auto_purge_on_delete( $post_id ): void {
		if ( ! $this->is_auto_purge_enabled() ) {
			return;
		}
		$this->purge_post_and_related( $post_id );
	}

	public function auto_purge_on_comment( $post_id ): void {
		if ( ! $this->is_auto_purge_enabled() ) {
			return;
		}
		$this->purge_post_and_related( $post_id );
	}

	private function purge_post_and_related( $post_id ): void {
		$permalink = get_permalink( $post_id );
		if ( $permalink ) {
			$path = wp_make_link_relative( $permalink );
			$this->api->purge_url( $path );
		}
		$this->api->purge_url( '/' );
	}
}
