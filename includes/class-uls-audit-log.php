<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ULS_Audit_Log {
	public function register() {
		add_action( 'uls_cleanup_logs', array( $this, 'cleanup' ) );
	}

	public static function maybe_create_table() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table_name      = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			blog_id bigint(20) unsigned NOT NULL,
			actor_user_id bigint(20) unsigned NOT NULL,
			target_user_id bigint(20) unsigned NULL,
			current_user_id bigint(20) unsigned NULL,
			action varchar(50) NOT NULL,
			status varchar(20) NOT NULL,
			ip_address varchar(45) NULL,
			user_agent text NULL,
			details text NULL,
			created_at_gmt datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY idx_action (action),
			KEY idx_actor (actor_user_id),
			KEY idx_created (created_at_gmt)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	public static function table_name() {
		global $wpdb;

		return $wpdb->prefix . 'uls_audit_log';
	}

	public function log( $action, $status, $actor_user_id, $target_user_id = null, $details = '' ) {
		$settings = wp_parse_args( (array) get_option( ULS_Settings::OPTION_KEY, array() ), ULS_Settings::defaults() );

		if ( empty( $settings['enable_audit_log'] ) ) {
			return;
		}

		global $wpdb;

		$ip_address = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

		$wpdb->insert(
			self::table_name(),
			array(
				'blog_id'         => get_current_blog_id(),
				'actor_user_id'   => absint( $actor_user_id ),
				'target_user_id'  => null === $target_user_id ? null : absint( $target_user_id ),
				'current_user_id' => get_current_user_id() ? absint( get_current_user_id() ) : null,
				'action'          => sanitize_key( $action ),
				'status'          => sanitize_key( $status ),
				'ip_address'      => $ip_address,
				'user_agent'      => $user_agent,
				'details'         => sanitize_textarea_field( $details ),
				'created_at_gmt'  => current_time( 'mysql', true ),
			),
			array(
				'%d',
				'%d',
				'%d',
				'%d',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
			)
		);
	}

	public function cleanup() {
		$settings = wp_parse_args( (array) get_option( ULS_Settings::OPTION_KEY, array() ), ULS_Settings::defaults() );
		$retain   = max( 1, absint( $settings['log_retention_days'] ?? 90 ) );

		global $wpdb;

		$table_name = self::table_name();
		$cutoff     = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS * $retain );

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table_name} WHERE created_at_gmt < %s", $cutoff ) );
	}
}
