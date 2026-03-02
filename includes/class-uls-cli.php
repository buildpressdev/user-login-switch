<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ULS_CLI {
	private $switch_manager;

	public function __construct( ULS_Switch_Manager $switch_manager ) {
		$this->switch_manager = $switch_manager;
	}

	public function register() {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'uls quick-login', array( $this, 'quick_login' ) );
		}
	}

	/**
	 * Generate one-time quick login URL for admin accounts.
	 *
	 * ## OPTIONS
	 *
	 * --user=<id|login|email>
	 * : Target user identifier.
	 *
	 * [--ttl=<minutes>]
	 * : Token lifetime in minutes (1-60).
	 */
	public function quick_login( $args, $assoc_args ) {
		if ( ! $this->switch_manager->is_guest_quick_login_enabled() ) {
			WP_CLI::error( 'Guest quick login is disabled or blocked by current environment.' );
		}

		if ( empty( $assoc_args['user'] ) ) {
			WP_CLI::error( 'Missing required --user parameter.' );
		}

		$user = $this->find_user( $assoc_args['user'] );

		if ( ! $user ) {
			WP_CLI::error( 'User not found.' );
		}

		if ( ! $this->switch_manager->can_be_quick_login_target( $user->ID ) ) {
			WP_CLI::error( 'Quick login target must be an administrator/super admin.' );
		}

		$ttl  = isset( $assoc_args['ttl'] ) ? absint( $assoc_args['ttl'] ) : 0;
		$url  = $this->switch_manager->generate_quick_login_url( (int) $user->ID, $ttl, 0 );

		if ( ! $url ) {
			WP_CLI::error( 'Could not generate quick login URL.' );
		}

		WP_CLI::success( 'One-time quick login URL generated:' );
		WP_CLI::line( $url );
	}

	private function find_user( $user_identifier ) {
		if ( is_numeric( $user_identifier ) ) {
			$user = get_user_by( 'id', (int) $user_identifier );

			if ( $user ) {
				return $user;
			}
		}

		$user = get_user_by( 'login', $user_identifier );

		if ( $user ) {
			return $user;
		}

		return get_user_by( 'email', $user_identifier );
	}
}
