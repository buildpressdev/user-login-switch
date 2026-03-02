<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ULS_Switch_Manager {
	const COOKIE_NAME = 'uls_origin_token';
	const ACTION_KEY  = 'uls_switch_action';
	const RECENT_KEY   = 'uls_recent_targets_';

	private $settings;
	private $audit_log;

	public function __construct( ULS_Settings $settings, ULS_Audit_Log $audit_log ) {
		$this->settings  = $settings;
		$this->audit_log = $audit_log;
	}

	public function register() {
		add_action( 'admin_post_uls_switch', array( $this, 'handle_switch' ) );
		add_action( 'admin_post_uls_return', array( $this, 'handle_return' ) );
		add_action( 'wp_ajax_uls_search_users', array( $this, 'ajax_search_users' ) );
		add_action( 'clear_auth_cookie', array( $this, 'handle_logout_cleanup' ) );
	}

	public function is_enabled() {
		return (bool) $this->settings->get( 'enabled' );
	}

	public function can_initiate_switch( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();

		if ( ! $user_id ) {
			return false;
		}

		$user = get_user_by( 'id', $user_id );

		if ( ! $user ) {
			return false;
		}

		if ( is_super_admin( $user_id ) ) {
			return true;
		}

		if ( ! user_can( $user, 'manage_options' ) ) {
			return false;
		}

		return in_array( 'administrator', (array) $user->roles, true );
	}

	public function target_role_allowed( $target_user_id ) {
		$allowed_roles = (array) $this->settings->get( 'target_roles' );

		if ( empty( $allowed_roles ) ) {
			return true;
		}

		$target = get_user_by( 'id', absint( $target_user_id ) );

		if ( ! $target ) {
			return false;
		}

		foreach ( (array) $target->roles as $role ) {
			if ( in_array( $role, $allowed_roles, true ) ) {
				return true;
			}
		}

		return false;
	}

	public function is_switched() {
		return (bool) $this->get_active_switch();
	}

	public function get_active_switch() {
		$token = $this->read_cookie_token();

		if ( ! $token ) {
			return false;
		}

		$payload = $this->decode_token( $token );

		if ( ! $payload ) {
			return false;
		}

		$key = $this->transient_key( $token );
		$ref = get_transient( $key );

		if ( ! $ref || empty( $ref['active'] ) ) {
			return false;
		}

		if ( (int) $payload['blog_id'] !== (int) get_current_blog_id() ) {
			return false;
		}

		if ( (int) $payload['target_user_id'] !== (int) get_current_user_id() ) {
			return false;
		}

		if ( time() > (int) $payload['expires_at'] ) {
			$this->mark_token_inactive( $token );
			$this->audit_log->log( 'switch_expired', 'expired', (int) $payload['origin_user_id'], (int) $payload['target_user_id'], 'Switch token expired.' );
			return false;
		}

		return $payload;
	}

	public function switch_url( $target_user_id ) {
		$url = add_query_arg(
			array(
				'action'      => 'uls_switch',
				'target_user' => absint( $target_user_id ),
			),
			admin_url( 'admin-post.php' )
		);

		return wp_nonce_url( $url, 'uls_switch_' . absint( $target_user_id ) );
	}

	public function return_url() {
		$url = add_query_arg(
			array(
				'action' => 'uls_return',
			),
			admin_url( 'admin-post.php' )
		);

		return wp_nonce_url( $url, 'uls_return' );
	}

	public function handle_switch() {
		if ( ! $this->is_enabled() ) {
			wp_die( esc_html__( 'User switching is disabled.', 'user-login-switch' ) );
		}

		$target_user_id = isset( $_GET['target_user'] ) ? absint( $_GET['target_user'] ) : 0;
		check_admin_referer( 'uls_switch_' . $target_user_id );

		$origin_user_id = get_current_user_id();

		if ( ! $this->can_initiate_switch( $origin_user_id ) ) {
			$this->audit_log->log( 'switch_failed', 'denied', $origin_user_id, $target_user_id, 'Permission denied.' );
			wp_die( esc_html__( 'You are not allowed to switch users.', 'user-login-switch' ) );
		}

		if ( ! $target_user_id || $origin_user_id === $target_user_id ) {
			$this->audit_log->log( 'switch_failed', 'invalid', $origin_user_id, $target_user_id, 'Invalid target user.' );
			wp_die( esc_html__( 'Invalid target user.', 'user-login-switch' ) );
		}

		$target_user = get_user_by( 'id', $target_user_id );

		if ( ! $target_user || ! $this->target_role_allowed( $target_user_id ) ) {
			$this->audit_log->log( 'switch_failed', 'invalid', $origin_user_id, $target_user_id, 'Target not found or role blocked.' );
			wp_die( esc_html__( 'Target user cannot be switched into.', 'user-login-switch' ) );
		}

		$token = $this->create_switch_token( $origin_user_id, $target_user_id );
		$this->persist_token( $token );

		wp_set_auth_cookie( $target_user_id, false, is_ssl() );
		wp_set_current_user( $target_user_id );
		do_action( 'wp_login', $target_user->user_login, $target_user );

		$this->set_cookie_token( $token );
		$this->track_recent_target( $origin_user_id, $target_user_id );
		$this->audit_log->log( 'switch_started', 'success', $origin_user_id, $target_user_id, 'Switch successful.' );

		wp_safe_redirect( admin_url( 'profile.php?uls_switched=1' ) );
		exit;
	}

	public function handle_return() {
		check_admin_referer( 'uls_return' );

		$payload = $this->get_active_switch();

		if ( ! $payload ) {
			wp_die( esc_html__( 'No active switched session found.', 'user-login-switch' ) );
		}

		$origin_user_id = (int) $payload['origin_user_id'];
		$target_user_id = (int) $payload['target_user_id'];
		$origin_user    = get_user_by( 'id', $origin_user_id );

		if ( ! $origin_user || ! $this->can_initiate_switch( $origin_user_id ) ) {
			$this->audit_log->log( 'switch_failed', 'invalid', $origin_user_id, $target_user_id, 'Origin user invalid on return.' );
			wp_die( esc_html__( 'Original administrator account is no longer valid.', 'user-login-switch' ) );
		}

		wp_set_auth_cookie( $origin_user_id, false, is_ssl() );
		wp_set_current_user( $origin_user_id );
		do_action( 'wp_login', $origin_user->user_login, $origin_user );

		$this->mark_token_inactive( $this->read_cookie_token() );
		self::clear_origin_cookie();
		$this->audit_log->log( 'switch_returned', 'success', $origin_user_id, $target_user_id, 'Returned to original admin.' );

		wp_safe_redirect( admin_url( 'users.php?uls_returned=1' ) );
		exit;
	}

	private function create_switch_token( $origin_user_id, $target_user_id ) {
		$expires_at = time() + ( absint( $this->settings->get( 'timeout_minutes' ) ) * MINUTE_IN_SECONDS );

		$payload = array(
			'origin_user_id' => absint( $origin_user_id ),
			'target_user_id' => absint( $target_user_id ),
			'created_at'     => time(),
			'expires_at'     => $expires_at,
			'session_hash'   => wp_hash( wp_get_session_token() . '|' . $origin_user_id . '|' . $target_user_id ),
			'blog_id'        => get_current_blog_id(),
			'status'         => 'active',
		);

		$encoded = rtrim( strtr( base64_encode( wp_json_encode( $payload ) ), '+/', '-_' ), '=' );
		$sig     = hash_hmac( 'sha256', $encoded, wp_salt( 'auth' ) );

		return $encoded . '.' . $sig;
	}

	private function decode_token( $token ) {
		if ( ! is_string( $token ) || false === strpos( $token, '.' ) ) {
			return false;
		}

		list( $encoded, $sig ) = explode( '.', $token, 2 );
		$expected_sig          = hash_hmac( 'sha256', $encoded, wp_salt( 'auth' ) );

		if ( ! hash_equals( $expected_sig, $sig ) ) {
			return false;
		}

		$json = base64_decode( strtr( $encoded, '-_', '+/' ) );

		if ( false === $json ) {
			return false;
		}

		$data = json_decode( $json, true );

		if ( ! is_array( $data ) ) {
			return false;
		}

		return $data;
	}

	private function persist_token( $token ) {
		$payload = $this->decode_token( $token );

		if ( ! $payload ) {
			return;
		}

		$key = $this->transient_key( $token );

		set_transient(
			$key,
			array(
				'active' => true,
			),
			max( 60, (int) $payload['expires_at'] - time() )
		);
	}

	private function mark_token_inactive( $token ) {
		if ( ! $token ) {
			return;
		}

		delete_transient( $this->transient_key( $token ) );
	}

	private function set_cookie_token( $token ) {
		$payload = $this->decode_token( $token );

		if ( ! $payload ) {
			return;
		}

		setcookie( self::COOKIE_NAME, $token, (int) $payload['expires_at'], COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );

		if ( COOKIEPATH !== SITECOOKIEPATH ) {
			setcookie( self::COOKIE_NAME, $token, (int) $payload['expires_at'], SITECOOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
		}
	}

	public static function clear_origin_cookie() {
		setcookie( self::COOKIE_NAME, '', time() - HOUR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );

		if ( COOKIEPATH !== SITECOOKIEPATH ) {
			setcookie( self::COOKIE_NAME, '', time() - HOUR_IN_SECONDS, SITECOOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
		}
	}

	private function read_cookie_token() {
		if ( empty( $_COOKIE[ self::COOKIE_NAME ] ) ) {
			return '';
		}

		return sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) );
	}

	private function transient_key( $token ) {
		return 'uls_token_' . md5( $token );
	}

	public function handle_logout_cleanup() {
		$token = $this->read_cookie_token();

		if ( $token ) {
			$this->mark_token_inactive( $token );
			self::clear_origin_cookie();
		}
	}

	public function ajax_search_users() {
		if ( ! $this->is_enabled() ) {
			wp_send_json_error( array( 'message' => esc_html__( 'User switching is disabled.', 'user-login-switch' ) ), 403 );
		}

		check_ajax_referer( 'uls_frontend_search', 'nonce' );

		if ( ! $this->can_initiate_switch() ) {
			wp_send_json_error( array( 'message' => esc_html__( 'You are not allowed to switch users.', 'user-login-switch' ) ), 403 );
		}

		$search = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
		$users  = array();

		if ( '' === $search ) {
			$users = $this->get_recent_users( get_current_user_id() );

			if ( empty( $users ) ) {
				$query = new WP_User_Query(
					array(
						'number'  => 15,
						'exclude' => array( get_current_user_id() ),
						'orderby' => 'registered',
						'order'   => 'DESC',
					)
				);

				foreach ( (array) $query->get_results() as $user ) {
					if ( ! $this->target_role_allowed( (int) $user->ID ) ) {
						continue;
					}

					$users[] = $this->format_user_row( $user );
				}
			}
		} else {
			$query = new WP_User_Query(
				array(
					'number'         => 15,
					'search'         => '*' . $search . '*',
					'search_columns' => array( 'user_login', 'user_email', 'display_name', 'user_nicename' ),
					'exclude'        => array( get_current_user_id() ),
					'orderby'        => 'display_name',
					'order'          => 'ASC',
				)
			);

			foreach ( (array) $query->get_results() as $user ) {
				if ( ! $this->target_role_allowed( (int) $user->ID ) ) {
					continue;
				}

				$users[] = $this->format_user_row( $user );
			}
		}

		wp_send_json_success(
			array(
				'users' => array_values( $users ),
			)
		);
	}

	private function format_user_row( $user ) {
		$wp_roles = wp_roles();
		$role     = '';
		$url      = $this->switch_url( (int) $user->ID );
		$url      = html_entity_decode( $url, ENT_QUOTES, 'UTF-8' );

		if ( $wp_roles && ! empty( $user->roles ) ) {
			$first_role = (string) reset( $user->roles );
			$role_data  = $wp_roles->roles[ $first_role ] ?? null;

			if ( is_array( $role_data ) && ! empty( $role_data['name'] ) ) {
				$role = translate_user_role( $role_data['name'] );
			}
		}

		return array(
			'id'         => (int) $user->ID,
			'name'       => $user->display_name ? $user->display_name : $user->user_login,
			'username'   => $user->user_login,
			'email'      => $user->user_email,
			'role'       => $role,
			'switch_url' => $url,
		);
	}

	private function recent_meta_key() {
		return self::RECENT_KEY . get_current_blog_id();
	}

	private function track_recent_target( $origin_user_id, $target_user_id ) {
		$key    = $this->recent_meta_key();
		$recent = get_user_meta( $origin_user_id, $key, true );

		if ( ! is_array( $recent ) ) {
			$recent = array();
		}

		$target_user_id = (int) $target_user_id;

		if ( empty( $recent[ $target_user_id ] ) ) {
			$recent[ $target_user_id ] = array(
				'count'     => 0,
				'last_used' => 0,
			);
		}

		$recent[ $target_user_id ]['count']     = absint( $recent[ $target_user_id ]['count'] ) + 1;
		$recent[ $target_user_id ]['last_used'] = time();

		update_user_meta( $origin_user_id, $key, $recent );
	}

	public function get_recent_users( $origin_user_id ) {
		$key    = $this->recent_meta_key();
		$recent = get_user_meta( absint( $origin_user_id ), $key, true );

		if ( ! is_array( $recent ) ) {
			return array();
		}

		uasort(
			$recent,
			static function ( $a, $b ) {
				$count_a = absint( $a['count'] ?? 0 );
				$count_b = absint( $b['count'] ?? 0 );

				if ( $count_a === $count_b ) {
					return absint( $b['last_used'] ?? 0 ) <=> absint( $a['last_used'] ?? 0 );
				}

				return $count_b <=> $count_a;
			}
		);

		$results = array();

		foreach ( array_slice( array_keys( $recent ), 0, 10 ) as $target_user_id ) {
			$user = get_user_by( 'id', (int) $target_user_id );

			if ( ! $user || ! $this->target_role_allowed( (int) $target_user_id ) ) {
				continue;
			}

			$results[] = $this->format_user_row( $user );
		}

		return $results;
	}
}
