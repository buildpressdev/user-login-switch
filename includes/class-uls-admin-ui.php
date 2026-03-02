<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ULS_Admin_UI {
	private $settings;
	private $switch_manager;
	private $audit_log;

	public function __construct( ULS_Settings $settings, ULS_Switch_Manager $switch_manager, ULS_Audit_Log $audit_log ) {
		$this->settings       = $settings;
		$this->switch_manager = $switch_manager;
		$this->audit_log      = $audit_log;
	}

	public function register() {
		add_action( 'admin_menu', array( $this, 'register_settings_page' ) );
		add_action( 'admin_post_uls_export_audit_csv', array( $this, 'handle_audit_csv_export' ) );
		add_action( 'admin_post_uls_prune_audit_logs', array( $this, 'handle_audit_prune' ) );
		add_filter( 'manage_users_columns', array( $this, 'add_quick_switch_column' ) );
		add_filter( 'manage_users-network_columns', array( $this, 'add_quick_switch_column' ) );
		add_filter( 'manage_users_custom_column', array( $this, 'render_quick_switch_column' ), 10, 3 );
		add_filter( 'manage_users-network_custom_column', array( $this, 'render_quick_switch_column' ), 10, 3 );
		add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_menu' ), 999 );
		add_action( 'admin_notices', array( $this, 'switched_notice' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function add_quick_switch_column( $columns ) {
		if ( ! $this->switch_manager->is_enabled() || ! $this->switch_manager->can_initiate_switch() ) {
			return $columns;
		}

		$updated_columns = array();

		foreach ( $columns as $key => $label ) {
			$updated_columns[ $key ] = $label;

			if ( 'username' === $key ) {
				$updated_columns['uls_quick_switch'] = esc_html__( 'Quick Switch', 'user-login-switch' );
			}
		}

		if ( ! isset( $updated_columns['uls_quick_switch'] ) ) {
			$updated_columns['uls_quick_switch'] = esc_html__( 'Quick Switch', 'user-login-switch' );
		}

		return $updated_columns;
	}

	public function render_quick_switch_column( $value, $column_name, $user_id ) {
		if ( 'uls_quick_switch' !== $column_name ) {
			return $value;
		}

		if ( ! $this->switch_manager->is_enabled() || ! $this->switch_manager->can_initiate_switch() ) {
			return '';
		}

		if ( get_current_user_id() === (int) $user_id || ! $this->switch_manager->target_role_allowed( (int) $user_id ) ) {
			return '<span class="uls-switch-icon uls-switch-icon--disabled" aria-hidden="true"><span class="dashicons dashicons-randomize"></span></span>';
		}

		$url = $this->switch_manager->switch_url( (int) $user_id );

		return sprintf(
			'<a class="uls-switch-icon" href="%1$s" aria-label="%2$s" title="%2$s"><span class="dashicons dashicons-randomize" aria-hidden="true"></span></a>',
			esc_url( $url ),
			esc_attr__( 'Switch to this user', 'user-login-switch' )
		);
	}

	public function register_settings_page() {
		add_options_page(
			__( 'User Login Switch', 'user-login-switch' ),
			__( 'User Login Switch', 'user-login-switch' ),
			'manage_options',
			'user-login-switch',
			array( $this, 'render_settings_page' )
		);

		add_submenu_page(
			'options-general.php',
			__( 'User Switch Audit Log', 'user-login-switch' ),
			__( 'Switch Audit Log', 'user-login-switch' ),
			'manage_options',
			'user-login-switch-audit',
			array( $this, 'render_audit_log_page' )
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

	public function render_audit_log_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$filters = $this->read_audit_filters_from_request();
		$total   = $this->audit_log->query_logs( $filters, true );

		$per_page = max( 1, absint( $filters['per_page'] ) );
		$pages    = max( 1, (int) ceil( $total / $per_page ) );

		if ( $filters['page'] > $pages ) {
			$filters['page'] = $pages;
		}

		$rows = $this->audit_log->query_logs( $filters, false );

		require ULS_DIR . 'admin/views/audit-log-page.php';
	}

	public function handle_audit_csv_export() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to export logs.', 'user-login-switch' ) );
		}

		check_admin_referer( 'uls_export_audit_csv' );

		$filters            = $this->read_audit_filters_from_request();
		$filters['page']    = 1;
		$filters['per_page'] = 200;

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=user-switch-audit-' . gmdate( 'Ymd-His' ) . '.csv' );

		$output = fopen( 'php://output', 'w' );

		if ( false === $output ) {
			wp_die( esc_html__( 'Could not export CSV.', 'user-login-switch' ) );
		}

		fputcsv( $output, array( 'id', 'blog_id', 'actor_user_id', 'target_user_id', 'current_user_id', 'action', 'status', 'ip_address', 'user_agent', 'details', 'created_at_gmt' ) );

		$batch_page = 1;

		while ( $batch_page <= 50 ) {
			$filters['page'] = $batch_page;
			$rows            = $this->audit_log->query_logs( $filters, false );

			if ( empty( $rows ) ) {
				break;
			}

			foreach ( $rows as $row ) {
				fputcsv(
					$output,
					array(
						$row['id'],
						$row['blog_id'],
						$row['actor_user_id'],
						$row['target_user_id'],
						$row['current_user_id'],
						$row['action'],
						$row['status'],
						$row['ip_address'],
						$row['user_agent'],
						$row['details'],
						$row['created_at_gmt'],
					)
				);
			}

			++$batch_page;
		}

		fclose( $output );
		exit;
	}

	public function handle_audit_prune() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to prune logs.', 'user-login-switch' ) );
		}

		check_admin_referer( 'uls_prune_audit_logs' );

		$retain = max( 1, absint( $this->settings->get( 'log_retention_days' ) ) );
		$this->audit_log->cleanup_by_days( $retain );

		wp_safe_redirect( add_query_arg( 'uls_logs_pruned', '1', admin_url( 'options-general.php?page=user-login-switch-audit' ) ) );
		exit;
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

		$load = in_array( $screen->id, array( 'users', 'settings_page_user-login-switch', 'settings_page_user-login-switch-audit' ), true );

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

	private function read_audit_filters_from_request() {
		return array(
			'action'    => isset( $_GET['action_filter'] ) ? sanitize_key( wp_unslash( $_GET['action_filter'] ) ) : '',
			'status'    => isset( $_GET['status_filter'] ) ? sanitize_key( wp_unslash( $_GET['status_filter'] ) ) : '',
			'actor'     => isset( $_GET['actor_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['actor_filter'] ) ) : '',
			'target'    => isset( $_GET['target_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['target_filter'] ) ) : '',
			'date_from' => isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '',
			'date_to'   => isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '',
			'page'      => isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1,
			'per_page'  => isset( $_GET['per_page'] ) ? max( 10, min( 200, absint( $_GET['per_page'] ) ) ) : 20,
		);
	}
}
