<?php

namespace morsalin1342\CaddySouinCacheManager\Admin;

class Menu {
	private string $main_page_slug;
	private $cache_keys_page_callback;
	private $settings_page_callback;

	public function __construct( string $main_page_slug, callable $cache_keys_page_callback, callable $settings_page_callback ) {
		$this->main_page_slug           = $main_page_slug;
		$this->cache_keys_page_callback = $cache_keys_page_callback;
		$this->settings_page_callback   = $settings_page_callback;
	}

	public function add_hooks(): void {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
	}

	public function add_admin_menu(): void {
		add_menu_page(
			'Souin Cache',
			'Souin Cache',
			'manage_options',
			$this->main_page_slug,
			$this->cache_keys_page_callback,
			'dashicons-performance',
			81
		);

		add_submenu_page(
			$this->main_page_slug,
			'Cache Keys',
			'Cache Keys',
			'manage_options',
			$this->main_page_slug,
			$this->cache_keys_page_callback
		);

		add_submenu_page(
			$this->main_page_slug,
			'Settings',
			'Settings',
			'manage_options',
			'cscm-settings',
			$this->settings_page_callback
		);
	}
}
