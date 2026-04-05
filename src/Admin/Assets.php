<?php

namespace morsalin1342\CaddySouinCacheManager\Admin;

class Assets {
	public function add_hooks(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function enqueue_assets( $hook ): void {
		// More robust hook check to ensure we only load on our plugin pages
		if ( strpos( $hook, 'cscm-' ) === false ) {
			return;
		}

		$plugin_dir_path = dirname( __FILE__, 3 );
		$plugin_file = $plugin_dir_path . '/caddy-souin-cache-manager.php';
		$manifest_path = $plugin_dir_path . '/dist/.vite/manifest.json';

		if ( ! file_exists( $manifest_path ) ) {
			return;
		}

		$manifest = json_decode( file_get_contents( $manifest_path ), true );
		if ( ! is_array( $manifest ) ) {
			return;
		}

		$plugin_url = plugin_dir_url( $plugin_file );

		// Main entry point
		if ( isset( $manifest['assets/src/main.js'] ) ) {
			$entry = $manifest['assets/src/main.js'];
			$js_handle = 'cscm-app-js';

			wp_enqueue_script(
				$js_handle,
				$plugin_url . 'dist/' . $entry['file'],
				array(),
				null,
				true
			);

			// Pass data to the script
			wp_localize_script( $js_handle, 'CSCM', array(
				'apiUrl'      => esc_url_raw( rest_url( 'cscm/v1' ) ),
				'nonce'       => wp_create_nonce( 'wp_rest' ),
				'currentPage' => strpos( $hook, 'cscm-cache-keys' ) !== false ? 'keys' : 'settings',
			) );

			// Enqueue CSS
			if ( isset( $entry['css'] ) && is_array( $entry['css'] ) ) {
				foreach ( $entry['css'] as $index => $css_file ) {
					wp_enqueue_style(
						'cscm-app-css-' . $index,
						$plugin_url . 'dist/' . $css_file,
						array(),
						null
					);
				}
			}
		}

		// Enqueue WordPress default styles for components
		wp_enqueue_style( 'wp-components' );
	}
}
