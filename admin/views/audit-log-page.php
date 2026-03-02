<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$base_url = admin_url( 'options-general.php?page=user-login-switch-audit' );

$query_for_links = array(
	'action_filter' => $filters['action'],
	'status_filter' => $filters['status'],
	'actor_filter'  => $filters['actor'],
	'target_filter' => $filters['target'],
	'date_from'     => $filters['date_from'],
	'date_to'       => $filters['date_to'],
	'per_page'      => $filters['per_page'],
);

$export_url = wp_nonce_url(
	add_query_arg(
		array_merge(
			array(
				'action' => 'uls_export_audit_csv',
			),
			$query_for_links
		),
		admin_url( 'admin-post.php' )
	),
	'uls_export_audit_csv'
);

$prune_url = wp_nonce_url(
	add_query_arg(
		array(
			'action' => 'uls_prune_audit_logs',
		),
		admin_url( 'admin-post.php' )
	),
	'uls_prune_audit_logs'
);
?>
<div class="wrap uls-audit-wrap">
	<h1><?php esc_html_e( 'User Switch Audit Log', 'user-login-switch' ); ?></h1>

	<?php if ( isset( $_GET['uls_logs_pruned'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Old logs were pruned using current retention settings.', 'user-login-switch' ); ?></p></div>
	<?php endif; ?>

	<p class="description"><?php esc_html_e( 'Audit logs may include user IDs, action status, IP address, and user agent for security tracing.', 'user-login-switch' ); ?></p>

	<form method="get" action="<?php echo esc_url( $base_url ); ?>">
		<input type="hidden" name="page" value="user-login-switch-audit" />
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Action', 'user-login-switch' ); ?></th>
				<td><input type="text" name="action_filter" value="<?php echo esc_attr( $filters['action'] ); ?>" placeholder="switch_started" /></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Status', 'user-login-switch' ); ?></th>
				<td><input type="text" name="status_filter" value="<?php echo esc_attr( $filters['status'] ); ?>" placeholder="success" /></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Actor', 'user-login-switch' ); ?></th>
				<td><input type="text" name="actor_filter" value="<?php echo esc_attr( $filters['actor'] ); ?>" placeholder="ID, login, email" /></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Target', 'user-login-switch' ); ?></th>
				<td><input type="text" name="target_filter" value="<?php echo esc_attr( $filters['target'] ); ?>" placeholder="ID, login, email" /></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Date range', 'user-login-switch' ); ?></th>
				<td>
					<input type="date" name="date_from" value="<?php echo esc_attr( $filters['date_from'] ); ?>" />
					<input type="date" name="date_to" value="<?php echo esc_attr( $filters['date_to'] ); ?>" />
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'Apply Filters', 'user-login-switch' ), 'secondary', '', false ); ?>
		<a class="button button-secondary" href="<?php echo esc_url( $export_url ); ?>"><?php esc_html_e( 'Export CSV', 'user-login-switch' ); ?></a>
		<a class="button button-link-delete" href="<?php echo esc_url( $prune_url ); ?>"><?php esc_html_e( 'Prune Old Logs', 'user-login-switch' ); ?></a>
	</form>

	<table class="widefat striped" style="margin-top:16px;">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Time (GMT)', 'user-login-switch' ); ?></th>
				<th><?php esc_html_e( 'Action', 'user-login-switch' ); ?></th>
				<th><?php esc_html_e( 'Status', 'user-login-switch' ); ?></th>
				<th><?php esc_html_e( 'Actor', 'user-login-switch' ); ?></th>
				<th><?php esc_html_e( 'Target', 'user-login-switch' ); ?></th>
				<th><?php esc_html_e( 'Details', 'user-login-switch' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $rows ) ) : ?>
				<tr><td colspan="6"><?php esc_html_e( 'No logs found for current filters.', 'user-login-switch' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $rows as $row ) : ?>
					<tr>
						<td><?php echo esc_html( $row['created_at_gmt'] ); ?></td>
						<td><code><?php echo esc_html( $row['action'] ); ?></code></td>
						<td><code><?php echo esc_html( $row['status'] ); ?></code></td>
						<td><?php echo esc_html( (string) $row['actor_user_id'] ); ?></td>
						<td><?php echo esc_html( (string) $row['target_user_id'] ); ?></td>
						<td><?php echo esc_html( $row['details'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>

	<?php if ( $pages > 1 ) : ?>
		<div class="tablenav" style="margin-top:12px;">
			<div class="tablenav-pages">
				<?php
				echo wp_kses_post(
					paginate_links(
						array(
							'base'      => esc_url_raw( add_query_arg( 'paged', '%#%', add_query_arg( $query_for_links, $base_url ) ) ),
							'format'    => '',
							'prev_text' => __( '&laquo;', 'user-login-switch' ),
							'next_text' => __( '&raquo;', 'user-login-switch' ),
							'total'     => $pages,
							'current'   => max( 1, (int) $filters['page'] ),
						)
					)
				);
				?>
			</div>
		</div>
	<?php endif; ?>
</div>
