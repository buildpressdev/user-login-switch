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
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		add_action( 'wp_footer', array( $this, 'render_frontend_widget' ) );
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

		$can_switch = $this->switch_manager->can_initiate_switch();
		$payload = $this->switch_manager->get_active_switch();

		if ( ! $can_switch && ! $payload ) {
			return;
		}

		if ( $can_switch ) {
			$wp_admin_bar->add_node(
				array(
					'id'    => 'uls-quick-switch',
					'title' => __( 'Quick Switch', 'user-login-switch' ),
					'href'  => admin_url( 'users.php' ),
				)
			);

			$recent_users = $this->switch_manager->get_recent_users( get_current_user_id() );

			if ( ! empty( $recent_users ) ) {
				foreach ( array_slice( $recent_users, 0, 8 ) as $recent_user ) {
					$title = $recent_user['name'];

					if ( ! empty( $recent_user['role'] ) ) {
						$title .= ' (' . $recent_user['role'] . ')';
					}

					$wp_admin_bar->add_node(
						array(
							'id'     => 'uls-recent-' . (int) $recent_user['id'],
							'parent' => 'uls-quick-switch',
							'title'  => esc_html( $title ),
							'href'   => esc_url( $recent_user['switch_url'] ),
						)
					);
				}
			}

			$wp_admin_bar->add_node(
				array(
					'id'     => 'uls-manage-users',
					'parent' => 'uls-quick-switch',
					'title'  => __( 'Open Users Screen', 'user-login-switch' ),
					'href'   => admin_url( 'users.php' ),
				)
			);
		}

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

	public function enqueue_frontend_assets() {
		if ( ! $this->can_show_frontend_widget() ) {
			return;
		}

		wp_enqueue_style( 'dashicons' );
		wp_enqueue_style( 'uls-frontend', ULS_URL . 'assets/frontend.css', array(), ULS_VERSION );
		wp_enqueue_script( 'uls-frontend', ULS_URL . 'assets/frontend.js', array(), ULS_VERSION, true );

		wp_localize_script(
			'uls-frontend',
			'ulsFrontend',
			array(
				'ajax_url'       => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'uls_frontend_search' ),
				'can_search'     => $this->switch_manager->can_initiate_switch(),
				'is_switched'    => $this->switch_manager->is_switched(),
				'return_url'     => $this->switch_manager->return_url(),
				'position'       => $this->settings->get( 'widget_position' ),
				'search_label'   => esc_html__( 'Search users by name, email, or username', 'user-login-switch' ),
				'empty_label'    => esc_html__( 'No users found.', 'user-login-switch' ),
				'loading_label'  => esc_html__( 'Loading users...', 'user-login-switch' ),
				'error_label'    => esc_html__( 'Could not load users. Please refresh and try again.', 'user-login-switch' ),
				'no_access_text' => esc_html__( 'Search is disabled in switched mode. Use return to go back.', 'user-login-switch' ),
			)
		);
	}

	public function render_frontend_widget() {
		if ( ! $this->can_show_frontend_widget() ) {
			return;
		}

		$position  = sanitize_html_class( $this->settings->get( 'widget_position' ) );
		$is_active = $this->switch_manager->is_switched();
		?>
		<div class="uls-widget uls-widget--<?php echo esc_attr( $position ); ?>" id="uls-widget-root">
			<button class="uls-widget__toggle" type="button" aria-expanded="false" aria-controls="uls-widget-modal">
				<span class="uls-widget__icon dashicons dashicons-randomize" aria-hidden="true"></span>
				<span class="screen-reader-text"><?php esc_html_e( 'Open user switcher', 'user-login-switch' ); ?></span>
			</button>
			<div class="uls-widget__modal" id="uls-widget-modal" hidden>
				<div class="uls-widget__head">
					<strong><?php esc_html_e( 'Quick User Switch', 'user-login-switch' ); ?></strong>
					<button class="uls-widget__close" type="button" aria-label="<?php esc_attr_e( 'Close', 'user-login-switch' ); ?>">&times;</button>
				</div>
				<div class="uls-widget__body">
					<?php if ( $this->switch_manager->can_initiate_switch() ) : ?>
						<label class="screen-reader-text" for="uls-user-search"><?php esc_html_e( 'Search users', 'user-login-switch' ); ?></label>
						<input id="uls-user-search" class="uls-widget__search" type="search" autocomplete="off" placeholder="<?php esc_attr_e( 'Search users by name, email, username', 'user-login-switch' ); ?>" />
						<div class="uls-widget__status" data-uls-status><?php esc_html_e( 'Loading users...', 'user-login-switch' ); ?></div>
						<ul class="uls-widget__list" data-uls-users></ul>
					<?php else : ?>
						<p class="uls-widget__status"><?php esc_html_e( 'Search is disabled in switched mode. Use return to go back.', 'user-login-switch' ); ?></p>
					<?php endif; ?>

					<?php if ( $is_active ) : ?>
						<p class="uls-widget__return-wrap"><a class="uls-widget__return" href="<?php echo esc_url( $this->switch_manager->return_url() ); ?>"><?php esc_html_e( 'Return to Original Admin', 'user-login-switch' ); ?></a></p>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
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

	private function can_show_frontend_widget() {
		if ( is_admin() || ! is_user_logged_in() ) {
			return false;
		}

		if ( ! $this->switch_manager->is_enabled() || ! $this->settings->get( 'show_frontend_widget' ) ) {
			return false;
		}

		return $this->switch_manager->can_initiate_switch() || $this->switch_manager->is_switched();
	}
}
