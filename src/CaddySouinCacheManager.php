<?php

namespace morsalin1342\CaddySouinCacheManager;

use morsalin1342\CaddySouinCacheManager\Admin\Assets;
use morsalin1342\CaddySouinCacheManager\Admin\Menu;
use morsalin1342\CaddySouinCacheManager\Souin\Api;
use morsalin1342\CaddySouinCacheManager\WordPress\Hooks;
use morsalin1342\CaddySouinCacheManager\WordPress\RestApi;
use WP_CLI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CaddySouinCacheManager {

	private string $option_name;
	private string $default_api_url;
	private string $main_page_slug;
	private string $settings_slug;
	private string $version;

	public function __construct() {
		$this->option_name     = 'cscm_settings';
		$this->default_api_url = 'http://127.0.0.1:2019/souin-api/souin';
		$this->main_page_slug  = 'cscm-cache-keys';
		$this->settings_slug   = 'cscm-settings';
		$this->version         = '1.8.15';
	}

	public function init(): void {
		$api = new Api( $this->get_api_url() );

		$assets  = new Assets();
		$menu    = new Menu( $this->main_page_slug, [ $this, 'render_cache_keys_page' ], [ $this, 'render_settings_page' ] );
		$hooks   = new Hooks( $api );
		$rest_api = new RestApi( $api );

		$assets->add_hooks();
		$menu->add_hooks();
		$hooks->add_hooks();
		$rest_api->add_hooks();

		add_action( 'admin_init', array( $this, 'register_settings' ) );

		if ( class_exists( 'WP_CLI' ) ) {
			WP_CLI::add_command( 'souin', CaddySouinCacheManagerCli::class );
		}
	}

	public function register_settings(): void {
		register_setting( $this->option_name, $this->option_name );
	}

	public function get_api_url(): string {
		$options = get_option( $this->option_name );
		return ! empty( $options['api_url'] ) ? $options['api_url'] : $this->default_api_url;
	}

	public function render_settings_page(): void {
		$this->render_vue_mount();
	}

	public function render_cache_keys_page(): void {
		$this->render_vue_mount();
	}

	private function render_vue_mount(): void {
		?>
        <div class="wrap">
            <div id="cscm-vue-app"></div>
        </div>
		<?php
	}
}
