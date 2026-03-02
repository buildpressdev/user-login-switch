<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ULS_Settings {
	const OPTION_KEY = 'uls_settings';

	public static function defaults() {
		return array(
			'enabled'               => 1,
			'timeout_minutes'       => 120,
			'show_users_row_action' => 1,
			'show_admin_bar'        => 1,
			'show_admin_notice'     => 1,
			'style_preset'          => 'default',
			'enable_audit_log'      => 1,
			'log_retention_days'    => 90,
			'target_roles'          => array(),
		);
	}

	public static function maybe_seed_defaults() {
		$current = get_option( self::OPTION_KEY );

		if ( false === $current ) {
			add_option( self::OPTION_KEY, self::defaults(), '', 'no' );
		}
	}

	public function register() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	public function register_settings() {
		register_setting(
			'uls_settings_group',
			self::OPTION_KEY,
			array( $this, 'sanitize' )
		);
	}

	public function sanitize( $input ) {
		$defaults = self::defaults();

		$output = array(
			'enabled'               => ! empty( $input['enabled'] ) ? 1 : 0,
			'timeout_minutes'       => max( 5, min( 1440, absint( $input['timeout_minutes'] ?? $defaults['timeout_minutes'] ) ) ),
			'show_users_row_action' => ! empty( $input['show_users_row_action'] ) ? 1 : 0,
			'show_admin_bar'        => ! empty( $input['show_admin_bar'] ) ? 1 : 0,
			'show_admin_notice'     => ! empty( $input['show_admin_notice'] ) ? 1 : 0,
			'style_preset'          => sanitize_key( $input['style_preset'] ?? $defaults['style_preset'] ),
			'enable_audit_log'      => ! empty( $input['enable_audit_log'] ) ? 1 : 0,
			'log_retention_days'    => max( 1, min( 3650, absint( $input['log_retention_days'] ?? $defaults['log_retention_days'] ) ) ),
			'target_roles'          => array_values( array_map( 'sanitize_key', (array) ( $input['target_roles'] ?? array() ) ) ),
		);

		if ( ! in_array( $output['style_preset'], array( 'default', 'compact', 'minimal' ), true ) ) {
			$output['style_preset'] = 'default';
		}

		return $output;
	}

	public function get( $key = null ) {
		$settings = wp_parse_args( (array) get_option( self::OPTION_KEY, array() ), self::defaults() );

		if ( null === $key ) {
			return $settings;
		}

		return $settings[ $key ] ?? null;
	}
}
