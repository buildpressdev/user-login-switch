<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$selected_roles = (array) ( $settings['target_roles'] ?? array() );
$environment    = function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production';
?>
<div class="wrap uls-settings-wrap">
	<h1><?php esc_html_e( 'User Login Switch Settings', 'user-login-switch' ); ?></h1>
	<form method="post" action="options.php">
		<?php settings_fields( 'uls_settings_group' ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Enable plugin', 'user-login-switch' ); ?></th>
				<td>
					<label><input type="checkbox" name="uls_settings[enabled]" value="1" <?php checked( ! empty( $settings['enabled'] ) ); ?> /> <?php esc_html_e( 'Enable user switching', 'user-login-switch' ); ?></label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Session timeout (minutes)', 'user-login-switch' ); ?></th>
				<td><input type="number" min="5" max="1440" name="uls_settings[timeout_minutes]" value="<?php echo esc_attr( (int) $settings['timeout_minutes'] ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Guest quick login', 'user-login-switch' ); ?></th>
				<td>
					<label><input type="checkbox" name="uls_settings[enable_guest_quick_login]" value="1" <?php checked( ! empty( $settings['enable_guest_quick_login'] ) ); ?> /> <?php esc_html_e( 'Enable one-time quick login links for logged-out admins', 'user-login-switch' ); ?></label>
					<p class="description"><?php echo esc_html( sprintf( __( 'Current environment: %s. Quick login is allowed by default only on local/development/staging.', 'user-login-switch' ), $environment ) ); ?></p>
					<label><?php esc_html_e( 'Link TTL (minutes)', 'user-login-switch' ); ?> <input type="number" min="1" max="60" name="uls_settings[guest_quick_login_ttl]" value="<?php echo esc_attr( (int) $settings['guest_quick_login_ttl'] ); ?>" /></label>
					<p class="description"><code>wp uls quick-login --user=admin</code></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Initiator role', 'user-login-switch' ); ?></th>
				<td><code><?php esc_html_e( 'Administrator only (fixed)', 'user-login-switch' ); ?></code></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Allowed target roles', 'user-login-switch' ); ?></th>
				<td>
					<fieldset>
						<legend class="screen-reader-text"><?php esc_html_e( 'Allowed target roles', 'user-login-switch' ); ?></legend>
						<p><label><input type="checkbox" class="uls-all-roles" <?php checked( empty( $selected_roles ) ); ?> /> <?php esc_html_e( 'All roles', 'user-login-switch' ); ?></label></p>
						<?php foreach ( $all_roles as $role_key => $role_data ) : ?>
							<p>
								<label>
									<input type="checkbox" class="uls-role-box" name="uls_settings[target_roles][]" value="<?php echo esc_attr( $role_key ); ?>" <?php checked( in_array( $role_key, $selected_roles, true ) ); ?> />
									<?php echo esc_html( translate_user_role( $role_data['name'] ) ); ?>
								</label>
							</p>
						<?php endforeach; ?>
						<p class="description"><?php esc_html_e( 'If no role is selected, all roles are allowed.', 'user-login-switch' ); ?></p>
					</fieldset>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'UI placement', 'user-login-switch' ); ?></th>
				<td>
					<label><input type="checkbox" name="uls_settings[show_admin_bar]" value="1" <?php checked( ! empty( $settings['show_admin_bar'] ) ); ?> /> <?php esc_html_e( 'Admin bar return menu', 'user-login-switch' ); ?></label><br />
					<label><input type="checkbox" name="uls_settings[show_admin_notice]" value="1" <?php checked( ! empty( $settings['show_admin_notice'] ) ); ?> /> <?php esc_html_e( 'Admin switched notice', 'user-login-switch' ); ?></label><br />
					<label><input type="checkbox" name="uls_settings[show_frontend_widget]" value="1" <?php checked( ! empty( $settings['show_frontend_widget'] ) ); ?> /> <?php esc_html_e( 'Frontend switch widget', 'user-login-switch' ); ?></label>
					<p>
						<label for="uls-widget-position"><?php esc_html_e( 'Widget position', 'user-login-switch' ); ?></label>
						<select id="uls-widget-position" name="uls_settings[widget_position]">
							<option value="left-center" <?php selected( 'left-center', $settings['widget_position'] ?? 'left-center' ); ?>><?php esc_html_e( 'Left Center', 'user-login-switch' ); ?></option>
							<option value="right-center" <?php selected( 'right-center', $settings['widget_position'] ?? '' ); ?>><?php esc_html_e( 'Right Center', 'user-login-switch' ); ?></option>
							<option value="left-bottom" <?php selected( 'left-bottom', $settings['widget_position'] ?? '' ); ?>><?php esc_html_e( 'Left Bottom', 'user-login-switch' ); ?></option>
							<option value="right-bottom" <?php selected( 'right-bottom', $settings['widget_position'] ?? '' ); ?>><?php esc_html_e( 'Right Bottom', 'user-login-switch' ); ?></option>
						</select>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Style preset', 'user-login-switch' ); ?></th>
				<td>
					<select name="uls_settings[style_preset]">
						<option value="default" <?php selected( 'default', $settings['style_preset'] ); ?>><?php esc_html_e( 'Default', 'user-login-switch' ); ?></option>
						<option value="compact" <?php selected( 'compact', $settings['style_preset'] ); ?>><?php esc_html_e( 'Compact', 'user-login-switch' ); ?></option>
						<option value="minimal" <?php selected( 'minimal', $settings['style_preset'] ); ?>><?php esc_html_e( 'Minimal', 'user-login-switch' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Audit logging', 'user-login-switch' ); ?></th>
				<td>
					<label><input type="checkbox" name="uls_settings[enable_audit_log]" value="1" <?php checked( ! empty( $settings['enable_audit_log'] ) ); ?> /> <?php esc_html_e( 'Enable audit logs', 'user-login-switch' ); ?></label><br />
					<label><?php esc_html_e( 'Retention days', 'user-login-switch' ); ?> <input type="number" min="1" max="3650" name="uls_settings[log_retention_days]" value="<?php echo esc_attr( (int) $settings['log_retention_days'] ); ?>" /></label>
				</td>
			</tr>
		</table>

		<?php submit_button(); ?>
	</form>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var allRoles = document.querySelector('.uls-all-roles');
  var roleBoxes = document.querySelectorAll('.uls-role-box');
  if (!allRoles || !roleBoxes.length) {
    return;
  }

  function updateFromAll() {
    if (allRoles.checked) {
      roleBoxes.forEach(function (box) { box.checked = false; });
    }
  }

  function updateAllFromRoles() {
    var anyChecked = false;
    roleBoxes.forEach(function (box) {
      if (box.checked) {
        anyChecked = true;
      }
    });
    allRoles.checked = !anyChecked;
  }

  allRoles.addEventListener('change', updateFromAll);
  roleBoxes.forEach(function (box) {
    box.addEventListener('change', updateAllFromRoles);
  });
});
</script>
