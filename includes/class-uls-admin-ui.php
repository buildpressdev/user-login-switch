<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ULS_Admin_UI {
	private $settings;
	private $switch_manager;

	public function __construct( ULS_Settings $settings, ULS_Switch_Manager $switch_manager ) {
		$this->settings       = $settings;
		$this->switch_manager = $switch_manager;
	}

	public function register() {
		add_action( 'admin_menu', array( $this, 'register_settings_page' ) );
		add_filter( 'user_row_actions', array( $this, 'add_user_row_action' ), 10, 2 );
		add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_menu' ), 999 );
		add_action( 'admin_notices', array( $this, 'switched_notice' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function register_settings_page() {
		add_options_page(
			__( 'User Login Switch', 'user-login-switch' ),
			__( 'User Login Switch', 'user-login-switch' ),
			'manage_options',
			'user-login-switch',
			array( $this, 'render_settings_page' )
		);
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings  = $this->settings->get();
		$wp_roles  = wp_roles();
		$all_roles = $wp_roles ? (array) $wp_roles->roles : array();

		require ULS_DIR . 'admin/views/settings-page.php';
	}

	public function add_user_row_action( $actions, $user ) {
		if ( ! $this->switch_manager->is_enabled() || ! $this->settings->get( 'show_users_row_action' ) ) {
			return $actions;
		}

		if ( ! $this->switch_manager->can_initiate_switch() ) {
			return $actions;
		}

		if ( get_current_user_id() === (int) $user->ID ) {
			return $actions;
		}

		if ( ! $this->switch_manager->target_role_allowed( (int) $user->ID ) ) {
			return $actions;
		}

		$actions['uls_switch'] = '<a class="uls-switch-link" href="' . esc_url( $this->switch_manager->switch_url( (int) $user->ID ) ) . '">' . esc_html__( 'Switch To', 'user-login-switch' ) . '</a>';

		return $actions;
	}

	public function add_admin_bar_menu( $wp_admin_bar ) {
		if ( ! is_user_logged_in() || ! $this->settings->get( 'show_admin_bar' ) ) {
			return;
		}

		$payload = $this->switch_manager->get_active_switch();

		if ( ! $payload ) {
			return;
		}

		$origin = get_user_by( 'id', (int) $payload['origin_user_id'] );

		$wp_admin_bar->add_node(
			array(
				'id'    => 'uls-switched-state',
				'title' => sprintf( __( 'Switched from %s', 'user-login-switch' ), esc_html( $origin ? $origin->display_name : __( 'Administrator', 'user-login-switch' ) ) ),
				'href'  => false,
			)
		);

		$wp_admin_bar->add_node(
			array(
				'id'     => 'uls-return-admin',
				'parent' => 'uls-switched-state',
				'title'  => __( 'Return to Original Admin', 'user-login-switch' ),
				'href'   => $this->switch_manager->return_url(),
			)
		);
	}

	public function switched_notice() {
		if ( ! $this->settings->get( 'show_admin_notice' ) ) {
			return;
		}

		$payload = $this->switch_manager->get_active_switch();

		if ( ! $payload ) {
			return;
		}

		$origin = get_user_by( 'id', (int) $payload['origin_user_id'] );
		$name   = $origin ? $origin->display_name : __( 'administrator', 'user-login-switch' );

		printf(
			'<div class="notice notice-warning"><p>%s <a href="%s">%s</a></p></div>',
			esc_html( sprintf( __( 'You are currently switched from %s.', 'user-login-switch' ), $name ) ),
			esc_url( $this->switch_manager->return_url() ),
			esc_html__( 'Return to original admin', 'user-login-switch' )
		);
	}

	public function enqueue_assets() {
		$screen = get_current_screen();

		if ( ! $screen ) {
			return;
		}

		$load = ( 'users' === $screen->id || 'settings_page_user-login-switch' === $screen->id );

		if ( ! $load ) {
			return;
		}

		wp_enqueue_style( 'uls-admin', ULS_URL . 'assets/admin.css', array(), ULS_VERSION );
		wp_add_inline_style( 'uls-admin', $this->style_css() );
	}

	private function style_css() {
		$preset = $this->settings->get( 'style_preset' );

		if ( 'minimal' === $preset ) {
			return '.uls-switch-link{font-weight:500;text-decoration:underline;}';
		}

		if ( 'compact' === $preset ) {
			return '.uls-switch-link{padding:1px 6px;border-radius:4px;background:#135e96;color:#fff !important;}';
		}

		return '.uls-switch-link{padding:2px 8px;border-radius:999px;background:#007cba;color:#fff !important;}';
	}
}
