<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once ULS_DIR . 'includes/class-uls-settings.php';
require_once ULS_DIR . 'includes/class-uls-audit-log.php';
require_once ULS_DIR . 'includes/class-uls-switch-manager.php';
require_once ULS_DIR . 'includes/class-uls-admin-ui.php';

class ULS_Plugin {
	const OPTION_KEY = 'uls_settings';

	private static $instance;
	private $settings;
	private $audit_log;
	private $switch_manager;
	private $admin_ui;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		$this->settings       = new ULS_Settings();
		$this->audit_log      = new ULS_Audit_Log();
		$this->switch_manager = new ULS_Switch_Manager( $this->settings, $this->audit_log );
		$this->admin_ui       = new ULS_Admin_UI( $this->settings, $this->switch_manager );

		add_action( 'plugins_loaded', array( $this, 'boot' ) );
	}

	public function boot() {
		$this->settings->register();
		$this->audit_log->register();
		$this->switch_manager->register();
		$this->admin_ui->register();
	}

	public static function activate( $network_wide ) {
		if ( is_multisite() && $network_wide ) {
			$site_ids = get_sites(
				array(
					'fields' => 'ids',
				)
			);

			foreach ( $site_ids as $site_id ) {
				switch_to_blog( (int) $site_id );
				ULS_Settings::maybe_seed_defaults();
				ULS_Audit_Log::maybe_create_table();
				restore_current_blog();
			}
		} else {
			ULS_Settings::maybe_seed_defaults();
			ULS_Audit_Log::maybe_create_table();
		}

		if ( ! wp_next_scheduled( 'uls_cleanup_logs' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'uls_cleanup_logs' );
		}
	}

	public static function deactivate( $network_wide ) {
		if ( wp_next_scheduled( 'uls_cleanup_logs' ) ) {
			wp_clear_scheduled_hook( 'uls_cleanup_logs' );
		}

		if ( is_multisite() && $network_wide ) {
			$site_ids = get_sites(
				array(
					'fields' => 'ids',
				)
			);

			foreach ( $site_ids as $site_id ) {
				switch_to_blog( (int) $site_id );
				ULS_Switch_Manager::clear_origin_cookie();
				restore_current_blog();
			}
		} else {
			ULS_Switch_Manager::clear_origin_cookie();
		}
	}
}
