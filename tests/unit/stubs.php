<?php
// phpcs:ignoreFile -- minimal stand-ins for WordPress core classes.

class WP_Error {
	private array $errors = array();
	private array $data   = array();

	public function __construct( $code = '', $message = '', $data = '' ) {
		if ( $code !== '' ) {
			$this->errors[ $code ][] = $message;
			if ( $data !== '' ) {
				$this->data[ $code ] = $data;
			}
		}
	}

	public function get_error_code() {
		return array_key_first( $this->errors ) ?? '';
	}

	public function get_error_message() {
		$code = $this->get_error_code();

		return $this->errors[ $code ][0] ?? '';
	}

	public function get_error_data( $code = '' ) {
		return $this->data[ $code === '' ? $this->get_error_code() : $code ] ?? null;
	}

	public function add_data( $data, $code = '' ) {
		$this->data[ $code === '' ? $this->get_error_code() : $code ] = $data;
	}
}

class WP_Post {
	public $ID          = 0;
	public $post_type   = 'post';
	public $post_status = 'publish';
	public $post_name   = '';
	public $post_author = 0;

	public function __construct( array $fields = array() ) {
		foreach ( $fields as $key => $value ) {
			$this->$key = $value;
		}
	}
}

class WP_REST_Request {
	private array $json;

	public function __construct( array $json = array() ) {
		$this->json = $json;
	}

	public function get_json_params() {
		return $this->json;
	}

	public function get_param( $key ) {
		return $this->json[ $key ] ?? null;
	}
}

class WP_REST_Response {
	public $data;
	public $status;

	public function __construct( $data = null, $status = 200 ) {
		$this->data   = $data;
		$this->status = $status;
	}

	public function get_data() {
		return $this->data;
	}

	public function get_status() {
		return $this->status;
	}
}
